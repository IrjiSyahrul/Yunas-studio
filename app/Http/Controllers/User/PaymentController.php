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
    //   'belum dibayar' → dibuat di BookingController, slot sudah terkunci
    //   'gagal'         → gagal/expired dari Midtrans, slot KEMBALI tersedia
    //   'dp'            → dibayar sebagian (nominal < total_price)
    //   'sudah dibayar' → dibayar lunas (nominal >= total_price)
    //
    // Status ('dp' vs 'sudah dibayar') ditentukan dengan MEMBANDINGKAN
    // nominal yang benar-benar disettle Midtrans (gross_amount notifikasi)
    // terhadap total_price transaksi — bukan dari flag terpisah.
    // Ini membuat perilaku konsisten dengan alur input manual admin
    // di TransaksiController.
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
            $grossAmount = (float) $notification->gross_amount;

            Log::info("Midtrans Webhook: {$orderId} - {$txStatus} - Rp{$grossAmount}");

            // Cari transaksi berdasarkan order_id yang dikirim Midtrans
            $transaksi = Transaksi::where('order_id', $orderId)->first();

            if (!$transaksi) {
                Log::warning("Transaksi tidak ditemukan di database: {$orderId}");
                return response()->json(['message' => 'Transaction not found.'], 404);
            }

            // ── Idempoten ──────────────────────────────────────────────
            // Jika transaksi sudah berstatus 'dp' ATAU 'sudah dibayar',
            // abaikan notifikasi ulang dari Midtrans (Midtrans kadang
            // mengirim webhook lebih dari satu kali untuk order yang sama).
            if (in_array($transaksi->status, ['sudah dibayar', 'dp'])) {
                return response()->json(['message' => 'Already processed.']);
            }

            // ── Status PENDING dari Midtrans ──────────────────────────
            // Artinya: user membuka halaman bayar tapi belum menyelesaikan pembayaran.
            // Transaksi sudah berstatus 'belum dibayar' sejak dibuat di BookingController,
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
            DB::transaction(function () use ($transaksi, $paymentType, $grossAmount) {

                // ── Tentukan status berdasarkan NOMINAL yang benar-benar dibayar ──
                if ($grossAmount >= (float) $transaksi->total_price) {
                    // Dibayar penuh — baik dari Full Payment langsung,
                    // atau dari pelunasan sisa DP (order_id berbeda, transaksi sama).
                    $transaksi->status = 'sudah dibayar';
                    // dp_amount SENGAJA TIDAK diubah/di-null-kan di sini,
                    // agar riwayat "DP Awal → Pelunasan" tetap tampil di invoice
                    // (lihat blade: @if($transaksi->dp_amount > 0) di status 'sudah dibayar').
                } else {
                    // Dibayar sebagian → DP
                    $transaksi->status    = 'dp';
                    $transaksi->dp_amount = $grossAmount;
                }

                $transaksi->payment_type   = $this->mapPaymentType($paymentType);
                $transaksi->process_status = 'Pelanggan Belum Foto';
                $transaksi->save();

                // ── Catat/hapus income — IKUTI PERSIS pola TransaksiController ──
                // Income HANYA dicatat saat status 'sudah dibayar' (lunas penuh).
                // Saat status 'dp', TIDAK dicatat ke Expense/Balance — nominal DP
                // cukup terlihat lewat kolom dp_amount & card "DP (Uang Muka)".
                if ($transaksi->status === 'sudah dibayar') {
                    $this->recordIncome($transaksi);
                } else {
                    $this->deleteIncome($transaksi);
                }
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
            'dp_amount'     => $transaksi->dp_amount,
            'remaining'     => $transaksi->status === 'dp'
                                ? $transaksi->total_price - $transaksi->dp_amount
                                : 0,
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
    // Dipanggil HANYA saat status transaksi 'sudah dibayar' (lunas penuh).
    // Disamakan persis dengan TransaksiController@recordIncome agar
    // pembukuan konsisten baik dari alur web maupun input manual admin.
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
    // HELPER — Hapus pencatatan pemasukan
    // Dipanggil saat status BUKAN 'sudah dibayar' (misal 'dp', 'gagal',
    // atau 'belum dibayar'). Disamakan persis dengan
    // TransaksiController@deleteIncome.
    // ═══════════════════════════════════════════════════════════════════
    private function deleteIncome(Transaksi $transaksi): void
    {
        $expense = Expense::where('name', $transaksi->receipt_code)
               ->where('type', 'income')
               ->first();

        if ($expense) {
            $expense->delete();
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