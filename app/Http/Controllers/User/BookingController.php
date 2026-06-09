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

class BookingController extends Controller
{
   
    private function generateAllSlots(): array
    {
        $slots = [];
        for ($h = 10; $h <= 18; $h++) {
            foreach ([0, 30] as $m) {
                if ($h === 18 && $m === 30) break;
                $slots[] = sprintf('%02d:%02d', $h, $m);
            }
        }
        return $slots; // 17 slot: 10:00 s.d. 18:00
    }
    private function getOccupiedSlots(string $startTime, int $durationMinutes): array
    {
        $slots    = [];
        $start    = Carbon::createFromFormat('H:i', $startTime);
        $slotCount = (int) ceil($durationMinutes / 30); // misal 60 menit → 2 slot

        for ($i = 0; $i < $slotCount; $i++) {
            $slots[] = $start->copy()->addMinutes($i * 30)->format('H:i');
        }

        return $slots;
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPER — Ambil semua booking aktif di 1 tanggal
    // Return: [ '10:00' => 2, '10:30' => 1, ... ]  (hitung per slot)
    //
    // Setiap transaksi aktif memblokir SEMUA slot dalam range durasinya.
    // Misal booking 10:00 paket 90 menit → memblokir 10:00, 10:30, 11:00.
    // ═══════════════════════════════════════════════════════════════════
    private function getBookedCountPerSlot(string $date): array
    {
        $booked = [];

        // Ambil semua transaksi aktif di tanggal ini beserta durasi paketnya
        $transaksis = Transaksi::whereDate('session_date', $date)
            ->whereNotIn('status', ['gagal'])
            ->with('packet:id,duration_minutes')
            ->get(['session_time', 'packet_id']);

        foreach ($transaksis as $t) {
            $duration = $t->packet->duration_minutes ?? 60; // fallback 60 menit
            $occupied = $this->getOccupiedSlots($t->session_time, $duration);

            foreach ($occupied as $slot) {
                $booked[$slot] = ($booked[$slot] ?? 0) + 1;
            }
        }

        return $booked;
    }

    // ═══════════════════════════════════════════════════════════════════
    // CREATE SNAP TOKEN
    // Validasi → cek konflik slot range → simpan transaksi → Snap Token
    // ═══════════════════════════════════════════════════════════════════
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
        $packet    = Packet::with('product')->findOrFail($validated['packet_id']);

        if ($packet->product_id != $validated['product_id']) {
            return response()->json(['message' => 'Paket tidak sesuai dengan produk.'], 422);
        }

        // ── Cek konflik slot berdasarkan RANGE DURASI ─────────────────
        // Ambil semua slot yang akan dipakai booking baru ini
        $slotsNeeded  = $this->getOccupiedSlots($validated['session_time'], $packet->duration_minutes);
        $bookedCounts = $this->getBookedCountPerSlot($validated['session_date']);

        $conflictSlots = [];
        foreach ($slotsNeeded as $slot) {
            if (($bookedCounts[$slot] ?? 0) >= 2) {
                $conflictSlots[] = $slot;
            }
        }

        // Juga pastikan semua slot dalam range tidak melewati batas jam operasional (18:00)
        $allSlots = $this->generateAllSlots();
        foreach ($slotsNeeded as $slot) {
            if (!in_array($slot, $allSlots)) {
                return response()->json([
                    'message' => 'Paket dengan durasi ' . $packet->duration_minutes . ' menit tidak muat jika dimulai pukul '
                                 . $validated['session_time'] . '. Silakan pilih waktu lebih awal.',
                    'errors'  => ['session_time' => ['Slot melewati jam operasional (maks 18:00).']],
                ], 422);
            }
        }

        if (!empty($conflictSlots)) {
            return response()->json([
                'message' => 'Slot ' . implode(', ', $conflictSlots) . ' pada tanggal '
                             . $validated['session_date'] . ' sudah penuh. Silakan pilih waktu lain.',
                'errors'  => ['session_time' => ['Slot sudah penuh.']],
            ], 422);
        }
        // ─────────────────────────────────────────────────────────────

        DB::beginTransaction();
        try {
            $user = User::firstOrCreate(
                ['username' => $validated['phone_number']],
                [
                    'name'     => $validated['customer_name'],
                    'password' => Hash::make($validated['phone_number']),
                    'role_id'  => Role::where('name', 'User')->first()->id ?? 3,
                ]
            );

            $transaksi = Transaksi::create([
                'user_id'        => $user->id,
                'customer_name'  => $validated['customer_name'],
                'phone_number'   => $validated['phone_number'],
                'packet_id'      => $validated['packet_id'],
                'session_date'   => $validated['session_date'],
                'session_time'   => $validated['session_time'],
                'total_price'    => $packet->price,
                'discount'       => 0,
                'status'         => 'belum dibayar',
                'payment_type'   => 'none',
                'dp_amount'      => null,
                'process_status' => 'Pelanggan Belum Foto',
                'note'           => 'Booking online — ' . $validated['session_date'] . ' ' . $validated['session_time'],
                'receipt_code'   => 'TEMP-' . uniqid(),
            ]);

            $receiptCode = 'INV/' . Carbon::now()->format('Ymd') . '/' . $transaksi->transaction_id;
            $transaksi->receipt_code = $receiptCode;
            $transaksi->order_id     = $receiptCode;
            $transaksi->save();

            DB::commit();

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
                    'duration' => 1,
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

    public function availableSlots(Request $request): JsonResponse
    {
        $request->validate([
            'date'      => 'required|date|after_or_equal:today',
            'packet_id' => 'nullable|exists:packets,id',
        ]);

        $date    = $request->date;
        $allSlots = $this->generateAllSlots();

        // Durasi paket yang sedang dilihat user (untuk cek apakah slot cukup)
        $viewDuration = 30; // default: 1 slot = 30 menit
        if ($request->packet_id) {
            $packet = Packet::find($request->packet_id);
            if ($packet) {
                $viewDuration = $packet->duration_minutes;
            }
        }

        // Hitung berapa booking aktif yang "menyentuh" setiap slot
        $bookedCounts = $this->getBookedCountPerSlot($date);

        $slots = [];
        foreach ($allSlots as $time) {
            // Slot yang akan dipakai jika user memilih waktu ini
            $slotsNeeded = $this->getOccupiedSlots($time, $viewDuration);

            // Cek apakah semua slot dalam range tersedia (tidak ada yang penuh)
            // dan semua slot dalam range masuk dalam jam operasional
            $rangeAvailable = true;
            $maxBookedInRange = 0;

            foreach ($slotsNeeded as $s) {
                // Slot di luar jam operasional → tidak bisa dipilih
                if (!in_array($s, $allSlots)) {
                    $rangeAvailable = false;
                    break;
                }
                $count = $bookedCounts[$s] ?? 0;
                if ($count >= 2) {
                    $rangeAvailable = false;
                    break;
                }
                $maxBookedInRange = max($maxBookedInRange, $count);
            }

            $slots[] = [
                'time'      => $time,
                'booked'    => $maxBookedInRange,   
                'max'       => 2,
                'available' => $rangeAvailable,
            ];
        }

        return response()->json(['slots' => $slots]);
    }


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