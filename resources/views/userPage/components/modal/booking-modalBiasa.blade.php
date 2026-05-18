{{--
    =====================================================================
    BOOKING MODAL — Terintegrasi Midtrans Snap
    =====================================================================
    Cara penggunaan:
    1. Include file ini di halaman utama (landing page) dengan @include
    2. Pastikan $packets tersedia di view (inject dari controller)
    3. Tambahkan tombol trigger: data-bs-toggle="modal" data-bs-target="#bookingModal"
    =====================================================================
--}}

<div class="modal fade"
     id="bookingModal"
     tabindex="-1"
     aria-hidden="true"
     data-bs-backdrop="static">

    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">

            {{-- Header --}}
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="bookingModalTitle">
                        <i class="mdi mdi-camera-outline text-success me-2"></i>Booking Sesi Foto
                    </h5>
                    <p class="text-muted small mb-0 mt-1" id="bookingModalSubtitle">
                        Isi data di bawah untuk melakukan booking sesi foto
                    </p>
                </div>
                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        id="bookingCloseBtn">
                </button>
            </div>

            {{-- Step Indicator --}}
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

            {{-- Body --}}
            <div class="modal-body p-4">

                {{-- ==================== STEP 1: Data Diri ==================== --}}
                <div id="booking-step-1">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control form-control-lg"
                                   id="b_customer_name"
                                   placeholder="Masukkan nama lengkap Anda"
                                   autocomplete="name">
                            <div class="invalid-feedback" id="err_customer_name">Nama tidak boleh kosong.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Nomor WhatsApp <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="mdi mdi-whatsapp text-success"></i>
                                </span>
                                <input type="tel"
                                       class="form-control border-start-0"
                                       id="b_phone_number"
                                       placeholder="08xxxxxxxxxx"
                                       autocomplete="tel">
                            </div>
                            <div class="invalid-feedback d-block d-none" id="err_phone_number">Nomor telepon tidak valid.</div>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button class="btn btn-success btn-lg rounded-pill" onclick="bookingNextStep(2)">
                            Lanjut Pilih Paket <i class="mdi mdi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                {{-- ==================== STEP 2: Pilih Paket & Jadwal ==================== --}}
                <div id="booking-step-2" class="d-none">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Produk <span class="text-danger">*</span></label>
                            <select class="form-select form-select-lg" id="b_product_id" onchange="bookingUpdatePackets()">
                                <option value="" disabled selected>-- Pilih Produk --</option>
                                @foreach($packets as $productName => $packetGroup)
                                    <option value="{{ $packetGroup->first()->product_id }}"
                                            data-product-name="{{ $productName }}">
                                        {{ $productName }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-none" id="err_product">Pilih produk terlebih dahulu.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Paket <span class="text-danger">*</span></label>
                            <select class="form-select form-select-lg" id="b_packet_id" disabled onchange="bookingUpdatePrice()">
                                <option value="" data-price="0" disabled selected>-- Pilih Paket --</option>
                            </select>
                            <div class="invalid-feedback d-none" id="err_packet">Pilih paket terlebih dahulu.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Sesi <span class="text-danger">*</span></label>
                            <input type="date"
                                   class="form-control form-control-lg"
                                   id="b_session_date">
                            <div class="invalid-feedback d-none" id="err_date">Pilih tanggal sesi.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Waktu Sesi <span class="text-danger">*</span></label>
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
                            <div class="invalid-feedback d-none" id="err_time">Pilih waktu sesi.</div>
                        </div>

                        {{-- Ringkasan Harga --}}
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
                            Lanjut ke Pembayaran <i class="mdi mdi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                {{-- ==================== STEP 3: Konfirmasi & Bayar ==================== --}}
                <div id="booking-step-3" class="d-none">

                    {{-- Ringkasan Pesanan --}}
                    <div class="card border-0 bg-light rounded-3 mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">
                                <i class="mdi mdi-clipboard-check-outline text-success me-1"></i>
                                Ringkasan Pesanan
                            </h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-muted" style="width: 40%">Nama</td>
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
                                        <td class="fw-bold pt-2">Total Bayar</td>
                                        <td class="fw-bold text-success fs-5 pt-2" id="confirm_total">-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p class="text-muted small text-center mb-3">
                        <i class="mdi mdi-information-outline text-primary me-1"></i>
                        Setelah booking dikonfirmasi, tim kami akan menghubungi Anda via WhatsApp untuk info pembayaran.
                    </p>

                    {{-- Loading state --}}
                    <div class="d-none text-center py-3" id="booking-loading">
                        <div class="spinner-border text-success" role="status"></div>
                        <p class="mt-2 text-muted small">Menyimpan booking Anda...</p>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-lg rounded-pill px-4" onclick="bookingNextStep(2)" id="booking-back-btn">
                            <i class="mdi mdi-arrow-left me-1"></i> Kembali
                        </button>
                        <button class="btn btn-success btn-lg rounded-pill flex-grow-1" onclick="bookingSubmit()" id="booking-pay-btn">
                            <i class="mdi mdi-check-circle-outline me-1"></i> Konfirmasi Booking
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- =====================================================================
     CSS — Booking Modal
     ===================================================================== --}}
<style>
    /* Step indicator */
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
        transition: all 0.3s ease;
    }
    .booking-step .step-label {
        font-size: 11px;
        color: #6c757d;
        white-space: nowrap;
        transition: all 0.3s ease;
    }
    .booking-step.active .step-number {
        background: #198754;
        color: white;
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
        transition: background 0.3s ease;
    }
    .step-line.done {
        background: #198754;
    }

    /* Animasi step */
    .booking-step-fade {
        animation: stepFadeIn 0.25s ease;
    }
    @keyframes stepFadeIn {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* Date input minimum today */
    #b_session_date {
        min-width: 100%;
    }
</style>

{{-- =====================================================================
     JavaScript — Booking Logic
     ===================================================================== --}}
<script>
(function () {
    'use strict';

    // ── Data produk & paket dari Blade (PHP) ──────────────────────────
    const productPackets = {!! json_encode($packets) !!};

    // ── Format currency Rupiah ────────────────────────────────────────
    const formatRp = (num) =>
        new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);

    // ── Format tanggal ke Indonesia ───────────────────────────────────
    const formatDate = (dateStr) => {
        if (!dateStr) return '-';
        return new Intl.DateTimeFormat('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
            .format(new Date(dateStr));
    };

    // ── Set minimum tanggal = hari ini ────────────────────────────────
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('b_session_date').setAttribute('min', today);

    // ── Step navigation ───────────────────────────────────────────────
    window.bookingNextStep = function (step) {
        if (step > 1 && !validateStep(step - 1)) return;

        // Sembunyikan semua step
        [1, 2, 3].forEach(i => {
            document.getElementById(`booking-step-${i}`).classList.add('d-none');
            document.getElementById(`step-indicator-${i}`).classList.remove('active', 'done');
        });

        // Update step line
        document.querySelectorAll('.step-line').forEach((line, idx) => {
            line.classList.toggle('done', idx < step - 1);
        });

        // Tandai done untuk step sebelumnya, active untuk sekarang
        for (let i = 1; i < step; i++) {
            document.getElementById(`step-indicator-${i}`).classList.add('done');
        }
        document.getElementById(`step-indicator-${step}`).classList.add('active');

        // Tampilkan step aktif
        const currentStep = document.getElementById(`booking-step-${step}`);
        currentStep.classList.remove('d-none');
        currentStep.classList.add('booking-step-fade');
        setTimeout(() => currentStep.classList.remove('booking-step-fade'), 300);

        // Isi data konfirmasi saat masuk step 3
        if (step === 3) bookingFillConfirmation();
    };

    // ── Validasi per step ─────────────────────────────────────────────
    function validateStep(step) {
        let valid = true;

        const clearError = (id) => {
            const el = document.getElementById(id);
            if (el) el.classList.add('d-none');
        };
        const showError = (inputId, errId) => {
            const el = document.getElementById(inputId);
            const err = document.getElementById(errId);
            if (el) el.classList.add('is-invalid');
            if (err) err.classList.remove('d-none');
            valid = false;
        };
        const clearInput = (id) => {
            const el = document.getElementById(id);
            if (el) el.classList.remove('is-invalid');
        };

        if (step === 1) {
            const name  = document.getElementById('b_customer_name').value.trim();
            const phone = document.getElementById('b_phone_number').value.trim();

            clearInput('b_customer_name'); clearError('err_customer_name');
            clearInput('b_phone_number'); clearError('err_phone_number');

            if (!name) showError('b_customer_name', 'err_customer_name');
            if (!phone || !/^08\d{8,12}$/.test(phone)) showError('b_phone_number', 'err_phone_number');
        }

        if (step === 2) {
            const product = document.getElementById('b_product_id').value;
            const packet  = document.getElementById('b_packet_id').value;
            const date    = document.getElementById('b_session_date').value;
            const time    = document.getElementById('b_session_time').value;

            clearInput('b_product_id'); clearError('err_product');
            clearInput('b_packet_id'); clearError('err_packet');
            clearInput('b_session_date'); clearError('err_date');
            clearInput('b_session_time'); clearError('err_time');

            if (!product) showError('b_product_id', 'err_product');
            if (!packet)  showError('b_packet_id', 'err_packet');
            if (!date)    showError('b_session_date', 'err_date');
            if (!time)    showError('b_session_time', 'err_time');
        }

        return valid;
    }

    // ── Update daftar paket ketika produk berubah ─────────────────────
    window.bookingUpdatePackets = function () {
        const productSelect = document.getElementById('b_product_id');
        const packetSelect  = document.getElementById('b_packet_id');

        packetSelect.innerHTML = '<option value="" data-price="0" disabled selected>-- Pilih Paket --</option>';
        packetSelect.disabled = true;

        const productName = productSelect.options[productSelect.selectedIndex]?.dataset.productName;
        if (!productName || !productPackets[productName]) return;

        productPackets[productName].forEach(packet => {
            const opt = document.createElement('option');
            opt.value           = packet.id;
            opt.dataset.price   = packet.price;
            opt.dataset.name    = packet.name;
            opt.textContent     = `${packet.name} — ${formatRp(packet.price)}`;
            packetSelect.appendChild(opt);
        });

        packetSelect.disabled = false;
        bookingUpdatePrice();
    };

    // ── Update tampilan harga ─────────────────────────────────────────
    window.bookingUpdatePrice = function () {
        const packetSelect = document.getElementById('b_packet_id');
        const summary      = document.getElementById('booking-price-summary');
        const display      = document.getElementById('booking-price-display');

        const price = parseFloat(packetSelect.options[packetSelect.selectedIndex]?.dataset.price) || 0;

        if (price > 0) {
            display.textContent = formatRp(price);
            summary.classList.remove('d-none');
        } else {
            summary.classList.add('d-none');
        }
    };

    // ── Isi data konfirmasi di step 3 ─────────────────────────────────
    function bookingFillConfirmation() {
        const productSelect = document.getElementById('b_product_id');
        const packetSelect  = document.getElementById('b_packet_id');
        const price = parseFloat(packetSelect.options[packetSelect.selectedIndex]?.dataset.price) || 0;

        document.getElementById('confirm_name').textContent    = document.getElementById('b_customer_name').value;
        document.getElementById('confirm_phone').textContent   = document.getElementById('b_phone_number').value;
        document.getElementById('confirm_product').textContent = productSelect.options[productSelect.selectedIndex]?.text || '-';
        document.getElementById('confirm_packet').textContent  = packetSelect.options[packetSelect.selectedIndex]?.dataset.name || '-';
        document.getElementById('confirm_date').textContent    = formatDate(document.getElementById('b_session_date').value);
        document.getElementById('confirm_time').textContent    = document.getElementById('b_session_time').value + ' WIB';
        document.getElementById('confirm_total').textContent   = formatRp(price);
    }

    // ── Submit: POST langsung ke server → simpan ke database ─────────
    window.bookingSubmit = async function () {
        const loadingEl = document.getElementById('booking-loading');
        const payBtn    = document.getElementById('booking-pay-btn');
        const backBtn   = document.getElementById('booking-back-btn');
        const closeBtn  = document.getElementById('bookingCloseBtn');

        loadingEl.classList.remove('d-none');
        payBtn.disabled   = true;
        backBtn.disabled  = true;
        closeBtn.disabled = true;

        const payload = {
            customer_name: document.getElementById('b_customer_name').value.trim(),
            phone_number:  document.getElementById('b_phone_number').value.trim(),
            product_id:    document.getElementById('b_product_id').value,
            packet_id:     document.getElementById('b_packet_id').value,
            session_date:  document.getElementById('b_session_date').value,
            session_time:  document.getElementById('b_session_time').value,
        };

        try {
            const response = await fetch('{{ route('booking.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Terjadi kesalahan.');
            }

            // Tampilkan pesan sukses di dalam modal (tanpa redirect)
            document.querySelector('.modal-body').innerHTML = `
                <div class="text-center py-4">
                    <div class="mb-3">
                        <i class="mdi mdi-check-circle text-success" style="font-size: 72px;"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Booking Berhasil!</h4>
                    <p class="text-muted mb-4">
                        Terima kasih, <strong>${payload.customer_name}</strong>!<br>
                        Tim kami akan segera menghubungi Anda di nomor<br>
                        <strong>${payload.phone_number}</strong> untuk konfirmasi lebih lanjut.
                    </p>
                    <button class="btn btn-success rounded-pill px-5" data-bs-dismiss="modal">
                        Tutup
                    </button>
                </div>
            `;

        } catch (error) {
            loadingEl.classList.add('d-none');
            payBtn.disabled   = false;
            backBtn.disabled  = false;
            closeBtn.disabled = false;

            alert('Gagal menyimpan booking: ' + error.message);
        }
    };

    // ── Reset modal saat ditutup ──────────────────────────────────────
    document.getElementById('bookingModal').addEventListener('hidden.bs.modal', function () {
        // Kembali ke step 1
        bookingNextStep(1);

        // Bersihkan semua field
        ['b_customer_name', 'b_phone_number', 'b_session_date'].forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.value = ''; el.classList.remove('is-invalid'); }
        });
        ['b_product_id', 'b_session_time'].forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.selectedIndex = 0; el.classList.remove('is-invalid'); }
        });

        const packetSelect = document.getElementById('b_packet_id');
        packetSelect.innerHTML = '<option value="" data-price="0" disabled selected>-- Pilih Paket --</option>';
        packetSelect.disabled = true;

        document.getElementById('booking-price-summary').classList.add('d-none');
        document.getElementById('booking-loading').classList.add('d-none');
        document.getElementById('booking-pay-btn').disabled  = false;
        document.getElementById('booking-back-btn').disabled = false;
        document.getElementById('bookingCloseBtn').disabled  = false;
    });

})();
</script>