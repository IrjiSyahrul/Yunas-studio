@extends('userPage.layouts.app')
@section('title', 'Jadwal Studio — Cek Ketersediaan & Reschedule')
@section('content')

    @include('userPage.styles.mobile-style')
    @include('userPage.components.modal.booking-modal')

 
   {{-- MOBILE VIEW --}}
    <div class="mobile-only bg-light min-vh-100">
        @include('userPage.components.mobile.header')
        @include('userPage.components.mobile.jadwal-mobile')
        @include('userPage.layouts.bottom-navbar')
    </div>


    {{-- DESKTOP VIEW --}}
    <div class="desktop-only">
        @include('userPage.components.desktop.jadwal')
        @include('userPage.components.desktop.footer')
        
    </div>

@endsection