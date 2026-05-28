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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Booking;

class BookingController extends Controller
{
    /**
     * Validasi form → simpan transaksi (status: menunggu pembayaran) → buat Snap Token.
     * Status akan diupdate ke 'sudah dibayar' oleh PaymentController@handleWebhook.
     */
    public function createSnapToken(Request $request): JsonResponse
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

        $packet = Packet::with('product')->findOrFail($validated['packet_id']);

        if ($packet->product_id != $validated['product_id']) {
            return response()->json(['message' => 'Paket tidak sesuai dengan produk.'], 422);
        }

        /* ── Cek kapasitas slot sebelum lanjut ──────────────────────────
           Tolak langsung jika slot sudah penuh (2 booking aktif),
           sehingga tidak ada transaksi "hantu" di DB.
        ──────────────────────────────────────────────────────────────── */
        $slotCount = Transaksi::whereDate('session_date', $validated['session_date'])
            ->where('session_time', $validated['session_time'])
            ->whereNotIn('status', ['cancelled'])
            ->count();

        if ($slotCount >= 2) {
            return response()->json([
                'message' => 'Slot waktu ' . $validated['session_time'] .
                             ' pada tanggal ' . $validated['session_date'] .
                             ' sudah penuh. Silakan pilih waktu lain.',
                'errors'  => ['session_time' => ['Slot sudah penuh.']],
            ], 422);
        }

        DB::beginTransaction();
        try {
            /* Buat / temukan User berdasarkan nomor HP */
            $user = User::firstOrCreate(
                ['username' => $validated['phone_number']],
                [
                    'name'     => $validated['customer_name'],
                    'password' => Hash::make($validated['phone_number']),
                    'role_id'  => Role::where('name', 'User')->first()->id ?? 3,
                ]
            );

            /* ── Simpan transaksi ───────────────────────────────────────
               FIX: session_date dan session_time sekarang disimpan
               ke kolom yang benar di tabel transaksis.
            ────────────────────────────────────────────────────────── */
            $transaksi = Transaksi::create([
                'user_id'        => $user->id,
                'customer_name'  => $validated['customer_name'],
                'phone_number'   => $validated['phone_number'],
                'packet_id'      => $validated['packet_id'],
                'session_date'   => $validated['session_date'],   // ← FIX: disimpan ke DB
                'session_time'   => $validated['session_time'],   // ← FIX: disimpan ke DB
                'total_price'    => $packet->price,
                'discount'       => 0,
                'status'         => 'belum dibayar',
                'payment_type'   => 'none',
                'dp_amount'      => null,
                'process_status' => 'Pelanggan Belum Foto',
                'note'           => 'Booking online — ' . $validated['session_date'] . ' ' . $validated['session_time'],
                'receipt_code'   => 'TEMP-' . uniqid(),
            ]);

            /* Generate receipt_code — dipakai sebagai order_id Midtrans */
            $receiptCode = 'INV/' . Carbon::now()->format('Ymd') . '/' . $transaksi->transaction_id;

            $transaksi->receipt_code = $receiptCode;
            $transaksi->order_id     = $receiptCode;
            $transaksi->save();

            DB::commit();

            /* ── Konfigurasi & request Snap Token ──────────────────── */
            \Midtrans\Config::$serverKey    = config('midtrans.server_key');
            \Midtrans\Config::$isProduction = config('midtrans.is_production');
            \Midtrans\Config::$isSanitized  = true;
            \Midtrans\Config::$is3ds        = true;

            $snapToken = \Midtrans\Snap::getSnapToken([
                'transaction_details' => [
                    'order_id'     => $receiptCode,
                    'gross_amount' => (int) $packet->price,
                ],
                'customer_details' => [
                    'first_name' => $validated['customer_name'],
                    'phone'      => $validated['phone_number'],
                ],
                'item_details' => [
                    [
                        'id'       => $packet->id,
                        'price'    => (int) $packet->price,
                        'quantity' => 1,
                        'name'     => $packet->product->name . ' - ' . $packet->name,
                    ]
                ],
                'expiry' => [
                    'unit'     => 'minutes',
                    'duration' => 20,
                ],
            ]);

            return response()->json([
                'snap_token' => $snapToken,
                'order_id'   => $receiptCode,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Booking Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal memproses: ' . $e->getMessage()
            ], 500);
        }
    }

    /* ──────────────────────────────────────────────────────────────────
       AVAILABLE SLOTS
       GET /booking/available-slots?date=YYYY-MM-DD
       Response: { slots: [ { time, booked, max, available } ] }
    ────────────────────────────────────────────────────────────────── */
    public function availableSlots(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
        ]);

        $date  = $request->date;
        $slots = [];

        /* Generate slot 10:00 – 20:00, per 30 menit */
        for ($h = 10; $h <= 20; $h++) {
            foreach ([0, 30] as $m) {
                if ($h === 20 && $m === 30) break;

                $time = sprintf('%02d:%02d', $h, $m);

                /* Hitung booking aktif di slot ini */
                $booked = Transaksi::whereDate('session_date', $date)
                    ->where('session_time', $time)
                    ->whereNotIn('status', ['cancelled'])
                    ->count();

                $slots[] = [
                    'time'      => $time,
                    'booked'    => $booked,
                    'max'       => 2,
                    'available' => $booked < 2,
                ];
            }
        }

        return response()->json(['slots' => $slots]);
    }

    /* ──────────────────────────────────────────────────────────────────
       DOWNLOAD PDF INVOICE
    ────────────────────────────────────────────────────────────────── */
    public function downloadPdf($order_id)
    {
        $booking = Transaksi::with([
            'packet.product',
            'packet.printOptions',
            'additionals',
        ])
            ->where('order_id', $order_id)
            ->firstOrFail();

        $transaksi = $booking;

        $pdf = Pdf::loadView('invoices.template', compact('transaksi'))
            ->setPaper('a4', 'portrait');

        $fileName = str_replace('/', '-', $booking->order_id);

        return $pdf->download('invoice-booking-' . $fileName . '.pdf');
    }
}