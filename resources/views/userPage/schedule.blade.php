@extends('userPage.layouts.app')

@include('userPage.styles.mobile-style')

@section('content')

    @include('userPage.components.modal.booking-modal')


   
        @include('userPage.components.desktop.jadwal')
        @include('userPage.layouts.bottom-navbar')
        @include('userPage.components.desktop.footer')



@endsection