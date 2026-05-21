{{-- =====================================================================
     MOBILE MENU — Kategori Paket (dinamis dari database)
     
     Alur klik:
     1. User klik produk (misal Wedding)
     2. Muncul modal detail → daftar paket + harga (Gold, Platinum, Silver...)
     3. User klik "Booking Sekarang" → modal booking terbuka, produk & paket
        sudah terpreselect otomatis
     ===================================================================== --}}

{{-- ── Grid Produk ─────────────────────────────────────────────────────── --}}
<div class="mobile-menu-wrap">

    <div class="mobile-section-title">
        Kategori Paket
    </div>

    <div class="mobile-menu-grid">
        <div class="row g-3 text-center">

            @foreach($packets as $productName => $packetGroup)
                @php $product = $packetGroup->first()->product; @endphp

                <div class="col-3">
                    <a href="#"
                       class="mobile-menu-item"
                       onclick="showProductDetail('{{ $product->id }}'); return false;"
                       title="{{ $productName }}">

                        <div class="mobile-menu-icon">
                            @if(!empty($product->icon))
                                <img src="{{ asset($product->icon) }}" width="28" alt="{{ $productName }}">
                            @else
                                <i class="mdi mdi-camera" style="font-size:28px; color:#198754;"></i>
                            @endif
                        </div>

                        <small class="d-block text-truncate" style="max-width:70px; margin:0 auto;">
                            {{ $productName }}
                        </small>

                    </a>
                </div>
            @endforeach

        </div>
    </div>

</div>

{{-- ── Modal Detail Produk ──────────────────────────────────────────────── --}}
<div class="modal fade"
     id="productDetailModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow-lg">

            {{-- Header --}}
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="productDetailTitle">
                        <i class="mdi mdi-camera-outline text-success me-2"></i>
                        <span id="productDetailName">Detail Produk</span>
                    </h5>
                    <p class="text-muted small mb-0 mt-1">Pilih paket yang sesuai kebutuhan Anda</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            {{-- Body --}}
            <div class="modal-body px-4 py-3" id="productDetailBody">
                {{-- Diisi oleh JavaScript --}}
            </div>

            {{-- Footer --}}
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button"
                        class="btn btn-outline-secondary rounded-pill px-4"
                        data-bs-dismiss="modal">
                    Tutup
                </button>
                <button type="button"
                        class="btn btn-success rounded-pill flex-grow-1"
                        id="productDetailBookBtn"
                        onclick="goToBooking()">
                    <i class="mdi mdi-calendar-check-outline me-1"></i>
                    Booking Sekarang
                </button>
            </div>

        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════
     CSS
═══════════════════════════════════════════════════════════════════════ --}}
<style>
    /* Kartu paket di modal detail */
    .packet-card {
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 14px 16px;
        cursor: pointer;
        transition: all .2s ease;
        position: relative;
    }
    .packet-card:hover {
        border-color: #198754;
        background: #f8fff9;
    }
    .packet-card.selected {
        border-color: #198754;
        background: #f0fdf4;
    }
    .packet-card.selected::after {
        content: '\F012C'; /* mdi-check-circle */
        font-family: 'Material Design Icons';
        position: absolute;
        top: 10px; right: 12px;
        color: #198754;
        font-size: 20px;
    }
    .packet-price-badge {
        background: #198754;
        color: white;
        border-radius: 20px;
        padding: 2px 12px;
        font-size: 13px;
        font-weight: 600;
    }
    .packet-feature-list {
        list-style: none;
        padding: 0; margin: 8px 0 0;
    }
    .packet-feature-list li {
        font-size: 12px;
        color: #6c757d;
        padding: 2px 0;
    }
    .packet-feature-list li::before {
        content: '✓ ';
        color: #198754;
        font-weight: 600;
    }
</style>

