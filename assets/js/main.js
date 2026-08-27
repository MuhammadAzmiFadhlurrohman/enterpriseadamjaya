/* ========================================================
   MAIN JAVASCRIPT & DYNAMIC FORM LOGIC: ADAM JAYA ENTERPRISE
   ======================================================== */

window.needsPageReload = false;
let currentSelectedMetode = '';

document.addEventListener("DOMContentLoaded", function () {
  // Mobile Sidebar Toggle with Backdrop Overlay
  const toggleBtn = document.getElementById("sidebar-toggle");
  const sidebar = document.getElementById("sidebar-wrapper");

  // Create backdrop overlay element if not exists
  let overlay = document.getElementById("sidebar-overlay");
  if (!overlay) {
    overlay = document.createElement("div");
    overlay.id = "sidebar-overlay";
    document.body.appendChild(overlay);
  }

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      sidebar.classList.toggle("show");
      overlay.classList.toggle("show");
    });

    overlay.addEventListener("click", function () {
      sidebar.classList.remove("show");
      overlay.classList.remove("show");
    });
  }

  // Auto reload table data when closing detail modal if status updated
  const detailModalElem = document.getElementById('detailPengajuanModal');
  if (detailModalElem) {
    detailModalElem.addEventListener('hidden.bs.modal', function () {
      if (window.needsPageReload) {
        window.location.reload();
      }
    });
  }

  // Auto Init Rupiah Masking
  initRupiahMasking();

  // Feature 2: Init Animated KPI Counters
  setTimeout(animateKPICounters, 100);

  // Feature 4: Init Network Speed & Slow Network Indicator
  initNetworkAndLoaders();
});

/**
 * Dynamic Modal Detail Pengajuan View
 */
function viewDetailPengajuan(id) {
  const modalBody = document.getElementById("detailModalContent");
  if (!modalBody) return;
  
  modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-indigo" role="status"></div><p class="mt-2 text-muted small">Memuat data detail...</p></div>`;
  
  const detailModalElem = document.getElementById('detailPengajuanModal');
  let detailModal = bootstrap.Modal.getInstance(detailModalElem);
  if (!detailModal) {
    detailModal = new bootstrap.Modal(detailModalElem);
  }
  detailModal.show();

  fetch(`get_pengajuan_detail.php?id=${id}`)
    .then(response => response.text())
    .then(html => {
      modalBody.innerHTML = html;
    })
    .catch(err => {
      modalBody.innerHTML = `<div class="alert alert-danger m-3">Gagal memuat data detail. Silakan coba lagi.</div>`;
    });
}

/* ========================================================
   MODAL DETAIL ACTION HANDLERS (GLOBAL SCOPE)
   ======================================================== */

function selectPaymentMethod(metode) {
  currentSelectedMetode = metode;
  const inputMetode = document.getElementById('modal_payment_metode');
  if (inputMetode) inputMetode.value = metode;

  const titleElem = document.getElementById('selected_method_title');
  if (titleElem) {
    titleElem.innerText = `Metode Dipilih: ${metode === 'transfer' ? 'Transfer Bank' : 'Cash / Tunai'}`;
  }

  const subBox = document.getElementById('payment_sub_box');
  if (subBox) subBox.classList.remove('d-none');

  const uploadForm = document.getElementById('formModalUploadProof');
  if (uploadForm) uploadForm.classList.add('d-none');
}

function resetPaymentSelection() {
  currentSelectedMetode = '';
  const subBox = document.getElementById('payment_sub_box');
  if (subBox) subBox.classList.add('d-none');

  const uploadForm = document.getElementById('formModalUploadProof');
  if (uploadForm) uploadForm.classList.add('d-none');
}

function showProofUploadForm() {
  const uploadForm = document.getElementById('formModalUploadProof');
  if (uploadForm) uploadForm.classList.remove('d-none');
}

function processPaymentWithoutProof(pengajuanId, csrfTokenVal) {
  if (!confirm('Ubah status pembayaran menjadi LUNAS DIBAYAR tanpa mengunggah berkas bukti?')) return;

  const formData = new FormData();
  formData.append('action', 'bayar_tanpa_bukti');
  formData.append('id', pengajuanId);
  formData.append('csrf_token', csrfTokenVal);

  fetch('proses_modal_action.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      window.needsPageReload = true;
      Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message, timer: 1500, showConfirmButton: false });
      viewDetailPengajuan(pengajuanId);
    } else {
      Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
    }
  })
  .catch(err => {
    Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan koneksi server.' });
  });
}

function submitPaymentWithProof(e, pengajuanId) {
  e.preventDefault();
  const form = document.getElementById('formModalUploadProof');
  const formData = new FormData(form);

  fetch('proses_modal_action.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      window.needsPageReload = true;
      Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message, timer: 1500, showConfirmButton: false });
      viewDetailPengajuan(pengajuanId);
    } else {
      Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
    }
  })
  .catch(err => {
    Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan koneksi server.' });
  });
}

function processDirectAction(actionName, id, csrfTokenVal) {
  const formData = new FormData();
  formData.append('action', actionName);
  formData.append('id', id);
  formData.append('csrf_token', csrfTokenVal);

  fetch('proses_modal_action.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      window.needsPageReload = true;
      Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message, timer: 1500, showConfirmButton: false });
      viewDetailPengajuan(id);
    } else {
      Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
    }
  })
  .catch(err => {
    Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan koneksi server.' });
  });
}

function submitKwitansiModal(e, id) {
  e.preventDefault();
  const form = document.getElementById('formKwitansiModal');
  const formData = new FormData(form);

  fetch('proses_modal_action.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      window.needsPageReload = true;
      Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message, timer: 1500, showConfirmButton: false });
      viewDetailPengajuan(id);
    } else {
      Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
    }
  })
  .catch(err => {
    Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan koneksi server.' });
  });
}

/**
 * Format angka ke Rupiah JavaScript
 */
function formatRupiahJS(angka, prefix = 'Rp ') {
  if (angka === null || angka === undefined || angka === '') return '';
  
  if (typeof angka === 'number') {
    if (isNaN(angka)) return '';
    let parts = String(angka).split('.');
    let intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    let decPart = parts[1] && parts[1] !== '00' && parts[1] !== '0' ? ',' + parts[1] : '';
    return prefix ? prefix + intPart + decPart : intPart + decPart;
  }

  let strVal = String(angka).trim();
  if (strVal === '') return '';

  // Handle pure MySQL decimal strings like "100000.00" or "50.50" (only if numeric float, no Rp, no comma)
  if (/^-?\d+\.\d+$/.test(strVal) && !strVal.startsWith('Rp') && !strVal.includes(',')) {
    let num = parseFloat(strVal);
    if (!isNaN(num)) {
      let parts = String(num).split('.');
      let intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      let decPart = parts[1] && parts[1] !== '00' && parts[1] !== '0' ? ',' + parts[1] : '';
      return prefix ? prefix + intPart + decPart : intPart + decPart;
    }
  }

  // Live input or formatted string: strip non-digits except comma
  let clean = strVal.replace(/[^0-9,]/g, '');
  if (!clean) return '';

  let split = clean.split(',');
  let intPart = split[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  let decPart = split[1] !== undefined ? ',' + split[1] : '';
  let result = intPart ? intPart + decPart : '';
  return prefix ? (result ? prefix + result : '') : result;
}

/**
 * Unformat Rupiah JS to Float
 */
function unformatRupiahJS(rupiahStr) {
  if (rupiahStr === null || rupiahStr === undefined || rupiahStr === '') return 0;
  if (typeof rupiahStr === 'number') return rupiahStr;
  let s = String(rupiahStr).trim();
  if (s === '') return 0;

  if (/^-?\d+\.\d+$/.test(s) && !s.includes(',') && !s.startsWith('Rp')) {
    return parseFloat(s) || 0;
  }

  let cleaned = s.replace(/[^0-9,-]/g, '').replace(',', '.');
  return parseFloat(cleaned) || 0;
}

function formatRupiahInput(input) {
  if (!input) return;
  let val = input.value;
  let clean = String(val).replace(/\D/g, '');
  if (!clean) {
    input.value = '';
    return;
  }
  input.value = 'Rp ' + clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Global Alias Compatibility
window.unformatRupiah = unformatRupiahJS;
window.unformatRupiahJS = unformatRupiahJS;
window.formatRupiahInput = formatRupiahInput;
window.formatRupiahJS = formatRupiahJS;
window.formatRupiah = function(num) { return formatRupiahJS(num, 'Rp '); };

function onRupiahInputMask() {
  formatRupiahInput(this);
}

/**
 * Init Event Listener Rupiah Masking
 */
function initRupiahMasking() {
  document.querySelectorAll('.rupiah-input').forEach(function (input) {
    input.removeEventListener('input', onRupiahInputMask);
    input.addEventListener('input', onRupiahInputMask);
  });
}

/* ========================================================
   GLOBAL 1-SECOND SPINNING LOGO LOADER FOR NAVIGATION & CRUD
   ======================================================== */
function showGlobalPageLoader(msg, callback, delayMs = 1000) {
  const loader = document.getElementById("global-page-loader");
  const msgElem = document.getElementById("global-loader-msg");
  
  if (msgElem && msg) {
    msgElem.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin me-1"></i> ${msg}`;
  }
  
  if (loader) {
    loader.style.display = "flex";
  }

  if (typeof callback === "function") {
    setTimeout(callback, delayMs);
  }
}

/**
 * SweetAlert Confirm Delete Helper (Light Mode Styled)
 */
function confirmDelete(event, url, message = "Data yang dihapus tidak dapat dikembalikan!") {
  event.preventDefault();
  Swal.fire({
    title: 'Apakah Anda Yakin?',
    text: message,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
    background: '#ffffff',
    color: '#0f172a',
    customClass: {
      popup: 'shadow-lg border rounded-4'
    }
  }).then((result) => {
    if (result.isConfirmed) {
      showGlobalPageLoader('Menghapus data...', function() {
        window.location.href = url;
      }, 1000);
    }
  });
}

/* ========================================================
   FEATURE 2: ANIMATED KPI COUNTER (COUNT UP ANIMATION)
   ======================================================== */
function animateKPICounters() {
  const targets = document.querySelectorAll(".stat-value, .stat-card-value, .badge-count, .kpi-number, .kpi-val, [data-counter]");
  targets.forEach(el => {
    const text = el.innerText.trim();
    if (!text || el.dataset.animated) return;

    // Separate numbers from suffix words (e.g. "12 Catatan" or "1.500 Total Stok" or "5 Pembeli")
    const isRupiah = text.includes("Rp");
    
    let suffix = "";
    const matchSuffix = text.match(/([a-zA-Z\s]+)$/);
    if (matchSuffix && !isRupiah && !text.includes("Rp")) {
      suffix = " " + matchSuffix[1].trim();
    }

    const targetVal = unformatRupiahJS(text);
    if (targetVal === null || isNaN(targetVal) || targetVal === 0) return;

    el.dataset.animated = "true";
    const duration = 1200;
    const startTime = performance.now();

    function step(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const ease = 1 - Math.pow(1 - progress, 3); // easeOutCubic
      const current = targetVal * ease;

      if (isRupiah) {
        el.innerText = formatRupiahJS(Math.round(current), 'Rp ');
      } else {
        el.innerText = Math.round(current).toLocaleString('id-ID') + suffix;
      }

      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        if (isRupiah) {
          el.innerText = formatRupiahJS(targetVal, 'Rp ');
        } else {
          el.innerText = targetVal.toLocaleString('id-ID') + suffix;
        }
      }
    }

    requestAnimationFrame(step);
  });
}

/* ========================================================
   FEATURE 7: FLOATING TOAST NOTIFICATION SYSTEM
   ======================================================== */
function showToast(type, title, message, duration = 4000) {
  const container = document.getElementById("toast-container");
  if (!container) return;

  const iconMap = {
    success: "fa-solid fa-circle-check text-success",
    error: "fa-solid fa-circle-xmark text-danger",
    danger: "fa-solid fa-circle-xmark text-danger",
    warning: "fa-solid fa-triangle-exclamation text-warning",
    info: "fa-solid fa-circle-info text-info"
  };

  const iconClass = iconMap[type] || iconMap.info;
  const toastId = "aj_toast_" + Date.now();

  const toastHtml = `
  <div id="${toastId}" class="toast show aj-toast-card toast-${type === 'danger' ? 'error' : type} mb-2" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header bg-white text-dark border-0 py-2.5 px-3 d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-2">
        <i class="${iconClass} fs-5"></i>
        <strong class="me-auto text-dark fw-bold" style="font-size: 0.92rem;">${title}</strong>
      </div>
      <button type="button" class="btn-close ms-2" style="font-size:0.7rem;" onclick="document.getElementById('${toastId}').remove()"></button>
    </div>
    ${message ? `<div class="toast-body py-1.5 px-3 pt-0 text-muted small" style="font-size: 0.84rem; line-height: 1.35; color: #475569 !important;">${message}</div>` : ''}
    <div class="toast-progress-bar" id="${toastId}_progress"></div>
  </div>`;

  container.insertAdjacentHTML("beforeend", toastHtml);

  const progressElem = document.getElementById(`${toastId}_progress`);
  if (progressElem) {
    progressElem.style.transition = `width ${duration}ms linear`;
    setTimeout(() => { progressElem.style.width = "0%"; }, 40);
  }

  setTimeout(() => {
    const elem = document.getElementById(toastId);
    if (elem) {
      elem.style.opacity = "0";
      setTimeout(() => elem.remove(), 300);
    }
  }, duration);
}

/* ========================================================
   NETWORK SPEED & SLOW NETWORK INDICATOR LOGIC
   ======================================================== */
function initNetworkAndLoaders() {
  const progressBar = document.getElementById("global-progress-bar");
  const slowNetBanner = document.getElementById("slow-net-banner");
  let slowNetTimer = null;

  window.startProgress = function() {
    if (!progressBar) return;
    progressBar.style.opacity = "1";
    progressBar.style.width = "40%";

    clearTimeout(slowNetTimer);
    slowNetTimer = setTimeout(() => {
      if (progressBar && progressBar.style.width !== "100%" && progressBar.style.opacity !== "0" && slowNetBanner) {
        slowNetBanner.style.display = "block";
      }
    }, 1500);
  };

  window.finishProgress = function() {
    if (!progressBar) return;
    progressBar.style.width = "100%";
    clearTimeout(slowNetTimer);
    setTimeout(() => {
      progressBar.style.opacity = "0";
      setTimeout(() => { progressBar.style.width = "0%"; }, 350);
      if (slowNetBanner) slowNetBanner.style.display = "none";
    }, 300);
  };

  // Intercept Fetch API requests to trigger top progress bar
  const originalFetch = window.fetch;
  if (originalFetch) {
    window.fetch = function(...args) {
      window.startProgress();
      return originalFetch.apply(this, args)
        .then(response => {
          window.finishProgress();
          return response;
        })
        .catch(error => {
          window.finishProgress();
          throw error;
        });
    };
  }

  // Detect link navigation with 1-second spinning logo overlay
  document.addEventListener("click", function (e) {
    const target = e.target.closest("a");
    if (target && target.href && !target.href.startsWith("javascript:") && !target.href.includes("#") && target.target !== "_blank") {
      const href = target.href;
      if (target.hasAttribute("data-no-loader")) return;

      e.preventDefault();
      window.startProgress();
      showGlobalPageLoader("Memuat Halaman...", function() {
        window.location.href = href;
      }, 1000); // 1 Detik Loading Animation
    }
  });

  // Detect form submissions with 1-second spinning logo overlay
  document.addEventListener("submit", function (e) {
    const form = e.target;
    if (form && !form.dataset.submitting && !form.hasAttribute("data-no-loader")) {
      e.preventDefault();
      form.dataset.submitting = "true";
      window.startProgress();

      let msg = "Menyimpan & memproses data...";
      const action = (form.getAttribute("action") || "").toLowerCase();
      if (action.includes("hapus") || action.includes("delete")) {
        msg = "Menghapus data...";
      } else if (action.includes("edit") || action.includes("update") || action.includes("jenis")) {
        msg = "Memperbarui data...";
      }

      showGlobalPageLoader(msg, function() {
        form.submit();
      }, 1000); // 1 Detik Loading Animation
    }
  });

  // Network connection online/offline events
  window.addEventListener("offline", function () {
    if (slowNetBanner) {
      const msgElem = document.getElementById("slow-net-msg");
      if (msgElem) msgElem.innerText = "Koneksi terputus (Offline). Memeriksa jaringan...";
      slowNetBanner.style.display = "block";
    }
    showToast("danger", "Koneksi Terputus", "Perangkat Anda sedang offline.", 4500);
  });

  window.addEventListener("online", function () {
    if (slowNetBanner) {
      slowNetBanner.style.display = "none";
    }
    showToast("success", "Koneksi Kembali", "Anda sudah terhubung kembali ke internet.", 3500);
  });
}

/* ========================================================
   GLOBAL HANDLER PEMBAYARAN CICILAN (ANGSURAN SUSULAN)
   ======================================================== */
function submitTambahCicilan(pengajuanId, csrfToken, maxSisa, cicilanKe) {
    if (typeof Swal === 'undefined') return;

    let defaultNum = parseInt(cicilanKe || 1, 10);
    if (isNaN(defaultNum) || defaultNum < 1) defaultNum = 1;
    let defaultCatatanStr = `Cicilan ke-${defaultNum}`;

    // Handler capture phase untuk menghentikan perangkap fokus Bootstrap 5.3
    const stopBootstrapFocusTrap = function(e) {
        if (e.target && (e.target.id === 'swal_nominal_cicilan' || e.target.id === 'swal_catatan_cicilan' || (e.target.closest && e.target.closest('.swal2-container')))) {
            e.stopPropagation();
        }
    };
    document.addEventListener('focusin', stopBootstrapFocusTrap, true);

    const activeBsModal = document.getElementById('detailPengajuanModal');
    let bsModalInstance = null;
    if (activeBsModal) {
        activeBsModal.removeAttribute('tabindex');
        if (window.bootstrap && bootstrap.Modal) {
            bsModalInstance = bootstrap.Modal.getInstance(activeBsModal);
            if (bsModalInstance && bsModalInstance._focustrap && typeof bsModalInstance._focustrap.deactivate === 'function') {
                bsModalInstance._focustrap.deactivate();
            }
        }
    }
    if (typeof $ !== 'undefined') {
        $(document).off('focusin.bs.modal');
    }

    Swal.fire({
        title: 'Catat Pembayaran Cicilan',
        html: `
            <div class="text-start mb-3">
                <label class="form-label small text-muted fw-bold mb-1">Nominal Pembayaran Cicilan (Rp):</label>
                <input type="text" id="swal_nominal_cicilan" class="form-control form-control-lg fw-bold text-wine" placeholder="Contoh: 500000">
                <div class="d-flex justify-content-between align-items-center mt-1 text-muted small" style="font-size:0.75rem;">
                    <span>Sisa Piutang Tagihan:</span>
                    <b class="text-danger">Rp ${parseFloat(maxSisa || 0).toLocaleString('id-ID')}</b>
                </div>
            </div>
            <div class="text-start mb-3">
                <label class="form-label small text-muted fw-bold mb-1">Metode Pembayaran:</label>
                <select id="swal_metode_cicilan" class="form-select fw-semibold">
                    <option value="Cash" selected>Cash</option>
                    <option value="Transfer">Transfer</option>
                </select>
            </div>
            <div class="text-start">
                <label class="form-label small text-muted fw-bold mb-1">Catatan / Keterangan (Otomatis):</label>
                <input type="text" id="swal_catatan_cicilan" class="form-control fw-bold" value="${defaultCatatanStr}" placeholder="Contoh: Cicilan ke-2 via transfer">
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check me-1"></i> Simpan Pembayaran',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#7A1E33',
        didOpen: () => {
            if (typeof $ !== 'undefined') {
                $(document).off('focusin.bs.modal');
            }
            const inputNominal = document.getElementById('swal_nominal_cicilan');
            if (inputNominal) {
                setTimeout(() => {
                    inputNominal.focus();
                }, 100);
                inputNominal.addEventListener('keyup', function() {
                    let val = this.value.replace(/[^\d]/g, '');
                    this.value = val ? parseInt(val, 10).toLocaleString('id-ID') : '';
                });
            }
        },
        willClose: () => {
            document.removeEventListener('focusin', stopBootstrapFocusTrap, true);
            if (activeBsModal) {
                activeBsModal.setAttribute('tabindex', '-1');
            }
            if (bsModalInstance && bsModalInstance._focustrap && typeof bsModalInstance._focustrap.activate === 'function') {
                bsModalInstance._focustrap.activate();
            }
        },
        preConfirm: () => {
            const inputNominal = document.getElementById('swal_nominal_cicilan');
            const selectMetode = document.getElementById('swal_metode_cicilan');
            const inputCatatan = document.getElementById('swal_catatan_cicilan');
            const nominalStr = inputNominal ? inputNominal.value : '';
            const metodeStr = selectMetode ? selectMetode.value : 'Cash';
            const catatanStr = inputCatatan ? inputCatatan.value : defaultCatatanStr;

            let num = parseInt(nominalStr.replace(/[^\d]/g, ''), 10) || 0;

            if (!nominalStr || num <= 0) {
                Swal.showValidationMessage('Masukkan nominal pembayaran cicilan yang valid (lebih dari Rp 0)!');
                return false;
            }
            return { nominal: num, metode: metodeStr, catatan: catatanStr };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('pengajuan_id', pengajuanId);
            formData.append('nominal_bayar', result.value.nominal);
            formData.append('metode_pembayaran', result.value.metode);
            formData.append('catatan', result.value.catatan);
            formData.append('csrf_token', csrfToken);

            fetch('proses_tambah_cicilan.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.needsPageReload = true;

                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: data.message,
                        timer: 1800,
                        showConfirmButton: false
                    }).then(() => {
                        if (typeof viewDetailPengajuan === 'function') {
                            viewDetailPengajuan(pengajuanId);
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: data.message
                    });
                }
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Sistem',
                    text: 'Terjadi kesalahan koneksi saat menyimpan cicilan.'
                });
            });
        }
    });
}
window.submitTambahCicilan = submitTambahCicilan;
