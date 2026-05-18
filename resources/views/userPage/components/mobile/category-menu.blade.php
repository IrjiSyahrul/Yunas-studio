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

{{-- ═══════════════════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════════════════ --}}
<script>
(function () {
    'use strict';

    // Data semua produk & paket dari Blade (sama dengan modal booking)
    const allPackets = {!! json_encode($packets) !!};

    // State: produk & paket yang sedang dipilih user
    let selectedProductId   = null;
    let selectedProductName = null;
    let selectedPacketId    = null;

    const formatRp = (n) =>
        new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', minimumFractionDigits:0 }).format(n);

    // ── Buka modal detail produk ─────────────────────────────────────
    window.showProductDetail = function (productId) {
        // Cari produk dari data yang sudah ada
        let productName = null;
        let packetList  = null;

        for (const [name, group] of Object.entries(allPackets)) {
            if (String(group[0].product_id) === String(productId)) {
                productName = name;
                packetList  = group;
                break;
            }
        }

        if (!packetList) return;

        // Simpan state
        selectedProductId   = productId;
        selectedProductName = productName;
        selectedPacketId    = null;

        // Set judul modal
        document.getElementById('productDetailName').textContent = productName;

        // Reset tombol booking (disable dulu sampai paket dipilih)
        const bookBtn = document.getElementById('productDetailBookBtn');
        bookBtn.disabled = true;
        bookBtn.innerHTML = '<i class="mdi mdi-calendar-check-outline me-1"></i> Pilih paket dulu';

        // Render daftar paket
        const body = document.getElementById('productDetailBody');
        body.innerHTML = '';

        if (packetList.length === 0) {
            body.innerHTML = '<p class="text-muted text-center py-3">Belum ada paket tersedia.</p>';
        } else {
            packetList.forEach(packet => {
                const card = document.createElement('div');
                card.className   = 'packet-card mb-3';
                card.dataset.id  = packet.id;
                card.dataset.name = packet.name;
                card.innerHTML   = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="fw-bold" style="font-size:15px">${packet.name}</div>
                        <span class="packet-price-badge ms-2 flex-shrink-0">${formatRp(packet.price)}</span>
                    </div>
                    ${packet.description
                        ? `<ul class="packet-feature-list mt-2">
                               ${packet.description.split('\n').filter(l => l.trim()).map(l => `<li>${l.trim()}</li>`).join('')}
                           </ul>`
                        : ''}
                `;

                card.addEventListener('click', function () {
                    // Deselect semua, select yang diklik
                    body.querySelectorAll('.packet-card').forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');

                    selectedPacketId = this.dataset.id;

                    // Aktifkan tombol booking
                    bookBtn.disabled = false;
                    bookBtn.innerHTML = `<i class="mdi mdi-calendar-check-outline me-1"></i> Booking — ${this.dataset.name}`;
                });

                body.appendChild(card);
            });
        }

        // Buka modal
        const modal = new bootstrap.Modal(document.getElementById('productDetailModal'));
        modal.show();
    };

    // ── Pindah ke modal booking dengan produk & paket terpreselect ───
    window.goToBooking = function () {
        if (!selectedProductId) return;

        // Tutup modal detail
        bootstrap.Modal.getInstance(document.getElementById('productDetailModal')).hide();

        // Tunggu modal detail selesai tutup, baru buka modal booking
        document.getElementById('productDetailModal').addEventListener('hidden.bs.modal', function handler() {
            this.removeEventListener('hidden.bs.modal', handler);

            // Buka modal booking
            const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
            bookingModal.show();

            // Setelah booking modal terbuka, isi dropdown
            document.getElementById('bookingModal').addEventListener('shown.bs.modal', function onShown() {
                this.removeEventListener('shown.bs.modal', onShown);

                // Set produk
                const productSelect = document.getElementById('b_product_id');
                productSelect.value = selectedProductId;
                productSelect.dispatchEvent(new Event('change')); // isi dropdown paket

                // Set paket setelah dropdown paket terisi (tunggu sebentar)
                if (selectedPacketId) {
                    setTimeout(() => {
                        const packetSelect = document.getElementById('b_packet_id');
                        packetSelect.value = selectedPacketId;
                        packetSelect.dispatchEvent(new Event('change')); // update tampilan harga
                    }, 50);
                }

                // Langsung loncat ke step 2 (produk & paket sudah dipilih)
                bookingNextStep(2);
            });
        });
    };

})();
</script>