{{-- resources/views/booking/success.blade.php --}}
@extends('layouts.app') {{-- sesuaikan dengan layout Anda --}}

@section('title', 'Pembayaran Berhasil')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">

            @php
                $isPending = request('status') === 'pending';
            @endphp

            <div class="mb-4">
                @if($isPending)
                    <div class="display-1">⏳</div>
                    <h2 class="fw-bold mt-3">Menunggu Pembayaran</h2>
                    <p class="text-muted">Segera selesaikan pembayaran Anda. Kami akan konfirmasi setelah pembayaran diterima.</p>
                @else
                    <div class="display-1">🎉</div>
                    <h2 class="fw-bold mt-3 text-success">Pembayaran Berhasil!</h2>
                    <p class="text-muted">Booking sesi foto Anda telah dikonfirmasi. Sampai jumpa di studio!</p>
                @endif
            </div>

            @if($booking)
            <div class="card border-0 shadow-sm rounded-4 text-start mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-muted text-uppercase" style="letter-spacing: 0.05em; font-size: 11px;">Detail Booking</h6>
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="text-muted" style="width: 40%">Order ID</td>
                            <td class="fw-semibold font-monospace">{{ $booking->order_id }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Nama</td>
                            <td class="fw-semibold">{{ $booking->customer_name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">WhatsApp</td>
                            <td class="fw-semibold">{{ $booking->phone_number }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Produk</td>
                            <td class="fw-semibold">{{ $booking->packet->product->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Paket</td>
                            <td class="fw-semibold">{{ $booking->packet->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Tanggal</td>
                            <td class="fw-semibold">{{ \Carbon\Carbon::parse($booking->session_date)->translatedFormat('d F Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Waktu</td>
                            <td class="fw-semibold">{{ $booking->session_time }} WIB</td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-bold pt-2">Total Dibayar</td>
                            <td class="fw-bold text-success pt-2">
                                Rp {{ number_format($booking->total_price, 0, ',', '.') }}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            @endif

            <a href="/" class="btn btn-success btn-lg rounded-pill px-5">
                Kembali ke Beranda
            </a>

        </div>
    </div>
</div>
@endsection

{{-- ============================================================ --}}
{{-- resources/views/booking/failed.blade.php                     --}}
{{-- (Simpan sebagai file terpisah jika diperlukan)               --}}
{{-- ============================================================ --}}