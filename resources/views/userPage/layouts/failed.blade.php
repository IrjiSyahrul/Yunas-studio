@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-md mx-auto">
        <!-- Failed Payment Card -->
        <div class="bg-white rounded-lg shadow-lg p-8 text-center">
            <!-- Failed Icon -->
            <div class="mb-6">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-red-100 rounded-full">
                    <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>
            </div>

            <!-- Title -->
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Pembayaran Gagal</h1>
            
            <!-- Message -->
            <p class="text-gray-600 mb-6">
                Maaf, transaksi pembayaran Anda tidak berhasil diproses. Silakan coba kembali atau hubungi dukungan pelanggan kami.
            </p>

            <!-- Error Details (Optional) -->
            @if(isset($error_message))
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-left">
                    <p class="text-sm text-red-700">
                        <strong>Kesalahan:</strong> {{ $error_message }}
                    </p>
                </div>
            @endif

            <!-- Order Details (Optional) -->
            @if(isset($order))
                <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                    <p class="text-sm text-gray-700 mb-2">
                        <strong>No. Pesanan:</strong> {{ $order->id }}
                    </p>
                    <p class="text-sm text-gray-700">
                        <strong>Tanggal:</strong> {{ $order->created_at->format('d M Y H:i') }}
                    </p>
                </div>
            @endif

            <!-- Action Buttons -->
            <div class="flex flex-col gap-3">
                
                <a href="{{ route('userPage.home') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-3 px-6 rounded-lg transition duration-200">
                    Kembali ke Dashboard
                </a>
                
            </div>
        </div>
    </div>
</div>
@endsection
