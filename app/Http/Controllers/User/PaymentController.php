<?php

namespace App\Http\Controllers\User;
use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Balance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════
    // WEBHOOK — Dipanggil otomatis Midtrans setelah pembayaran selesai
    // Ini satu-satunya tempat status transaksi diupdate dari Midtrans
    // PENTING: Tambahkan 'payment/webhook' ke VerifyCsrfToken@$except
    //
    // Alur status transaksi:
    //   'pending'      → dibuat di BookingController, slot sudah terkunci
    //   'cancelled'    → gagal/expired dari Midtrans, slot KEMBALI tersedia
    //   'sudah dibayar'→ pembayaran berhasil, slot tetap terkunci
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
                return response()->json(['message' => 'Transaction not found.'], 404);
            }

            // Idempoten: jika sudah dibayar, abaikan notifikasi ulang dari Midtrans
            // Midtrans kadang mengirim webhook lebih dari satu kali untuk order yang sama
            if ($transaksi->status === 'sudah dibayar') {
                return response()->json(['message' => 'Already processed.']);
            }

            // ── Status PENDING dari Midtrans ──────────────────────────────
            // Artinya: user membuka halaman bayar tapi belum menyelesaikan pembayaran.
            // Transaksi sudah berstatus 'pending' sejak dibuat di BookingController,
            // jadi tidak perlu update DB — slot tetap terkunci, cukup acknowledge ke Midtrans.
            if ($txStatus === 'pending') {
                return response()->json(['message' => 'Payment pending.']);
            }

            // ── Status GAGAL / EXPIRED dari Midtrans ─────────────────────
            
            if (in_array($txStatus, ['cancel', 'deny', 'expire'])) {
                $transaksi->status = 'gagal';
                $transaksi->save();

                Log::info("Transaksi {$orderId} dibatalkan — slot dikembalikan.");
                return response()->json(['message' => 'Payment cancelled.']);
            }

            // ── Cek Keberhasilan Bayar (Settlement / Capture Accept) ──────
            // 'settlement' → transfer bank / e-wallet sudah settle
            // 'capture' + fraud 'accept' → kartu kredit berhasil & aman
            $isPaid = match (true) {
                $txStatus === 'settlement'                            => true,
                $txStatus === 'capture' && $fraudStatus === 'accept' => true,
                default                                               => false,
            };

            if (!$isPaid) {
                return response()->json(['message' => 'Not paid.']);
            }

            // ── Pembayaran BERHASIL ───────────────────────────────────────
            // Jalankan dalam DB transaction agar update status & pencatatan
            // income terjadi secara atomik — keduanya berhasil atau keduanya batal.
            DB::transaction(function () use ($transaksi, $paymentType) {
                $transaksi->status         = 'sudah dibayar';
                $transaksi->payment_type   = $this->mapPaymentType($paymentType);
                $transaksi->process_status = 'Pelanggan Belum Foto';

                
                // Men-generate ulang akan memutus referensi ke Midtrans order_id.

                $transaksi->save();

                // Catat pemasukan ke tabel expenses / log keuangan
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
    // REDIRECT PAGE — Halaman setelah user selesai dari popup Midtrans
    // Midtrans redirect ke sini dengan query param ?order_id=INV/...
    // ═══════════════════════════════════════════════════════════════════
    public function paymentSuccess(Request $request)
    {
        $orderId = $request->query('order_id');

        if (!$orderId) {
            abort(404, 'Order ID not found.');
        }

        // Eager load packet & product agar tidak N+1 query di Blade
        $transaksi = Transaksi::with(['packet.product'])
            ->where('order_id', $orderId)
            ->first();

        // Transaksi tidak ditemukan — tampilkan halaman sukses tanpa detail
        if (!$transaksi) {
            return view('userPage.layouts.success', ['booking' => null]);
        }

        $booking = (object) [
            'order_id'      => $transaksi->order_id,
            'customer_name' => $transaksi->customer_name,
            'phone_number'  => $transaksi->phone_number,
            'session_date'  => $transaksi->session_date,  
            'session_time'  => $transaksi->session_time,  
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
    // HELPER — Catat pemasukan ke tabel expenses
    // Dipanggil hanya saat pembayaran berhasil (settlement/capture).
    // Menggunakan receipt_code sebagai nama unik agar tidak duplikat.
    // ═══════════════════════════════════════════════════════════════════
    private function recordIncome(Transaksi $transaksi): void
    {
        $description = "Billed To:\nName: {$transaksi->customer_name}\n"
                     . "Phone: {$transaksi->phone_number}\n"
                     . "Invoice Details:\nTransaction Date: "
                     . $transaksi->created_at->format('d-m-Y');

        $categoryId = ExpenseCategory::where('name', 'Transaction')->first()->id ?? 1;
        $balance    = Balance::first();

        // Cek apakah income untuk transaksi ini sudah pernah dicatat sebelumnya
        // (misalnya webhook dikirim dua kali oleh Midtrans)
        $existing = Expense::where('name', $transaksi->receipt_code)
            ->where('type', 'income')
            ->first();

        if ($existing) {
            // Jika sudah ada, update selisih ke balance agar tidak dobel hitung
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
            // Income belum ada → buat baru dan tambahkan ke balance
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

    // ═══════════════════════════════════════════════════════════════════
    // HELPER — Normalisasi nama metode pembayaran dari Midtrans
    // Midtrans mengembalikan string seperti 'bank_transfer', 'gopay',
    // 'qris', 'indomaret', dsb. Disederhanakan menjadi dua kategori.
    // ═══════════════════════════════════════════════════════════════════
    private function mapPaymentType(string $paymentType): string
    {
        // Pembayaran tunai via minimarket → kategori 'Cash'
        // Semua lainnya (transfer, QRIS, e-wallet) → kategori 'Transfer/Qris'
        return in_array($paymentType, ['cash', 'cstore', 'indomaret', 'alfamart'])
            ? 'Cash'
            : 'Transfer/Qris';
    }
}