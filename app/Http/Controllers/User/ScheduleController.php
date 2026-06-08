<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Packet;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
    public function index()
    {
        return view('userPage.schedule');
    }

    // ═══════════════════════════════════════════════════════════════════
    // API — SLOT TERSEDIA PER HARI
    // GET /jadwal/slots?date=YYYY-MM-DD&packet_id=X (packet_id opsional)
    //
    // Sama persis dengan BookingController@availableSlots,
    // namun dipisah agar route /jadwal berdiri sendiri.
    // ═══════════════════════════════════════════════════════════════════
    public function slots(Request $request): JsonResponse
    {
        $request->validate([
            'date'      => 'required|date',
            'packet_id' => 'nullable|exists:packets,id',
        ]);

        $date     = $request->date;
        $allSlots = $this->generateAllSlots();

        $viewDuration = 30;
        if ($request->packet_id) {
            $packet = Packet::find($request->packet_id);
            if ($packet) $viewDuration = $packet->duration_minutes;
        }

        $bookedCounts = $this->getBookedCountPerSlot($date);

        $slots = [];
        foreach ($allSlots as $time) {
            $slotsNeeded      = $this->getOccupiedSlots($time, $viewDuration);
            $rangeAvailable   = true;
            $maxBookedInRange = 0;

            foreach ($slotsNeeded as $s) {
                if (!in_array($s, $allSlots)) { $rangeAvailable = false; break; }
                $count = $bookedCounts[$s] ?? 0;
                if ($count >= 2)              { $rangeAvailable = false; break; }
                $maxBookedInRange = max($maxBookedInRange, $count);
            }

            $slots[] = [
                'time'      => $time,
                'booked'    => $maxBookedInRange,
                'max'       => 2,
                'available' => $rangeAvailable,
            ];
        }

        return response()->json(['slots' => $slots, 'date' => $date]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // API — CARI BOOKING UNTUK RESCHEDULE
    // POST /jadwal/cari-booking
    // Body: { identifier: "08xxx..." atau "INV/..." }
    //
    // Mengembalikan semua booking aktif (belum dibayar / sudah dibayar)
    // milik nomor HP atau order ID tersebut.
    // ═══════════════════════════════════════════════════════════════════
    public function cariBooking(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string|max:100',
        ]);

        $identifier = trim($request->identifier);

        // Cari berdasarkan nomor HP atau order_id
        $query = Transaksi::with('packet:id,name,duration_minutes,price')
            ->whereIn('status', ['belum dibayar', 'sudah dibayar'])
            // Hanya booking yang tanggalnya hari ini atau ke depan
            ->whereDate('session_date', '>=', now()->toDateString())
            ->where(function ($q) use ($identifier) {
                $q->where('phone_number', $identifier)
                  ->orWhere('order_id', $identifier);
            })
            ->orderBy('session_date')
            ->orderBy('session_time');

        $bookings = $query->get()->map(function ($t) {
            return [
                'transaction_id' => $t->transaction_id,
                'order_id'       => $t->order_id,
                'customer_name'  => $t->customer_name,
                'phone_number'   => $t->phone_number,
                'packet_name'    => $t->packet->name ?? '-',
                'duration'       => $t->packet->duration_minutes ?? 60,
                'session_date'   => $t->session_date,
                'session_time'   => $t->session_time,
                'status'         => $t->status,
                'total_price'    => $t->total_price,
            ];
        });

        if ($bookings->isEmpty()) {
            return response()->json([
                'message'  => 'Tidak ada booking aktif yang ditemukan untuk nomor HP atau Order ID tersebut.',
                'bookings' => [],
            ], 404);
        }

        return response()->json(['bookings' => $bookings]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // API — LAKUKAN RESCHEDULE
    // POST /jadwal/reschedule
    // Body: { transaction_id, new_date, new_time, identifier }
    //
    // Validasi:
    // 1. Transaksi milik identifier (HP / order_id)
    // 2. Status masih 'belum dibayar' atau 'sudah dibayar'
    // 3. Slot baru tidak penuh (mempertimbangkan durasi paket)
    // 4. Slot baru tidak sama dengan slot lama
    // 5. Tanggal baru tidak di masa lalu
    // ═══════════════════════════════════════════════════════════════════
    public function reschedule(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_id' => 'required|exists:transaksi,transaction_id',
            'new_date'       => 'required|date|after_or_equal:today',
            'new_time'       => 'required|string',
            'identifier'     => 'required|string|max:100',
        ]);

        $identifier = trim($request->identifier);

        // Cari transaksi & pastikan milik identifier ini
        $transaksi = Transaksi::with('packet:id,name,duration_minutes')
            ->where('transaction_id', $request->transaction_id)
            ->where(function ($q) use ($identifier) {
                $q->where('phone_number', $identifier)
                  ->orWhere('order_id', $identifier);
            })
            ->whereIn('status', ['belum dibayar', 'sudah dibayar'])
            ->first();

        if (!$transaksi) {
            return response()->json([
                'message' => 'Booking tidak ditemukan atau tidak bisa di-reschedule.',
            ], 404);
        }

        // Cek tidak sama dengan jadwal lama
        if ($transaksi->session_date == $request->new_date &&
            $transaksi->session_time == $request->new_time) {
            return response()->json([
                'message' => 'Jadwal baru sama dengan jadwal saat ini.',
            ], 422);
        }

        $duration    = $transaksi->packet->duration_minutes ?? 60;
        $allSlots    = $this->generateAllSlots();

        // Cek slot baru masuk jam operasional
        $slotsNeeded = $this->getOccupiedSlots($request->new_time, $duration);
        foreach ($slotsNeeded as $s) {
            if (!in_array($s, $allSlots)) {
                return response()->json([
                    'message' => 'Waktu ' . $request->new_time . ' dengan durasi ' .
                                 $duration . ' menit melewati jam operasional (maks 18:00).',
                ], 422);
            }
        }

        // Hitung slot terpakai di tanggal baru (EXCLUDE transaksi ini sendiri)
        $bookedCounts = $this->getBookedCountPerSlot($request->new_date, $transaksi->transaction_id);

        foreach ($slotsNeeded as $slot) {
            if (($bookedCounts[$slot] ?? 0) >= 2) {
                return response()->json([
                    'message' => 'Slot ' . $request->new_time . ' pada tanggal ' .
                                 $request->new_date . ' sudah penuh. Silakan pilih waktu lain.',
                ], 422);
            }
        }

        // Simpan jadwal lama untuk catatan
        $oldDate = $transaksi->session_date;
        $oldTime = $transaksi->session_time;

        // Update jadwal
        $transaksi->session_date = $request->new_date;
        $transaksi->session_time = $request->new_time;
        $transaksi->note         = $transaksi->note .
            "\n[Reschedule] " . $oldDate . ' ' . $oldTime .
            ' → ' . $request->new_date . ' ' . $request->new_time .
            ' pada ' . now()->format('d-m-Y H:i');
        $transaksi->save();

        Log::info("Reschedule: {$transaksi->order_id} dari {$oldDate} {$oldTime} ke {$request->new_date} {$request->new_time}");

        return response()->json([
            'message'      => 'Jadwal berhasil diubah!',
            'new_date'     => $request->new_date,
            'new_time'     => $request->new_time,
            'old_date'     => $oldDate,
            'old_time'     => $oldTime,
            'order_id'     => $transaksi->order_id,
            'packet_name'  => $transaksi->packet->name ?? '-',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS (sama dengan BookingController — bisa diextract ke trait)
    // ═══════════════════════════════════════════════════════════════════
    private function generateAllSlots(): array
    {
        $slots = [];
        for ($h = 10; $h <= 18; $h++) {
            foreach ([0, 30] as $m) {
                if ($h === 18 && $m === 30) break;
                $slots[] = sprintf('%02d:%02d', $h, $m);
            }
        }
        return $slots;
    }

    private function getOccupiedSlots(string $startTime, int $durationMinutes): array
    {
        $slots     = [];
        $start     = Carbon::createFromFormat('H:i', $startTime);
        $slotCount = (int) ceil($durationMinutes / 30);
        for ($i = 0; $i < $slotCount; $i++) {
            $slots[] = $start->copy()->addMinutes($i * 30)->format('H:i');
        }
        return $slots;
    }

    /**
     * Hitung berapa booking aktif yang "menyentuh" setiap slot di 1 hari.
     * $excludeTransactionId: abaikan transaksi ini (dipakai saat reschedule
     * agar slot lama milik booking tersebut tidak dihitung sebagai konflik).
     */
    private function getBookedCountPerSlot(string $date, ?int $excludeTransactionId = null): array
    {
        $booked = [];

        $query = Transaksi::whereDate('session_date', $date)
            ->whereNotIn('status', ['gagal'])
            ->with('packet:id,duration_minutes');

        if ($excludeTransactionId) {
            $query->where('transaction_id', '!=', $excludeTransactionId);
        }

        $transaksis = $query->get(['session_time', 'packet_id', 'transaction_id']);

        foreach ($transaksis as $t) {
            $duration = $t->packet->duration_minutes ?? 60;
            $occupied = $this->getOccupiedSlots($t->session_time, $duration);
            foreach ($occupied as $slot) {
                $booked[$slot] = ($booked[$slot] ?? 0) + 1;
            }
        }

        return $booked;
    }
}