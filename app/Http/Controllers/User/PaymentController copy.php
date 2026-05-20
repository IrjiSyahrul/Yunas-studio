<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Balance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentController
 *
 * Khusus menangani pembayaran via Midtrans.
 * BookingController hanya simpan data → PaymentController yang update status bayar.
 *
 * AKTIFKAN MIDTRANS:
 *   1. composer require midtrans/midtrans-php
 *   2. Isi .env: MIDTRANS_SERVER_KEY, MIDTRANS_CLIENT_KEY, MIDTRANS_IS_PRODUCTION
 *   3. Uncomment semua blok [MIDTRANS] di bawah
 */
class PaymentControllers extends Controller
{
    public function __construct()
     {
        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production');
        \Midtrans\Config::$isSanitized  = true;
        \Midtrans\Config::$is3ds        = true;
    }
    // ═══════════════════════════════════════════════════════════════════
    // STEP 1 — Buat Snap Token
    // Dipanggil frontend setelah BookingController berhasil simpan transaksi.
    // ═══════════════════════════════════════════════════════════════════

    public function createSnapToken(Request $request): JsonResponse
    {
        $transaksi = Transaksi::with(['packet.product'])
            ->findOrFail($request->input('transaksi_id'));

        if ($transaksi->status === 'sudah dibayar') {
            return response()->json(['message' => 'Transaksi ini sudah dibayar.'], 409);
        }

      
        try {
            $snapToken = \Midtrans\Snap::getSnapToken([
                'transaction_details' => [
                    'order_id'     => $transaksi->receipt_code,
                    'gross_amount' => (int) $transaksi->total_price,
                ],
                'customer_details' => [
                    'first_name' => $transaksi->customer_name,
                    'phone'      => $transaksi->phone_number,
                ],
                'item_details' => [[
                    'id'       => $transaksi->packet->id,
                    'price'    => (int) $transaksi->total_price,
                    'quantity' => 1,
                    'name'     => $transaksi->packet->product->name . ' - ' . $transaksi->packet->name,
                ]],
                'expiry' => ['unit' => 'days', 'duration' => 1],
            ]);
        
            return response()->json([
                'snap_token' => $snapToken,
                'order_id'   => $transaksi->receipt_code,
            ]);
        } catch (\Exception $e) {
            Log::error('Midtrans Snap Error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal memproses pembayaran.'], 500);
        }

        return response()->json(['message' => 'Midtrans belum aktif.']);
    }

    // ═══════════════════════════════════════════════════════════════════
    // STEP 2 — Webhook Midtrans
    // Dipanggil otomatis oleh server Midtrans setelah pembayaran selesai.
    // PENTING: Tambahkan 'payment/webhook' ke VerifyCsrfToken@$except
    // ═══════════════════════════════════════════════════════════════════

    public function handleWebhook(Request $request): JsonResponse
    {
    
        try {
            $notification = new \Midtrans\Notification();
            $orderId      = $notification->order_id;   // = receipt_code transaksi
            $txStatus     = $notification->transaction_status;
            $paymentType  = $notification->payment_type;
            $fraudStatus  = $notification->fraud_status ?? null;
        
            Log::info("Midtrans Webhook: order_id={$orderId}, status={$txStatus}");
        
            // Cari transaksi berdasarkan receipt_code
            $transaksi = Transaksi::where('receipt_code', $orderId)->firstOrFail();
        
            // Idempotent — skip jika sudah diproses
           if ($transaksi->status === 'sudah dibayar') {
                return response()->json(['message' => 'Already processed']);
            }
            if ($txStatus === 'pending') {
                $transaksi->update([
                    'status' => 'Belum dibayar',
                ]);

                return response()->json(['message' => 'Payment pending']);
            }
        
            // Tentukan apakah benar-benar lunas
            $isPaid = match(true) {
                $txStatus === 'settlement'                            => true,
                $txStatus === 'capture' && $fraudStatus === 'accept'  => true,
                default                                               => false,
            };
        
            // Jika dibatalkan / expired
            if (in_array($txStatus, ['cancel', 'deny', 'expire'])) {
                $transaksi->update(['status' => 'belum dibayar']);
                return response()->json(['message' => 'Payment ' . $txStatus]);
            }
        
            if ($isPaid) {
                DB::transaction(function () use ($transaksi, $paymentType) {
                    $transaksi->update([
                        'status'       => 'sudah dibayar',
                        'payment_type' => $this->mapPaymentType($paymentType),
                    ]);
        
                    // Catat pemasukan ke tabel expenses & update balance
                    // (sama seperti TransaksiController@recordIncome)
                    $this->recordIncome($transaksi);
                });
            }
        
            return response()->json(['message' => 'OK']);
        
        } catch (\Exception $e) {
            Log::error('Midtrans Webhook Error: ' . $e->getMessage());
            return response()->json(['message' => 'Server error.'], 500);
        }

        return response()->json(['message' => 'Midtrans belum aktif.']);
    }

    // ═══════════════════════════════════════════════════════════════════
    // STEP 3 — Redirect setelah user selesai di halaman Midtrans
    // ═══════════════════════════════════════════════════════════════════

    public function paymentSuccess(Request $request)
    {
        $transaksi = Transaksi::with(['packet.product'])
            ->where('receipt_code', $request->query('order_id'))
            ->first();

        return view('booking.success', compact('transaksi'));
    }

    public function paymentFailed(Request $request)
    {
        $transaksi = Transaksi::where('receipt_code', $request->query('order_id'))->first();

        return view('booking.failed', compact('transaksi'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPER — sama persis seperti TransaksiController
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Catat pemasukan ke tabel expenses dan update balance.
     * Dipanggil dari handleWebhook() setelah konfirmasi Midtrans.
     * Logika identik dengan TransaksiController@recordIncome.
     */
    private function recordIncome(Transaksi $transaksi): void
    {
        $description = "Billed To:\nName: {$transaksi->customer_name}\nPhone: {$transaksi->phone_number}\n"
                     . "Invoice Details:\nTransaction Date: " . $transaksi->created_at->format('d-m-Y');

        $categoryId = ExpenseCategory::where('name', 'Transaction')->first()->id ?? 1;
        $balance    = Balance::first();

        $existingExpense = Expense::where('name', $transaksi->receipt_code)
            ->where('type', 'income')
            ->first();

        if ($existingExpense) {
            // Update jika sudah ada (misal amount berubah)
            $difference = $transaksi->total_price - $existingExpense->amount;
            if ($difference != 0 && $balance) {
                $balance->amount += $difference;
                $balance->save();
            }
            $existingExpense->update([
                'description'      => $description,
                'amount'           => $transaksi->total_price,
                'paid_amount'      => $transaksi->total_price,
                'remaining_amount' => 0,
                'expense_date'     => now(),
            ]);
        } else {
            // Buat baru
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

    /**
     * Map payment_type dari Midtrans ke format di sistem.
     */
    private function mapPaymentType(string $paymentType): string
    {
        return in_array($paymentType, ['cash', 'cstore', 'indomaret', 'alfamart'])
            ? 'Cash'
            : 'Transfer/Qris';
    }
}