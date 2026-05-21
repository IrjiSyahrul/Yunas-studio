<nav id="main-navbar" class="navbar navbar-expand-md bg-white border-bottom px-4 px-md-5 sticky-top"
    style="height: 56px;">

    <div class="container-fluid px-0">
        {{-- Logo --}}
        <a href="{{ url('/user') }}" class="navbar-brand fw-bold text-uppercase text-dark"
            style="font-size: 1.25 rem; letter-spacing: 0.15em;">
            Yunas Studio
        </a>

        {{-- Toggler untuk layar kecil (opsional, bisa dihapus kalau tidak mau hamburger) --}}
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
            data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

        {{-- Menu --}}
        <div class="collapse navbar-collapse" id="navMenu">

            {{-- Menu Tengah --}}
            <ul class="navbar-nav mx-auto gap-md-4">
                <li class="nav-item">
                    <a href="#paket"
                        class="nav-link text-dark {{ request()->is('paket') ? 'fw-semibold' : 'fw-normal' }}">
                        Paket
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#galeri" class="nav-link text-dark">
                        Galeri
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#cara-booking"
                        class="nav-link text-dark {{ request()->is('cara-booking') ? 'fw-semibold' : 'fw-normal' }}">
                        Cara Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a href="https://wa.me/6281234567890?text=Halo%20Admin%20Yunas%20Studio,%20saya%20ingin%20bertanya%20mengenai%20booking."
                        target="_blank" class="nav-link text-dark">
                        Kontak
                    </a>
                </li>
            </ul>

            {{-- Tombol CTA --}}
            <a class="btn btn-dark rounded-2 px-4 py-2 ms-md-3" data-bs-toggle="modal" data-bs-target="#bookingModal"
                style="font-size: 0.875rem;">
                Booking Sekarang
            </a>

        </div>
    </div>
</nav>