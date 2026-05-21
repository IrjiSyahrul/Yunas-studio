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

        DB::beginTransaction();
        try {
            // Buat / temukan User
            $user = User::firstOrCreate(
                ['username' => $validated['phone_number']],
                [
                    'name'     => $validated['customer_name'],
                    'password' => Hash::make($validated['phone_number']),
                    'role_id'  => Role::where('name', 'User')->first()->id ?? 3,
                ]
            );

            // Simpan transaksi dengan status 'menunggu pembayaran'
            $transaksi = Transaksi::create([
                'user_id'        => $user->id,
                'customer_name'  => $validated['customer_name'],
                'phone_number'   => $validated['phone_number'],
                'packet_id'      => $validated['packet_id'],
                'total_price'    => $packet->price,
                'discount'       => 0,
                'status'         => 'belum dibayar',
                'payment_type'   => 'none',
                'dp_amount'      => null,
                'process_status' => 'Pelanggan Belum Foto',
                'note'           => 'Booking online — ' . $validated['session_date'] . ' ' . $validated['session_time'],
                'receipt_code'   => 'TEMP-' . uniqid(),
            ]);

            // Generate receipt_code — dipakai sebagai order_id Midtrans
            $receiptCode = 'INV/' . Carbon::now()->format('Ymd') . '/' . $transaksi->transaction_id;
       
            $transaksi->receipt_code = $receiptCode;
            $transaksi->order_id = $receiptCode;  // ← Tambahkan ini!

            $transaksi->save();

            DB::commit();

            // Konfigurasi Midtrans
            \Midtrans\Config::$serverKey    = config('midtrans.server_key');
            \Midtrans\Config::$isProduction = config('midtrans.is_production');
            \Midtrans\Config::$isSanitized  = true;
            \Midtrans\Config::$is3ds        = true;

            $snapToken = \Midtrans\Snap::getSnapToken([
                'transaction_details' => [
                    'order_id'     => $receiptCode, // receipt_code sebagai order_id
                    'gross_amount' => (int) $packet->price,
                ],
                'customer_details' => [
                    'first_name' => $validated['customer_name'],
                    'phone'      => $validated['phone_number'],
                ],
                'item_details' => [[
                    'id'       => $packet->id,
                    'price'    => (int) $packet->price,
                    'quantity' => 1,
                    'name'     => $packet->product->name . ' - ' . $packet->name,
                ]],
                    'expiry' => [
                    'unit' => 'minutes', 
                    'duration' => 1,    // Beri waktu 20 menit bagi pelanggan untuk transfer
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

   

public function downloadPdf($order_id)
{
    $booking = Transaksi::with([
        'packet.product',
        'packet.printOptions',
        'additionals'
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