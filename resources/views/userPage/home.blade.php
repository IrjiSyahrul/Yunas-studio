@extends('userPage.layouts.app')

@include('userPage.styles.mobile-style')

@section('content')

    {{-- Modal Booking --}}
   @include('userPage.components.modal.booking-modal') 

    {{-- MOBILE VIEW --}}
    <div class="mobile-only bg-light min-vh-100">

        @include('userPage.components.mobile.header')

        @include('userPage.components.mobile.banner')

        @include('userPage.components.mobile.category-menu')


        @include('userPage.layouts.bottom-navbar')

    </div>

    {{-- DESKTOP VIEW --}}
    <div class="desktop-only">

        @include('userPage.components.desktop.hero')

        @include('userPage.components.desktop.package-section')

        @include('userPage.components.desktop.gallery')

        @include('userPage.components.desktop.booking-step')
        
        @include('userPage.components.desktop.footer')
    </div>

@endsection