{{-- ═══════════════════════════════════════════════════════════════════════
     PRODUCT DETAIL MODAL — Custom Overlay (tidak pakai Bootstrap Modal)
     Dipanggil via: showProductDetail(productId)
═══════════════════════════════════════════════════════════════════════ --}}

{{-- Overlay --}}
<div id="productDetailOverlay"
     onclick="if(event.target===this) closeProductDetail()"
     style="
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 99999;
        align-items: center;
        justify-content: center;
        padding: 16px;
     ">

    {{-- Dialog --}}
    <div style="
            background: #fff;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: pdSlideUp .25s ease;
         ">

        {{-- Header --}}
        <div style="padding: 20px 20px 12px; border-bottom: 1px solid #f0f0f0; flex-shrink: 0;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div style="font-size:17px; font-weight:700; color:#212529;" id="pdProductName">
                        Detail Produk
                    </div>
                    <div style="font-size:13px; color:#6c757d; margin-top:2px;">
                        Pilih paket yang sesuai kebutuhan Anda
                    </div>
                </div>
                <button onclick="closeProductDetail()"
                        style="background:none; border:none; font-size:20px; color:#6c757d; cursor:pointer; padding:0; line-height:1;">
                    ✕
                </button>
            </div>
        </div>

        {{-- Body --}}
        <div id="pdBody"
             style="overflow-y: auto; padding: 16px 20px; flex: 1;">
            {{-- Diisi JavaScript --}}
        </div>

        {{-- Footer --}}
        <div style="padding: 12px 20px 20px; border-top: 1px solid #f0f0f0; flex-shrink:0; display:flex; gap:10px;">
            <button onclick="closeProductDetail()"
                    style="
                        border: 1px solid #dee2e6;
                        background: #fff;
                        color: #6c757d;
                        border-radius: 50px;
                        padding: 10px 20px;
                        font-size: 14px;
                        cursor: pointer;
                    ">
                Tutup
            </button>
            <button id="pdBookBtn"
                    onclick="goToBooking()"
                    disabled
                    style="
                        flex: 1;
                        background: #198754;
                        color: #fff;
                        border: none;
                        border-radius: 50px;
                        padding: 10px 20px;
                        font-size: 14px;
                        font-weight: 600;
                        cursor: pointer;
                        opacity: 0.6;
                    ">
                Pilih paket dulu
            </button>
        </div>

    </div>
</div>

{{-- ═══ CSS ═══ --}}
<style>
    @keyframes pdSlideUp {
        from { transform: translateY(30px); opacity: 0; }
        to   { transform: translateY(0);    opacity: 1; }
    }
    .pd-packet-card {
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 14px 16px;
        cursor: pointer;
        transition: all .2s ease;
        position: relative;
        margin-bottom: 12px;
    }
    .pd-packet-card:hover {
        border-color: #198754;
        background: #f8fff9;
    }
    .pd-packet-card.selected {
        border-color: #198754;
        background: #f0fdf4;
    }
    .pd-packet-card.selected::after {
        content: '✓';
        position: absolute;
        top: 12px; right: 14px;
        color: #198754;
        font-weight: 700;
        font-size: 16px;
    }
    .pd-price-badge {
        background: #198754;
        color: white;
        border-radius: 20px;
        padding: 2px 12px;
        font-size: 13px;
        font-weight: 600;
    }
    .pd-feature-list {
        list-style: none;
        padding: 0; margin: 8px 0 0;
    }
    .pd-feature-list li {
        font-size: 12px;
        color: #6c757d;
        padding: 2px 0;
    }
    .pd-feature-list li::before {
        content: '✓ ';
        color: #198754;
        font-weight: 600;
    }
</style>

{{-- ═══ JAVASCRIPT ═══ --}}
<script>
(function () {
    'use strict';

    const allPackets = {!! json_encode($packets) !!};

    let selectedProductId   = null;
    let selectedProductName = null;
    let selectedPacketId    = null;

    const formatRp = (n) =>
        new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', minimumFractionDigits:0 }).format(n);

        window.showProductDetail = function (productId) {
    console.log('showProductDetail dipanggil dengan id:', productId);
    console.log('allPackets:', allPackets);
    // ── Buka overlay ────────────────────────────────────────────────────
    window.showProductDetail = function (productId) {

        // Cari paket berdasarkan product_id
        let productName = null;
        let packetList  = null;

        for (const [name, group] of Object.entries(allPackets)) {
            if (String(group[0].product_id) === String(productId)) {
                productName = name;
                packetList  = group;
                break;
            }
        }

        if (!packetList) {
            alert('Paket tidak ditemukan.');
            return;
        }

        selectedProductId   = productId;
        selectedProductName = productName;
        selectedPacketId    = null;

        // Set judul
        document.getElementById('pdProductName').textContent = productName;

        // Reset tombol
        const bookBtn = document.getElementById('pdBookBtn');
        bookBtn.disabled = true;
        bookBtn.style.opacity = '0.6';
        bookBtn.textContent = 'Pilih paket dulu';

        // Render paket
        const body = document.getElementById('pdBody');
        body.innerHTML = '';

        if (packetList.length === 0) {
            body.innerHTML = '<p style="text-align:center;color:#6c757d;padding:20px 0;">Belum ada paket tersedia.</p>';
        } else {
            packetList.forEach(packet => {
                const card = document.createElement('div');
                card.className = 'pd-packet-card';
                card.dataset.id   = packet.id;
                card.dataset.name = packet.name;

                const features = packet.description
                    ? `<ul class="pd-feature-list">
                           ${packet.description.split('\n').filter(l => l.trim()).map(l => `<li>${l.trim()}</li>`).join('')}
                       </ul>`
                    : '';

                card.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div style="font-weight:700; font-size:15px;">${packet.name}</div>
                        <span class="pd-price-badge ms-2">${formatRp(packet.price)}</span>
                    </div>
                    ${features}
                `;

                card.addEventListener('click', function () {
                    body.querySelectorAll('.pd-packet-card').forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedPacketId = this.dataset.id;

                    bookBtn.disabled = false;
                    bookBtn.style.opacity = '1';
                    bookBtn.textContent = `Booking — ${this.dataset.name}`;
                });

                body.appendChild(card);
            });
        }

        // Tampilkan overlay
        const overlay = document.getElementById('productDetailOverlay');
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    // ── Tutup overlay ───────────────────────────────────────────────────
    window.closeProductDetail = function () {
        document.getElementById('productDetailOverlay').style.display = 'none';
        document.body.style.overflow = '';
    };

    // Tutup dengan tombol Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeProductDetail();
    });

    // ── Pindah ke modal booking ─────────────────────────────────────────
    window.goToBooking = function () {
        if (!selectedProductId) return;

        closeProductDetail();

        // Tunggu overlay tutup, baru buka booking modal
        setTimeout(() => {
            const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
            bookingModal.show();

            document.getElementById('bookingModal').addEventListener('shown.bs.modal', function onShown() {
                this.removeEventListener('shown.bs.modal', onShown);

                const productSelect = document.getElementById('b_product_id');
                productSelect.value = selectedProductId;
                productSelect.dispatchEvent(new Event('change'));

                if (selectedPacketId) {
                    setTimeout(() => {
                        const packetSelect = document.getElementById('b_packet_id');
                        packetSelect.value = selectedPacketId;
                        packetSelect.dispatchEvent(new Event('change'));
                    }, 100);
                }

                bookingNextStep(2);
            });
        }, 100);
    };
        }
})();
</script>