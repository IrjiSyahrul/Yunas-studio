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