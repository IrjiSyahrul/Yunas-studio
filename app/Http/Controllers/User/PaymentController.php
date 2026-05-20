<?php

namespace App\Http\Controllers\User;
use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Models\User;
use App\Models\Role;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Balance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════
    // WEBHOOK — Dipanggil otomatis Midtrans setelah pembayaran selesai
    // Ini satu-satunya tempat transaksi dibuat ke database
    // PENTING: Tambahkan 'payment/webhook' ke VerifyCsrfToken@$except
    // ═══════════════════════════════════════════════════════════════════

   public function handleWebhook(Request $request): JsonResponse
{
    \Midtrans\Config::$serverKey    = config('midtrans.server_key');
    \Midtrans\Config::$isProduction = config('midtrans.is_production');

    try {
        $notification = new \Midtrans\Notification();

        $orderId     = $notification->order_id;
        $txStatus    = $notification->transaction_status;
        $paymentType = $notification->payment_type;
        $fraudStatus = $notification->fraud_status ?? null;

        Log::info("Midtrans Webhook: {$orderId} - {$txStatus}");

        // Cari transaksi berdasarkan order_id yang dikirim Midtrans
        $transaksi = Transaksi::where('order_id', $orderId)->first();

        if (!$transaksi) {
            Log::warning("Transaksi tidak ditemukan di database: {$orderId}");
            return response()->json([
                'message' => 'Transaction not found.'
            ], 404);
        }

        // Jika sudah dibayar → stop agar tidak duplikat income
        if ($transaksi->status === 'sudah dibayar') {
            return response()->json([
                'message' => 'Already processed.'
            ]);
        }

        // Jalankan Update Status berdasarkan Status Midtrans
        if ($txStatus === 'pending') {
            // PENTING: Pastikan 'menunggu pembayaran' ada di daftar ENUM database Anda
            $transaksi->status = 'menunggu pembayaran'; 
            $transaksi->save();

            return response()->json(['message' => 'Payment pending.']);
        }

        if (in_array($txStatus, ['cancel', 'deny', 'expire'])) {
            $transaksi->status = 'gagal';
            $transaksi->save();

            return response()->json(['message' => 'Payment failed.']);
        }

        // Cek Keberhasilan Bayar (Settlement / Capture Accept)
        $isPaid = match(true) {
            $txStatus === 'settlement'                           => true,
            $txStatus === 'capture' && $fraudStatus === 'accept' => true,
            default                                              => false,
        };

        if (!$isPaid) {
            return response()->json(['message' => 'Not paid.']);
        }

        // Jika lolos seleksi di atas, berarti statusnya BERHASIL / PAID
        DB::transaction(function () use ($transaksi, $paymentType) {
            $transaksi->status = 'sudah dibayar';
            $transaksi->payment_type = $this->mapPaymentType($paymentType);
            $transaksi->process_status = 'Pelanggan Belum Foto';
            
            // 💡 PERBAIKAN: Jangan generate ulang receipt_code di sini karena sudah dibuat di BookingController
            // Cukup gunakan receipt_code yang sudah ada bawaan dari database.
            
            $transaksi->save();

            // Catat ke log keuangan / income
            $this->recordIncome($transaksi);
        });

        return response()->json(['message' => 'OK']);

    } catch (\Exception $e) {
        Log::error('Midtrans Webhook Error: ' . $e->getMessage());
        return response()->json([
            'message' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}

    // ═══════════════════════════════════════════════════════════════════
    // REDIRECT PAGE
    // ═══════════════════════════════════════════════════════════════════

    public function paymentSuccess(Request $request)
{
    $orderId = $request->query('order_id');

    if (!$orderId) {
        abort(404, 'Order ID not found.');
    }

    // Cari transaksi berdasarkan order_id
    $transaksi = Transaksi::with(['packet.product'])
        ->where('order_id', $orderId)
        ->first();

    // Jika transaksi tidak ditemukan
    if (!$transaksi) {

        return view('userPage.layouts.success', [
            'booking' => null
        ]);
    }

    // Ambil session date & time dari note
    $sessionDate = null;
    $sessionTime = null;

    if ($transaksi->note) {

        // Contoh note:
        // Booking online — 2026-05-19 14:00

        $parts = explode(' — ', $transaksi->note);

        if (isset($parts[1])) {

            $datetime = explode(' ', $parts[1]);

            $sessionDate = $datetime[0] ?? null;
            $sessionTime = $datetime[1] ?? null;
        }
    }

    // Mapping agar sesuai dengan Blade lama
    $booking = (object) [

        'order_id'      => $transaksi->order_id,
        'customer_name' => $transaksi->customer_name,
        'phone_number'  => $transaksi->phone_number,
        'session_date'  => $sessionDate,
        'session_time'  => $sessionTime,
        'total_price'   => $transaksi->total_price,
        'status'        => $transaksi->status,
        'payment_type'  => $transaksi->payment_type,
        'receipt_code'  => $transaksi->receipt_code,
        'packet'        => $transaksi->packet,
    ];

    return view('userPage.layouts.success', compact('booking'));
}
    public function paymentFailed(Request $request)
    {
        return view('userPage.layouts.failed');
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPER
    // ═══════════════════════════════════════════════════════════════════

    private function recordIncome(Transaksi $transaksi): void
    {
        $description = "Billed To:\nName: {$transaksi->customer_name}\n"
                     . "Phone: {$transaksi->phone_number}\n"
                     . "Invoice Details:\nTransaction Date: "
                     . $transaksi->created_at->format('d-m-Y');

        $categoryId = ExpenseCategory::where('name', 'Transaction')->first()->id ?? 1;
        $balance    = Balance::first();

        $existing = Expense::where('name', $transaksi->receipt_code)
            ->where('type', 'income')->first();

        if ($existing) {
            $diff = $transaksi->total_price - $existing->amount;
            if ($diff != 0 && $balance) {
                $balance->amount += $diff;
                $balance->save();
            }
            $existing->update([
                'description'      => $description,
                'amount'           => $transaksi->total_price,
                'paid_amount'      => $transaksi->total_price,
                'remaining_amount' => 0,
                'expense_date'     => now(),
            ]);
        } else {
            Expense::create([
                'name'             => $transaksi->receipt_code,
                'type'             => 'income',
                'description'      => $description,
                'amount'           => $transaksi->total_price,
                'paid_amount'      => $transaksi->total_price,
                'remaining_amount' => 0,
                'expense_date'     => now(),
                'category_id'      => $categoryId,
                'is_paid'          => true,
            ]);
            if ($balance) {
                $balance->amount += $transaksi->total_price;
                $balance->save();
            }
        }
    }

    private function mapPaymentType(string $paymentType): string
    {
        return in_array($paymentType, ['cash', 'cstore', 'indomaret', 'alfamart'])
            ? 'Cash'
            : 'Transfer/Qris';
    }
}