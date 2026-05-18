@extends('userPage.layouts.app')

@push('styles')
    <style>
        /* ===== MOBILE APP STYLES ===== */
        /* Sembunyikan bottom nav di desktop */
        .mobile-bottom-nav {
            display: none;
        }

        @media (max-width: 767.98px) {

            /* Tambah padding bawah untuk bottom nav */
            body {
                padding-bottom: 70px;
            }

            /* Sembunyikan elemen desktop di mobile */
            .desktop-only {
                display: none !important;
            }

            /* Bottom Navigation Bar */
            .mobile-bottom-nav {
                display: flex;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 1050;
                background: #fff;
                border-top: 1px solid #e9ecef;
                height: 64px;
                box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.08);
            }

            .mobile-bottom-nav a {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 4px;
                text-decoration: none;
                color: #adb5bd;
                font-size: 10px;
                transition: color 0.2s;
            }

            .mobile-bottom-nav a.active,
            .mobile-bottom-nav a:hover {
                color: #198754;
            }

            .mobile-bottom-nav a i {
                font-size: 22px;
            }

            /* Mobile Header */
            .mobile-header {
                position: sticky;
                top: 0;
                z-index: 1040;
                background: #198754;
                padding: 12px 16px;
            }

            /* Mobile Banner */
            .mobile-banner-wrap {
                padding: 12px 16px 0;
            }

            /* Mobile Menu Paket */
            .mobile-menu-wrap {
                padding: 16px 16px 0;
            }

            .mobile-menu-grid {
                background: #fff;
                border-radius: 16px;
                padding: 16px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            }

            .mobile-menu-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                color: #343a40;
            }

            .mobile-menu-icon {
                width: 56px;
                height: 56px;
                background: #f8f9fa;
                border-radius: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                transition: background 0.2s;
            }

            .mobile-menu-item:active .mobile-menu-icon {
                background: #e9f5ee;
            }

            /* Mobile Promo Card */
            .mobile-promo-wrap {
                padding: 16px;
            }

            .mobile-promo-card {
                background: #fff;
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            }

            .mobile-section-title {
                font-size: 15px;
                font-weight: 600;
                color: #212529;
                margin-bottom: 12px;
            }
        }

        @media (min-width: 768px) {

            /* Sembunyikan elemen mobile di desktop */
            .mobile-only {
                display: none !important;
            }
        }
    </style>
@endpush

@section('content')

    {{-- ===== BOOKING MODAL (shared) ===== --}}
    <div id="bookingModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Booking Sesi Foto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 pb-4">
                    <form class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Pilih Paket</label>
                            <select name="product_id" class="form-select">
                                <option value="">-- Pilih Paket --</option>
                                @foreach($products as $prod)
                                    <option value="{{ $prod->id }}">{{ $prod->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Pilih Produk</label>
                            <select name="packet_id" class="form-select">
                                <option value="" disabled selected>-- Pilih Produk --</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Nama Lengkap</label>
                            <input type="text" class="form-control" placeholder="Masukkan nama lengkap">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Nomor Telepon</label>
                            <input type="text" class="form-control" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Tanggal</label>
                            <input type="date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Waktu</label>
                            <select class="form-select">
                                <option>09:00</option>
                                <option>11:00</option>
                                <option>13:00</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Catatan Tambahan</label>
                            <textarea class="form-control" rows="3" placeholder="Tambahkan catatan..."></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Konfirmasi Booking</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- ==========================================
    MOBILE APP LAYOUT (tersembunyi di desktop)
    =========================================== --}}
    <div class="mobile-only bg-light min-vh-100">

        {{-- Sticky Header --}}
        <div class="mobile-header">
            <div class="d-flex align-items-center gap-2">
                <a>YUNAS STUDIO</a>
                <a href="/"
                    class="bg-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 text-decoration-none"
                    style="width:42px; height:42px;">

                </a>
            </div>
        </div>

        {{-- Banner Carousel --}}
        <div class="mobile-banner-wrap">
            <div id="mobileBanner" class="carousel slide rounded-4 overflow-hidden shadow-sm" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <img src="{{ asset('images/portrait.jpg') }}" class="d-block w-100"
                            style="height: 180px; object-fit: cover;" alt="Banner 1">
                    </div>
                    <div class="carousel-item">
                        <img src="{{ asset('images/banner2.jpg') }}" class="d-block w-100"
                            style="height: 180px; object-fit: cover;" alt="Banner 2">
                    </div>
                </div>
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#mobileBanner" data-bs-slide-to="0" class="active"></button>
                    <button type="button" data-bs-target="#mobileBanner" data-bs-slide-to="1"></button>
                </div>
            </div>
        </div>

        {{-- Menu Kategori Paket --}}
        <div class="mobile-menu-wrap">
            <div class="mobile-section-title">Kategori Paket</div>
            <div class="mobile-menu-grid">
                <div class="row g-3 text-center">
                    <div class="col-3">
                        <a href="#" class="mobile-menu-item">
                            <div class="mobile-menu-icon">
                                <img src="{{ asset('icons/portrait.png') }}" width="28" alt="">
                            </div>
                            <small class="fw-medium" style="font-size:11px;">Portrait</small>
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="#" class="mobile-menu-item">
                            <div class="mobile-menu-icon">
                                <img src="{{ asset('icons/family.png') }}" width="28" alt="">
                            </div>
                            <small class="fw-medium" style="font-size:11px;">Family</small>
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="#" class="mobile-menu-item">
                            <div class="mobile-menu-icon">
                                <img src="{{ asset('icons/product.png') }}" width="28" alt="">
                            </div>
                            <small class="fw-medium" style="font-size:11px;">Product</small>
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="#" class="mobile-menu-item">
                            <div class="mobile-menu-icon">
                                <img src="{{ asset('icons/wedding.png') }}" width="28" alt="">
                            </div>
                            <small class="fw-medium" style="font-size:11px;">Wedding</small>
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="#" class="mobile-menu-item">
                            <div class="mobile-menu-icon">
                                <img src="{{ asset('icons/portrait.png') }}" width="28" alt="">
                            </div>
                            <small class="fw-medium" style="font-size:11px;">Portrait</small>
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="#" class="mobile-menu-item">
                            <div class="mobile-menu-icon">
                                <img src="{{ asset('icons/family.png') }}" width="28" alt="">
                            </div>
                            <small class="fw-medium" style="font-size:11px;">Family</small>
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="#" class="mobile-menu-item">
                            <div class="mobile-menu-icon">
                                <img src="{{ asset('icons/product.png') }}" width="28" alt="">
                            </div>
                            <small class="fw-medium" style="font-size:11px;">Product</small>
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="#" class="mobile-menu-item">
                            <div class="mobile-menu-icon">
                                <img src="{{ asset('icons/wedding.png') }}" width="28" alt="">
                            </div>
                            <small class="fw-medium" style="font-size:11px;">Wedding</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Promo --}}
        <div class="mobile-promo-wrap">
            <div class="mobile-section-title">Promo Studio</div>
            <div class="mobile-promo-card">
                <img src="{{ asset('images/promo.jpg') }}" class="w-100" style="height: 160px; object-fit: cover;"
                    alt="Promo">
                <div class="p-3">
                    <h6 class="fw-bold mb-1">Paket Graduation</h6>
                    <p class="text-muted small mb-3">Diskon hingga 30% bulan ini.</p>
                    <button class="btn btn-success rounded-pill w-100 fw-semibold" data-bs-toggle="modal"
                        data-bs-target="#bookingModal">
                        Booking Sekarang
                    </button>
                </div>
            </div>
        </div>
        @include('userPage.layouts.bottom-navbar')
    </div>

    {{-- ==========================================
    DESKTOP LAYOUT (tersembunyi di mobile)
    =========================================== --}}

    {{-- Hero Section --}}
    <section class="desktop-only bg-secondary position-relative overflow-hidden mx-3 mx-md-4 mx-lg-5 mt-4 rounded-4"
        style="height: 90vh;">
        <img src="{{ asset('images/studio.jpg') }}" class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover"
            alt="Studio Foto">
        <div class="position-absolute top-0 start-0 w-100 h-100"
            style="background: linear-gradient(to right, rgba(0,0,0,0.70), rgba(0,0,0,0.40), transparent);">
        </div>
        <div class="position-relative h-100 d-flex align-items-center px-4 px-md-5" style="z-index: 10;">
            <div class="text-white" style="max-width: 700px;">
                <span class="badge rounded-pill text-uppercase fw-semibold mb-4 px-3 py-2" style="font-size: 0.7rem; letter-spacing: 0.15em;
                                 background: rgba(255,255,255,0.1);
                                 border: 1px solid rgba(255,255,255,0.2);">
                    Professional Photo Studio
                </span>
                <h1 class="display-4 fw-bold lh-sm mb-4">
                    Abadikan Momen <br>
                    Berharga Anda <br>
                    <span class="text-secondary">dengan Sempurna</span>
                </h1>
                <p class="text-white-50 mb-5 fs-6 lh-lg" style="max-width: 480px;">
                    Studio foto profesional dengan peralatan lengkap,
                    pencahayaan terbaik, dan tim fotografer berpengalaman
                    untuk hasil yang memukau.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <button class="btn btn-light rounded-pill px-4 py-2 fw-semibold" data-bs-toggle="modal"
                        data-bs-target="#bookingModal">
                        Booking Sekarang
                    </button>
                    <a href="#layanan" class="btn rounded-pill px-4 py-2 fw-semibold text-white"
                        style="border: 1px solid rgba(255,255,255,0.5);">
                        Lihat Layanan
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Paket Section --}}
    <section id="paket" class="desktop-only bg-light py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <span class="badge bg-secondary text-uppercase fw-semibold px-3 py-2 mb-3 rounded-pill"
                    style="font-size: 0.7rem; letter-spacing: 0.15em;">
                    Layanan Kami
                </span>
                <h2 class="display-5 fw-bold text-dark mb-3">Pilihan Paket Foto</h2>
                <p class="text-muted mx-auto" style="max-width: 500px;">
                    Pilih paket foto yang sesuai dengan kebutuhan Anda.
                    Semua paket sudah termasuk peralatan studio dan editing profesional.
                </p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                        <div class="overflow-hidden" style="height: 256px;">
                            <img src="{{ asset('images/portrait.jpg') }}" class="card-img-top w-100 h-100 object-fit-cover"
                                style="transition: transform 0.5s;" onmouseover="this.style.transform='scale(1.05)'"
                                onmouseout="this.style.transform='scale(1)'" alt="Portrait Photo">
                        </div>
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="card-title fw-semibold mb-0">Portrait Photo</h5>
                                <span class="badge bg-light text-muted rounded-pill px-3">Popular</span>
                            </div>
                            <p class="card-text text-muted small">
                                Cocok untuk personal branding, graduation, dan kebutuhan profesional.
                            </p>
                            <a href="#"
                                class="fw-semibold text-dark text-decoration-none border-bottom border-dark-subtle pb-1 small">
                                Lihat Paket &rarr;
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                        <div class="overflow-hidden" style="height: 256px;">
                            <img src="{{ asset('images/family.jpg') }}" class="card-img-top w-100 h-100 object-fit-cover"
                                style="transition: transform 0.5s;" onmouseover="this.style.transform='scale(1.05)'"
                                onmouseout="this.style.transform='scale(1)'" alt="Family Photo">
                        </div>
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="card-title fw-semibold mb-0">Family Photo</h5>
                                <span class="badge bg-light text-muted rounded-pill px-3">Favorit</span>
                            </div>
                            <p class="card-text text-muted small">
                                Abadikan momen bersama keluarga dengan hasil foto hangat dan natural.
                            </p>
                            <a href="#"
                                class="fw-semibold text-dark text-decoration-none border-bottom border-dark-subtle pb-1 small">
                                Lihat Paket &rarr;
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                        <div class="overflow-hidden" style="height: 256px;">
                            <img src="{{ asset('images/product.jpg') }}" class="card-img-top w-100 h-100 object-fit-cover"
                                style="transition: transform 0.5s;" onmouseover="this.style.transform='scale(1.05)'"
                                onmouseout="this.style.transform='scale(1)'" alt="Product Photo">
                        </div>
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="card-title fw-semibold mb-0">Product Photo</h5>
                                <span class="badge bg-light text-muted rounded-pill px-3">Bisnis</span>
                            </div>
                            <p class="card-text text-muted small">
                                Tingkatkan penjualan dengan foto produk berkualitas tinggi dan profesional.
                            </p>
                            <a href="#"
                                class="fw-semibold text-dark text-decoration-none border-bottom border-dark-subtle pb-1 small">
                                Lihat Paket &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Galeri Section --}}
    <section id="galeri" class="desktop-only py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <span class="badge bg-secondary text-uppercase fw-semibold px-3 py-2 mb-3 rounded-pill"
                    style="font-size: 0.7rem; letter-spacing: 0.15em;">
                    Galeri Foto
                </span>
                <h2 class="display-5 fw-bold text-dark mb-3">Hasil Karya Kami</h2>
                <p class="text-muted mx-auto" style="max-width: 500px;">
                    Lihat hasil karya kami yang memukau dan inspiratif.
                    Setiap sesi foto dipastikan menghasilkan gambar berkualitas tinggi. </p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm overflow-hidden h-100">
                        <div class="position-relative overflow-hidden" style="height: 250px;">
                            <img src="https://via.placeholder.com/400x300" class="card-img-top w-100 h-100 object-fit-cover"
                                style="transition: transform 0.5s;" onmouseover="this.style.transform='scale(1.05)'"
                                onmouseout="this.style.transform='scale(1)'" alt="Galeri Foto">
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm overflow-hidden h-100">
                        <div class="position-relative overflow-hidden" style="height: 250px;">
                            <img src="https://via.placeholder.com/400x300" class="card-img-top w-100 h-100 object-fit-cover"
                                style="transition: transform 0.5s;" onmouseover="this.style.transform='scale(1.05)'"
                                onmouseout="this.style.transform='scale(1)'" alt="Galeri Foto">
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm overflow-hidden h-100">
                        <div class="position-relative overflow-hidden" style="height: 250px;">
                            <img src="https://via.placeholder.com/400x300" class="card-img-top w-100 h-100 object-fit-cover"
                                style="transition: transform 0.5s;" onmouseover="this.style.transform='scale(1.05)'"
                                onmouseout="this.style.transform='scale(1)'" alt="Galeri Foto">
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm overflow-hidden h-100">
                        <div class="position-relative overflow-hidden" style="height: 250px;">
                            <img src="https://via.placeholder.com/400x300" class="card-img-top w-100 h-100 object-fit-cover"
                                style="transition: transform 0.5s;" onmouseover="this.style.transform='scale(1.05)'"
                                onmouseout="this.style.transform='scale(1)'" alt="Galeri Foto">
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm overflow-hidden h-100">
                        <div class="position-relative overflow-hidden" style="height: 250px;">
                            <img src="https://via.placeholder.com/400x300" class="card-img-top w-100 h-100 object-fit-cover"
                                style="transition: transform 0.5s;" onmouseover="this.style.transform='scale(1.05)'"
                                onmouseout="this.style.transform='scale(1)'" alt="Galeri Foto">
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm overflow-hidden h-100">
                        <div class="position-relative overflow-hidden" style="height: 250px;">
                            <img src="https://via.placeholder.com/400x300" class="card-img-top w-100 h-100 object-fit-cover"
                                style="transition: transform 0.5s;" onmouseover="this.style.transform='scale(1.05)'"
                                onmouseout="this.style.transform='scale(1)'" alt="Galeri Foto">
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm overflow-hidden h-100">
                        <div class="position-relative overflow-hidden" style="height: 250px;">
                            <img src="https://via.placeholder.com/400x300" class="card-img-top w-100 h-100 object-fit-cover"
                                style="transition: transform 0.5s;" onmouseover="this.style.transform='scale(1.05)'"
                                onmouseout="this.style.transform='scale(1)'" alt="Galeri Foto">
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm overflow-hidden h-100">
                        <div class="position-relative overflow-hidden" style="height: 250px;">
                            <img src="https://via.placeholder.com/400x300" class="card-img-top w-100 h-100 object-fit-cover"
                                style="transition: transform 0.5s;" onmouseover="this.style.transform='scale(1.05)'"
                                onmouseout="this.style.transform='scale(1)'" alt="Galeri Foto">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Cara Booking -->
    <section id="cara-booking" class="desktop-only py-5 bg-light">
        <div class="container py-4">

            <!-- Heading -->
            <div class="text-center mb-5">
                <span class="badge bg-secondary text-uppercase fw-semibold px-3 py-2 mb-3 rounded-pill"
                    style="font-size: 0.7rem; letter-spacing: 0.15em;">
                    Cara Kerja
                </span>

                <h2 class="display-5 fw-bold text-dark mb-3">
                    Mudah Booking di Yunas Studio
                </h2>

                <p class="text-muted mx-auto" style="max-width: 600px;">
                    Hanya butuh 4 langkah mudah untuk mengabadikan momen berharga anda.
                </p>
            </div>

            <!-- Timeline Card -->
            <div class="bg-white border border-primary rounded-4 shadow-sm p-4 p-md-5">

                <div class="row text-center g-4 position-relative">

                    <!-- Garis -->
                    <div class="position-absolute top-0 start-50 translate-middle-x d-none d-md-block"
                        style="width: 75%; height: 2px; background: #dee2e6; margin-top: 38px; z-index: 0;">
                    </div>

                    <!-- Step 1 -->
                    <div class="col-md-3 position-relative" style="z-index: 1;">
                        <div class="bg-light rounded-4 d-inline-flex align-items-center justify-content-center mb-3"
                            style="width:70px; height:70px;">
                            <i class="bi bi-calendar2-check fs-4"></i>
                        </div>

                        <h6 class="fw-bold mb-2">Pilih Jadwal</h6>

                        <p class="text-muted small mb-0">
                            Pilih paket foto dan tentukan tanggal serta waktu yang Anda inginkan.
                        </p>
                    </div>

                    <!-- Step 2 -->
                    <div class="col-md-3 position-relative" style="z-index: 1;">
                        <div class="bg-light rounded-4 d-inline-flex align-items-center justify-content-center mb-3"
                            style="width:70px; height:70px;">
                            <i class="bi bi-camera fs-4"></i>
                        </div>

                        <h6 class="fw-bold mb-2">Sesi Foto</h6>

                        <p class="text-muted small mb-0">
                            Datang ke studio dan nikmati pengalaman sesi foto yang nyaman.
                        </p>
                    </div>

                    <!-- Step 3 -->
                    <div class="col-md-3 position-relative" style="z-index: 1;">
                        <div class="bg-light rounded-4 d-inline-flex align-items-center justify-content-center mb-3"
                            style="width:70px; height:70px;">
                            <i class="bi bi-check2-square fs-4"></i>
                        </div>

                        <h6 class="fw-bold mb-2">Proses Edit</h6>

                        <p class="text-muted small mb-0">
                            Tim kami akan mengedit foto dengan profesional untuk hasil terbaik.
                        </p>
                    </div>

                    <!-- Step 4 -->
                    <div class="col-md-3 position-relative" style="z-index: 1;">
                        <div class="bg-light rounded-4 d-inline-flex align-items-center justify-content-center mb-3"
                            style="width:70px; height:70px;">
                            <i class="bi bi-cloud-arrow-down fs-4"></i>
                        </div>

                        <h6 class="fw-bold mb-2">Terima Hasil</h6>

                        <p class="text-muted small mb-0">
                            Dapatkan hasil edit melalui Google Drive atau cetak sesuai paket.
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </section>



@endsection