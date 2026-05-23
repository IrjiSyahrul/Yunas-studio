{{--
=====================================================================
BOOKING MODAL
=====================================================================
Alur saat ini : Form → POST /booking → simpan ke transaksis → sukses
Alur nanti : Setelah BookingController sukses → PaymentController
ambil Snap Token → popup Midtrans

Cara pakai:
1. @include('userPage.modal.booking-modal') di index.blade.php
2. Inject $packets dari controller halaman tersebut
3. Tombol trigger: data-bs-toggle="modal" data-bs-target="#bookingModal"
4. Pastikan layout punya:
<meta name="csrf-token" content="{{ csrf_token() }}">

Preselect dari product-detail-modal:
- bookingPreselect(productId, packetId) dipanggil otomatis dari goToBooking()
- Produk & paket langsung terisi, user tinggal isi jadwal di step 2
=====================================================================
--}}

<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">

    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">

            {{-- ── Header ────────────────────────────────────────────── --}}
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div>
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="mdi mdi-camera-outline text-success me-2"></i>Booking Sesi Foto
                    </h5>
                    <p class="text-muted small mb-0 mt-1" id="booking-modal-subtitle">
                        Isi data di bawah untuk melakukan booking sesi foto
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="bookingCloseBtn">
                </button>
            </div>

            {{-- ── Step Indicator ────────────────────────────────────── --}}
            <div class="px-4 pt-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="booking-step active" id="step-indicator-1">
                        <span class="step-number">1</span>
                        <span class="step-label">Data Diri</span>
                    </div>
                    <div class="step-line flex-grow-1"></div>
                    <div class="booking-step" id="step-indicator-2">
                        <span class="step-number">2</span>
                        <span class="step-label">Pilih Paket</span>
                    </div>
                    <div class="step-line flex-grow-1"></div>
                    <div class="booking-step" id="step-indicator-3">
                        <span class="step-number">3</span>
                        <span class="step-label">Konfirmasi</span>
                    </div>
                </div>
            </div>

            {{-- ── Body ──────────────────────────────────────────────── --}}
            <div class="modal-body p-4" id="booking-modal-body">

                {{-- ════════════ STEP 1 : Data Diri ════════════ --}}
                <div id="booking-step-1">

                    {{-- Banner paket terpilih (muncul jika datang dari product detail modal) --}}
                    <div id="booking-preselect-banner" class="d-none mb-4">
                        <div class="alert border-0 rounded-3 py-3 mb-0"
                            style="background: #f0f4ff; border-left: 4px solid #0f3460 !important;">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width: 40px; height: 40px; border-radius: 50%;
                                            background: #0f3460; display: flex; align-items: center;
                                            justify-content: center; flex-shrink: 0;">
                                    <i class="mdi mdi-check" style="color: white; font-size: 20px;"></i>
                                </div>
                                <div>
                                    <p class="mb-0 fw-semibold" style="color: #0f3460; font-size: 14px;">
                                        Paket sudah dipilih
                                    </p>
                                    <p class="mb-0 text-muted small" id="banner-packet-info">-</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                Nama Lengkap <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-lg" id="b_customer_name"
                                placeholder="Masukkan nama lengkap Anda" autocomplete="name">
                            <div class="invalid-feedback d-none" id="err_customer_name">
                                Nama tidak boleh kosong.
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                Nomor WhatsApp <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="mdi mdi-whatsapp text-success"></i>
                                </span>
                                <input type="tel" class="form-control border-start-0" id="b_phone_number"
                                    placeholder="08xxxxxxxxxx" autocomplete="tel">
                            </div>
                            <div class="invalid-feedback d-none" id="err_phone_number">
                                Nomor tidak valid. Gunakan format 08xxxxxxxxxx.
                            </div>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button class="btn btn-success btn-lg rounded-pill" onclick="bookingNextStep(2)">
                            Lanjut Pilih Paket <i class="mdi mdi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
                {{-- /STEP 1 --}}

                {{-- ════════════ STEP 2 : Pilih Paket & Jadwal ════════════ --}}
                <div id="booking-step-2" class="d-none">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Produk <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-lg" id="b_product_id"
                                onchange="bookingUpdatePackets()">
                                <option value="" disabled selected>-- Pilih Produk --</option>
                                @foreach($packets as $productName => $packetGroup)
                                    <option value="{{ $packetGroup->first()->product_id }}"
                                        data-product-name="{{ $productName }}">
                                        {{ $productName }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-none" id="err_product">
                                Pilih produk terlebih dahulu.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Paket <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-lg" id="b_packet_id" disabled
                                onchange="bookingUpdatePrice()">
                                <option value="" data-price="0" disabled selected>-- Pilih Paket --</option>
                            </select>
                            <div class="invalid-feedback d-none" id="err_packet">
                                Pilih paket terlebih dahulu.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Tanggal Sesi <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control form-control-lg" id="b_session_date">
                            <div class="invalid-feedback d-none" id="err_date">
                                Pilih tanggal sesi.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Waktu Sesi <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-lg" id="b_session_time">
                                <option value="" disabled selected>-- Pilih Waktu --</option>
                                <option value="09:00">09:00 WIB</option>
                                <option value="10:00">10:00 WIB</option>
                                <option value="11:00">11:00 WIB</option>
                                <option value="12:00">12:00 WIB</option>
                                <option value="13:00">13:00 WIB</option>
                                <option value="14:00">14:00 WIB</option>
                                <option value="15:00">15:00 WIB</option>
                                <option value="16:00">16:00 WIB</option>
                            </select>
                            <div class="invalid-feedback d-none" id="err_time">
                                Pilih waktu sesi.
                            </div>
                        </div>

                        {{-- Ringkasan harga muncul setelah paket dipilih --}}
                        <div class="col-12 d-none" id="booking-price-summary">
                            <div class="alert alert-success border-0 rounded-3 mb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-0 small text-muted">Total Pembayaran</p>
                                        <p class="mb-0 fw-bold fs-5" id="booking-price-display">Rp 0</p>
                                    </div>
                                    <i class="mdi mdi-tag-check-outline fs-2 text-success"></i>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-outline-secondary btn-lg rounded-pill px-4" onclick="bookingNextStep(1)">
                            <i class="mdi mdi-arrow-left me-1"></i> Kembali
                        </button>
                        <button class="btn btn-success btn-lg rounded-pill flex-grow-1" onclick="bookingNextStep(3)">
                            Lanjut Konfirmasi <i class="mdi mdi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
                {{-- /STEP 2 --}}

                {{-- ════════════ STEP 3 : Konfirmasi & Simpan ════════════ --}}
                <div id="booking-step-3" class="d-none">

                    {{-- Ringkasan pesanan --}}
                    <div class="card border-0 bg-light rounded-3 mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">
                                <i class="mdi mdi-clipboard-check-outline text-success me-1"></i>
                                Ringkasan Pesanan
                            </h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-muted" style="width:40%">Nama</td>
                                        <td class="fw-semibold" id="confirm_name">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">WhatsApp</td>
                                        <td class="fw-semibold" id="confirm_phone">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Produk</td>
                                        <td class="fw-semibold" id="confirm_product">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Paket</td>
                                        <td class="fw-semibold" id="confirm_packet">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Tanggal</td>
                                        <td class="fw-semibold" id="confirm_date">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Waktu</td>
                                        <td class="fw-semibold" id="confirm_time">-</td>
                                    </tr>
                                    <tr class="border-top">
                                        <td class="fw-bold pt-2">Total</td>
                                        <td class="fw-bold text-success fs-5 pt-2" id="confirm_total">-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Info pembayaran --}}
                    <div class="alert alert-info border-0 rounded-3 py-2 mb-3">
                        <p class="mb-0 small">
                            <i class="mdi mdi-information-outline me-1"></i>
                            Setelah booking dikonfirmasi, tim kami akan menghubungi Anda via WhatsApp
                            untuk info pembayaran lebih lanjut.
                        </p>
                    </div>

                    {{-- Loading spinner --}}
                    <div class="d-none text-center py-3" id="booking-loading">
                        <div class="spinner-border text-success" role="status"></div>
                        <p class="mt-2 text-muted small">Menyimpan booking Anda...</p>
                    </div>

                    {{-- Tombol aksi --}}
                    <div class="d-flex gap-2" id="booking-action-btns">
                        <button class="btn btn-outline-secondary btn-lg rounded-pill px-4" onclick="bookingNextStep(2)"
                            id="booking-back-btn">
                            <i class="mdi mdi-arrow-left me-1"></i> Kembali
                        </button>
                        <button class="btn btn-success btn-lg rounded-pill flex-grow-1" onclick="bookingSubmit()"
                            id="booking-pay-btn">
                            <i class="mdi mdi-check-circle-outline me-1"></i> Konfirmasi Booking
                        </button>
                    </div>

                </div>
                {{-- /STEP 3 --}}

            </div>
            {{-- /modal-body --}}

        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════
CSS
═══════════════════════════════════════════════════════════════════ --}}
<style>
    .booking-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }

    .booking-step .step-number {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #e9ecef;
        color: #6c757d;
        font-weight: 600;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all .3s ease;
    }

    .booking-step .step-label {
        font-size: 11px;
        color: #6c757d;
        white-space: nowrap;
        transition: all .3s ease;
    }

    .booking-step.active .step-number {
        background: #198754;
        color: #fff;
    }

    .booking-step.active .step-label {
        color: #198754;
        font-weight: 600;
    }

    .booking-step.done .step-number {
        background: #d1e7dd;
        color: #198754;
    }

    .step-line {
        height: 2px;
        background: #e9ecef;
        margin-bottom: 18px;
        transition: background .3s ease;
    }

    .step-line.done {
        background: #198754;
    }

    @keyframes stepFadeIn {
        from {
            opacity: 0;
            transform: translateY(8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .booking-step-fade {
        animation: stepFadeIn .25s ease;
    }
</style>

{{-- Load Midtrans Snap.js --}}
<script src="{{ config('midtrans.snap_url') }}" data-client-key="{{ config('midtrans.client_key') }}"></script>

{{-- ═══════════════════════════════════════════════════════════════════
JAVASCRIPT
═══════════════════════════════════════════════════════════════════ --}}
<script>
    (function () {
        'use strict';

        const productPackets = {!! json_encode($packets) !!};

        const formatRp = (n) =>
            new Intl.NumberFormat('id-ID', {
                style: 'currency', currency: 'IDR', minimumFractionDigits: 0
            }).format(n);

        const formatDate = (s) => s
            ? new Intl.DateTimeFormat('id-ID', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
            }).format(new Date(s))
            : '-';

        // Set tanggal minimum = hari ini
        document.getElementById('b_session_date')
            .setAttribute('min', new Date().toISOString().split('T')[0]);

        // ── Preselect produk & paket dari product-detail-modal ──────────
        // Dipanggil otomatis oleh goToBooking() di product-detail-modal
        window.bookingPreselect = function (productId, packetId) {
            const productSel = document.getElementById('b_product_id');

            // Set nilai produk
            productSel.value = productId;

            // Trigger update dropdown paket
            bookingUpdatePackets();

            // Tunggu dropdown paket terisi, lalu set paket
            setTimeout(() => {
                const packetSel = document.getElementById('b_packet_id');
                packetSel.value = packetId;

                // Trigger update harga
                bookingUpdatePrice();

                // Tampilkan banner paket terpilih di step 1
                const productName = productSel.options[productSel.selectedIndex]?.dataset.productName ?? '';
                const packetName = packetSel.options[packetSel.selectedIndex]?.dataset.name ?? '';
                const packetPrice = parseFloat(packetSel.options[packetSel.selectedIndex]?.dataset.price) || 0;

                const banner = document.getElementById('booking-preselect-banner');
                const info = document.getElementById('banner-packet-info');

                if (banner && info && packetName) {
                    info.textContent = `${productName} — ${packetName} · ${formatRp(packetPrice)}`;
                    banner.classList.remove('d-none');
                }
            }, 50);
        };

        // ── Step navigation ──────────────────────────────────────────────
        window.bookingNextStep = function (step) {
            if (step > 1 && !validateStep(step - 1)) return;

            [1, 2, 3].forEach(i => {
                document.getElementById(`booking-step-${i}`).classList.add('d-none');
                document.getElementById(`step-indicator-${i}`).classList.remove('active', 'done');
            });

            document.querySelectorAll('.step-line').forEach((line, idx) => {
                line.classList.toggle('done', idx < step - 1);
            });

            for (let i = 1; i < step; i++) {
                document.getElementById(`step-indicator-${i}`).classList.add('done');
            }
            document.getElementById(`step-indicator-${step}`).classList.add('active');

            const el = document.getElementById(`booking-step-${step}`);
            el.classList.remove('d-none');
            el.classList.add('booking-step-fade');
            setTimeout(() => el.classList.remove('booking-step-fade'), 300);

            if (step === 3) fillConfirmation();
        };

        // ── Validasi per step ────────────────────────────────────────────
        function validateStep(step) {
            let valid = true;

            const ok = (inputId, errId) => {
                document.getElementById(inputId)?.classList.remove('is-invalid');
                document.getElementById(errId)?.classList.add('d-none');
            };
            const err = (inputId, errId) => {
                document.getElementById(inputId)?.classList.add('is-invalid');
                document.getElementById(errId)?.classList.remove('d-none');
                valid = false;
            };

            if (step === 1) {
                ok('b_customer_name', 'err_customer_name');
                ok('b_phone_number', 'err_phone_number');

                if (!document.getElementById('b_customer_name').value.trim())
                    err('b_customer_name', 'err_customer_name');

                if (!/^08\d{8,12}$/.test(document.getElementById('b_phone_number').value.trim()))
                    err('b_phone_number', 'err_phone_number');
            }

            if (step === 2) {
                ['b_product_id/err_product', 'b_packet_id/err_packet',
                    'b_session_date/err_date', 'b_session_time/err_time'].forEach(pair => {
                        const [iId, eId] = pair.split('/');
                        ok(iId, eId);
                        if (!document.getElementById(iId).value) err(iId, eId);
                    });
            }

            return valid;
        }

        // ── Dropdown paket ───────────────────────────────────────────────
        window.bookingUpdatePackets = function () {
            const productSel = document.getElementById('b_product_id');
            const packetSel = document.getElementById('b_packet_id');

            packetSel.innerHTML = '<option value="" data-price="0" disabled selected>-- Pilih Paket --</option>';
            packetSel.disabled = true;
            document.getElementById('booking-price-summary').classList.add('d-none');

            const name = productSel.options[productSel.selectedIndex]?.dataset.productName;
            if (!name || !productPackets[name]) return;

            productPackets[name].forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.dataset.price = p.price;
                opt.dataset.name = p.name;
                opt.textContent = `${p.name} — ${formatRp(p.price)}`;
                packetSel.appendChild(opt);
            });

            packetSel.disabled = false;
            bookingUpdatePrice();
        };

        window.bookingUpdatePrice = function () {
            const packetSel = document.getElementById('b_packet_id');
            const price = parseFloat(packetSel.options[packetSel.selectedIndex]?.dataset.price) || 0;
            const summary = document.getElementById('booking-price-summary');

            if (price > 0) {
                document.getElementById('booking-price-display').textContent = formatRp(price);
                summary.classList.remove('d-none');
            } else {
                summary.classList.add('d-none');
            }
        };

        // ── Isi ringkasan di step 3 ──────────────────────────────────────
        function fillConfirmation() {
            const productSel = document.getElementById('b_product_id');
            const packetSel = document.getElementById('b_packet_id');
            const price = parseFloat(packetSel.options[packetSel.selectedIndex]?.dataset.price) || 0;

            document.getElementById('confirm_name').textContent = document.getElementById('b_customer_name').value;
            document.getElementById('confirm_phone').textContent = document.getElementById('b_phone_number').value;
            document.getElementById('confirm_product').textContent = productSel.options[productSel.selectedIndex]?.dataset.productName || '-';
            document.getElementById('confirm_packet').textContent = packetSel.options[packetSel.selectedIndex]?.dataset.name || '-';
            document.getElementById('confirm_date').textContent = formatDate(document.getElementById('b_session_date').value);
            document.getElementById('confirm_time').textContent = document.getElementById('b_session_time').value + ' WIB';
            document.getElementById('confirm_total').textContent = formatRp(price);
        }

        // ── Submit booking ───────────────────────────────────────────────
        window.bookingSubmit = async function () {
            const loadingEl = document.getElementById('booking-loading');
            const actionEl = document.getElementById('booking-action-btns');
            const closeBtn = document.getElementById('bookingCloseBtn');

            loadingEl.classList.remove('d-none');
            actionEl.classList.add('d-none');
            closeBtn.disabled = true;

            const payload = {
                customer_name: document.getElementById('b_customer_name').value.trim(),
                phone_number: document.getElementById('b_phone_number').value.trim(),
                product_id: document.getElementById('b_product_id').value,
                packet_id: document.getElementById('b_packet_id').value,
                session_date: document.getElementById('b_session_date').value,
                session_time: document.getElementById('b_session_time').value,
            };

            try {
                const snapRes = await fetch('{{ route("booking.snap-token") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload),
                });

                const snapData = await snapRes.json();

                if (!snapRes.ok) {
                    const errMsg = snapData.errors
                        ? Object.values(snapData.errors).flat().join('\n')
                        : (snapData.message || 'Terjadi kesalahan.');
                    throw new Error(errMsg);
                }

                /* =========================================================
       TUTUP MODAL DENGAN AMAN
    ========================================================= */
                const bookingModalEl =
                    document.getElementById('bookingModal');

                const bsModal =
                    bootstrap.Modal.getInstance(bookingModalEl);

                window._bookingGoingToSnap = true;

                /* HILANGKAN FOCUS DARI ELEMENT DI DALAM MODAL */
                if (document.activeElement) {
                    document.activeElement.blur();
                }

                /* PINDAHKAN FOCUS KE BODY */
                document.body.focus();

                /* BARU HIDE MODAL */
                bsModal.hide();

                snap.pay(snapData.snap_token, {
                    onSuccess: (r) => {
                        window._bookingGoingToSnap = false;
                        window.location.href = `{{ route("payment.success") }}?order_id=${r.order_id}`;
                    },
                    onPending: (r) => {
                        window._bookingGoingToSnap = false;
                        window.location.href = `{{ route("payment.success") }}?order_id=${r.order_id}&status=pending`;
                    },
                    onError: (r) => {
                        window._bookingGoingToSnap = false;
                        window.location.href = `{{ route("payment.failed") }}?order_id=${r.order_id}`;
                    },
                    onClose: () => {
                        window._bookingGoingToSnap = false;
                        bsModal.show();
                        loadingEl.classList.add('d-none');
                        actionEl.classList.remove('d-none');
                        closeBtn.disabled = false;
                    },
                });

            } catch (error) {
                loadingEl.classList.add('d-none');
                actionEl.classList.remove('d-none');
                closeBtn.disabled = false;
                showInlineError(error.message);
            }
        };

        // ── Tampilkan error inline ───────────────────────────────────────
        function showInlineError(message) {
            const existing = document.getElementById('booking-inline-error');
            if (existing) existing.remove();

            const div = document.createElement('div');
            div.id = 'booking-inline-error';
            div.className = 'alert alert-danger rounded-3 mt-3 mb-0';
            div.innerHTML = `<i class="mdi mdi-alert-circle-outline me-1"></i>${message}`;

            document.getElementById('booking-action-btns').insertAdjacentElement('beforebegin', div);
        }

        // ── Reset modal saat ditutup ─────────────────────────────────────
        /* =========================================================
       RESET MODAL SAAT DITUTUP
    ========================================================= */
        document.getElementById('bookingModal')
            .addEventListener('hidden.bs.modal', function () {

                /* JIKA MENUJU MIDTRANS JANGAN RESET */
                if (window._bookingGoingToSnap) {

                    /* FIX ACCESSIBILITY */
                    if (document.activeElement) {
                        document.activeElement.blur();
                    }

                    document.body.focus();

                    return;
                }

                /* =====================================================
                   RESET FIELD
                ===================================================== */
                ['b_customer_name', 'b_phone_number', 'b_session_date']
                    .forEach(id => {

                        const el = document.getElementById(id);

                        if (el) {
                            el.value = '';
                            el.classList.remove('is-invalid');
                        }
                    });

                ['b_product_id', 'b_session_time']
                    .forEach(id => {

                        const el = document.getElementById(id);

                        if (el) {
                            el.selectedIndex = 0;
                            el.classList.remove('is-invalid');
                        }
                    });

                /* =====================================================
                   RESET PACKET
                ===================================================== */
                const packetSelect =
                    document.getElementById('b_packet_id');

                if (packetSelect) {

                    packetSelect.innerHTML =
                        '<option value="" data-price="0" disabled selected>-- Pilih Paket --</option>';

                    packetSelect.disabled = true;
                }

                /* =====================================================
                   HIDE PRESELECT BANNER
                ===================================================== */
                const banner =
                    document.getElementById('booking-preselect-banner');

                if (banner) {
                    banner.classList.add('d-none');
                }

                /* =====================================================
                   HIDE PRICE SUMMARY
                ===================================================== */
                const priceSummary =
                    document.getElementById('booking-price-summary');

                if (priceSummary) {
                    priceSummary.classList.add('d-none');
                }

                /* =====================================================
                   RESET LOADING
                ===================================================== */
                const loadingEl =
                    document.getElementById('booking-loading');

                if (loadingEl) {
                    loadingEl.classList.add('d-none');
                }

                /* =====================================================
                   SHOW ACTION BUTTON
                ===================================================== */
                const actionEl =
                    document.getElementById('booking-action-btns');

                if (actionEl) {
                    actionEl.classList.remove('d-none');
                }

                /* =====================================================
                   ENABLE CLOSE BUTTON
                ===================================================== */
                const closeBtn =
                    document.getElementById('bookingCloseBtn');

                if (closeBtn) {
                    closeBtn.disabled = false;
                }

                /* =====================================================
                   REMOVE INLINE ERROR
                ===================================================== */
                const inlineErr =
                    document.getElementById('booking-inline-error');

                if (inlineErr) {
                    inlineErr.remove();
                }

                /* =====================================================
                   KEMBALI KE STEP 1
                ===================================================== */
                if (typeof bookingNextStep === 'function') {
                    bookingNextStep(1);
                }

                /* =====================================================
                   FIX ACCESSIBILITY WARNING
                ===================================================== */
                if (document.activeElement) {
                    document.activeElement.blur();
                }

                document.body.focus();
            });

    })();
</script>

<script>

    /* =========================================================
       FIX FOCUS SAAT MODAL DIBUKA
    ========================================================= */
    document.getElementById('bookingModal')
        .addEventListener('shown.bs.modal', function () {

            const closeBtn =
                document.getElementById('bookingCloseBtn');

            if (closeBtn) {
                closeBtn.blur();
            }
        });

</script>