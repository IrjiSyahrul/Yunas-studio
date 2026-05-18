<?php

namespace App\Http\Controllers\User;
use App\Http\Controllers\Controller;

use App\Models\Packet;
use App\Models\Transaksi;
use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    /**
     * User submit form booking → simpan ke tabel transaksis.
     *
     * Yang ditangani di sini:
     *   - Data pelanggan (customer_name, phone_number, product, packet, jadwal)
     *   - Buat akun User otomatis jika belum ada (sama seperti TransaksiController)
     *   - Generate receipt_code
     *   - Status pembayaran = 'belum dibayar' (akan diupdate PaymentController setelah bayar)
     *
     * Yang TIDAK ditangani di sini (urusan PaymentController):
     *   - Snap Token Midtrans
     *   - Webhook konfirmasi bayar
     *   - Update status → 'sudah dibayar'
     *   - Record income / update balance
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:50',
            'phone_number'  => 'required|string|max:20',
            'product_id'    => 'required|exists:products,id',
            'packet_id'     => 'required|exists:packets,id',
            'session_date'  => 'required|date|after_or_equal:today',
            'session_time'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak valid.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Ambil paket dari database — harga tidak boleh dari frontend
        $packet = Packet::with('product')->findOrFail($validated['packet_id']);

        // Pastikan packet milik product yang dipilih
        if ($packet->product_id != $validated['product_id']) {
            return response()->json(['message' => 'Paket tidak sesuai dengan produk.'], 422);
        }

        DB::beginTransaction();
        try {
            // ── 1. Buat / temukan User berdasarkan nomor telepon ──────────────
            // Sama persis seperti logika di TransaksiController@store
            $user = User::firstOrCreate(
                ['username' => $validated['phone_number']],
                [
                    'name'     => $validated['customer_name'],
                    'password' => Hash::make($validated['phone_number']),
                    'role_id'  => Role::where('name', 'User')->first()->id ?? 3,
                ]
            );

            // ── 2. Hitung harga ───────────────────────────────────────────────
            // Booking mandiri tidak ada tambahan ekstra maupun diskon
            // Jika nanti ingin ditambah, tinggal tambahkan di sini
            $totalPrice = $packet->price;

            // ── 3. Simpan transaksi ───────────────────────────────────────────
            $transaksi = Transaksi::create([
                'user_id'        => $user->id,
                'customer_name'  => $validated['customer_name'],
                'phone_number'   => $validated['phone_number'],
                'packet_id'      => $validated['packet_id'],
                'total_price'    => $totalPrice,
                'discount'       => 0,
                'status'         => 'belum dibayar',    // PaymentController yang akan update ini
                'payment_type'   => 'none',             // PaymentController yang akan update ini
                'dp_amount'      => null,
                'process_status' => 'Pelanggan Belum Foto',
                'note'           => 'Booking mandiri via website — ' . $validated['session_date'] . ' ' . $validated['session_time'],
                'receipt_code'   => 'TEMP-' . uniqid(), // Akan di-update setelah ID tersedia
            ]);

            // ── 4. Generate receipt_code pakai ID (sama seperti TransaksiController) ──
            $transaksi->receipt_code = 'INV/' . Carbon::now()->format('Ymd') . '/' . $transaksi->transaction_id;
            $transaksi->save();

            DB::commit();

            return response()->json([
                'message'      => 'Booking berhasil! Kami akan segera menghubungi Anda.',
                'transaksi_id' => $transaksi->id,
                'receipt_code' => $transaksi->receipt_code,
                'order_id'     => $transaksi->receipt_code, // Dipakai PaymentController nanti
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyimpan booking: ' . $e->getMessage(),
            ], 500);
        }
    }
}