/**
 * Interaktivitas sisi klien DIGIVA.ID:
 * - Navbar Laptop: Floating pill menyusut jadi orb 54px saat scroll & bisa dibuka klik
 * - Header HP: Sticky header berubah jadi floating pill tanpa nama brand saat scroll
 * - Carousel Promo 3 slide + swipe sentuh + CTA auto-filter
 * - Filter kategori, pencarian sinkron desktop/mobile, sortir katalog
 * - Stepper checkout + validasi promo real-time via api.php
 * - Validasi form pasca-interaksi (:user-invalid + aria-invalid + live region + fallback)
 * - Light-dismiss fallback untuk <dialog> & admin product editor modal
 */

(() => {
  'use strict';

  const formatRp = (num) =>
    'Rp ' + Math.max(0, Math.round(num)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  const showToast = (text) => {
    const stack = document.getElementById('toast-stack');
    if (!stack) return;
    const item = document.createElement('div');
    item.className = 'toast-item';
    item.textContent = text;
    stack.append(item);
    setTimeout(() => item.remove(), 2600);
  };

  /* ------------------------------------------------------------------------
     1. Desktop Floating Pill Scroll-Collapse & Mobile Header Morph
     ------------------------------------------------------------------------ */
  const desktopPill = document.getElementById('desktop-glass-pill');
  const desktopOrb = document.getElementById('desktop-nav-orb');
  const mobileHeader = document.getElementById('mobile-header');
  let pillManuallyPinned = false;

  const syncHeaderOnScroll = () => {
    const y = window.scrollY || window.pageYOffset || 0;
    if (mobileHeader) {
      mobileHeader.classList.toggle('is-scrolled', y > 20);
    }
    if (desktopPill && desktopOrb) {
      if (y <= 36) {
        pillManuallyPinned = false;
        desktopPill.classList.remove('is-compact');
        desktopPill.classList.add('is-expanded');
        desktopOrb.setAttribute('aria-expanded', 'true');
      } else if (!pillManuallyPinned) {
        desktopPill.classList.add('is-compact');
        desktopPill.classList.remove('is-expanded');
        desktopOrb.setAttribute('aria-expanded', 'false');
      }
    }
  };

  if (desktopOrb && desktopPill) {
    desktopOrb.addEventListener('click', () => {
      const compact = desktopPill.classList.contains('is-compact');
      if (compact) {
        pillManuallyPinned = true;
        desktopPill.classList.remove('is-compact');
        desktopPill.classList.add('is-expanded');
        desktopOrb.setAttribute('aria-expanded', 'true');
      } else if (window.scrollY > 36) {
        pillManuallyPinned = false;
        desktopPill.classList.add('is-compact');
        desktopPill.classList.remove('is-expanded');
        desktopOrb.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('click', (e) => {
      if (window.scrollY > 36 && pillManuallyPinned && !desktopPill.contains(e.target)) {
        pillManuallyPinned = false;
        syncHeaderOnScroll();
      }
    });
  }

  window.addEventListener('scroll', syncHeaderOnScroll, { passive: true });
  syncHeaderOnScroll();

  /* ------------------------------------------------------------------------
     2. Hero Promo Carousel (3 Slides + Touch Swipe + Pause on Hover)
     ------------------------------------------------------------------------ */
  const carousel = document.querySelector('[data-carousel]');
  if (carousel) {
    const slides = [...carousel.querySelectorAll('[data-slide]')];
    const dots = [...carousel.querySelectorAll('[data-slide-to]')];
    let current = 0;
    let timer = null;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const goTo = (idx) => {
      current = (idx + slides.length) % slides.length;
      slides.forEach((s, i) => s.classList.toggle('is-active', i === current));
      dots.forEach((d, i) => {
        const active = i === current;
        d.classList.toggle('is-active', active);
        d.setAttribute('aria-selected', String(active));
      });
    };

    const startAuto = () => {
      if (reducedMotion || slides.length < 2) return;
      clearInterval(timer);
      timer = setInterval(() => goTo(current + 1), 5200);
    };

    dots.forEach((dot) => {
      dot.addEventListener('click', () => {
        goTo(Number(dot.dataset.slideTo));
        startAuto();
      });
    });

    let touchStartX = 0;
    carousel.addEventListener('touchstart', (e) => {
      touchStartX = e.changedTouches[0].clientX;
    }, { passive: true });
    carousel.addEventListener('touchend', (e) => {
      const dx = e.changedTouches[0].clientX - touchStartX;
      if (Math.abs(dx) > 40) {
        goTo(current + (dx < 0 ? 1 : -1));
        startAuto();
      }
    }, { passive: true });

    carousel.addEventListener('mouseenter', () => clearInterval(timer));
    carousel.addEventListener('mouseleave', startAuto);
    startAuto();
  }

  /* ------------------------------------------------------------------------
     3. Katalog Filter, Pencarian Sinkron Laptop/HP, dan Sortir
     ------------------------------------------------------------------------ */
  const catalogSection = document.getElementById('katalog');
  const grid = document.getElementById('catalog-product-grid');
  if (catalogSection && grid) {
    const cards = [...grid.querySelectorAll('[data-product-card]')];
    const catButtons = [...catalogSection.querySelectorAll('[data-cat-filter]')];
    const sortSelect = document.getElementById('catalog-sort');
    const desktopSearch = document.getElementById('desktop-search-input');
    const mobileSearch = document.getElementById('mobile-search-input');
    const countLabel = document.getElementById('catalog-count-label');
    const resetBtn = document.getElementById('catalog-reset-btn');
    const emptyState = document.getElementById('catalog-empty-state');
    const emptyReset = document.getElementById('catalog-empty-reset');

    let activeCat = catalogSection.dataset.initialCat || 'all';
    let query = (desktopSearch?.value || mobileSearch?.value || '').trim().toLowerCase();

    const applyCatalog = () => {
      const sortMode = sortSelect?.value || 'recommended';
      const sorted = [...cards].sort((a, b) => {
        if (sortMode === 'price-asc') return Number(a.dataset.price) - Number(b.dataset.price);
        if (sortMode === 'price-desc') return Number(b.dataset.price) - Number(a.dataset.price);
        if (sortMode === 'bestseller') return Number(b.dataset.sold) - Number(a.dataset.sold);
        const featDiff = Number(b.dataset.featured) - Number(a.dataset.featured);
        return featDiff !== 0 ? featDiff : Number(b.dataset.sold) - Number(a.dataset.sold);
      });

      let visibleCount = 0;
      sorted.forEach((card) => {
        grid.append(card);
        const matchCat = activeCat === 'all' || card.dataset.category === activeCat;
        const matchQuery =
          query === '' ||
          card.dataset.title.includes(query) ||
          card.dataset.brand.includes(query) ||
          card.dataset.category.toLowerCase().includes(query);
        const show = matchCat && matchQuery;
        card.hidden = !show;
        if (show) visibleCount += 1;
      });

      if (countLabel) {
        if (activeCat === 'all' && query === '') {
          countLabel.textContent = `${visibleCount} produk pilihan`;
        } else if (activeCat !== 'all' && query === '') {
          countLabel.textContent = `${visibleCount} produk · ${activeCat}`;
        } else {
          countLabel.textContent = `${visibleCount} hasil ditemukan`;
        }
      }

      if (resetBtn) {
        resetBtn.hidden = activeCat === 'all' && query === '';
      }
      if (emptyState) {
        emptyState.hidden = visibleCount > 0;
      }
    };

    const setCategory = (cat) => {
      activeCat = cat;
      catButtons.forEach((btn) => {
        const active = btn.dataset.catFilter === cat;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', String(active));
      });
      applyCatalog();
    };

    catButtons.forEach((btn) => {
      btn.addEventListener('click', () => setCategory(btn.dataset.catFilter));
    });

    document.querySelectorAll('[data-hero-cat]').forEach((cta) => {
      cta.addEventListener('click', () => {
        const targetCat = cta.dataset.heroCat;
        if (targetCat) setCategory(targetCat);
      });
    });

    const syncSearch = (val, source) => {
      query = val.trim().toLowerCase();
      if (desktopSearch && source !== desktopSearch) desktopSearch.value = val;
      if (mobileSearch && source !== mobileSearch) mobileSearch.value = val;
      applyCatalog();
    };

    desktopSearch?.addEventListener('input', (e) => syncSearch(e.target.value, desktopSearch));
    mobileSearch?.addEventListener('input', (e) => syncSearch(e.target.value, mobileSearch));
    mobileSearch?.closest('form')?.addEventListener('submit', (e) => {
      if (catalogSection) {
        e.preventDefault();
        catalogSection.scrollIntoView({ behavior: 'smooth' });
      }
    });

    sortSelect?.addEventListener('change', applyCatalog);

    const resetAll = () => {
      syncSearch('', null);
      setCategory('all');
    };
    resetBtn?.addEventListener('click', resetAll);
    emptyReset?.addEventListener('click', resetAll);

    applyCatalog();
  }

  /* ------------------------------------------------------------------------
     4. Checkout Quantity Stepper & Live Promo API
     ------------------------------------------------------------------------ */
  const checkoutForm = document.getElementById('checkout-form');
  if (checkoutForm) {
    const unitPrice = Number(checkoutForm.dataset.unitPrice || 0);
    const maxStock = Math.min(20, Number(checkoutForm.dataset.maxStock || 1));
    const qtyInput = document.getElementById('checkout-qty');
    const qtyMinus = document.getElementById('qty-minus');
    const qtyPlus = document.getElementById('qty-plus');
    const summaryQty = document.getElementById('summary-qty');
    const summaryFee = document.getElementById('summary-fee');
    const feeHint = document.getElementById('qris-fee-hint');
    const summaryDiscountRow = document.getElementById('summary-discount-row');
    const summaryDiscount = document.getElementById('summary-discount');
    const summaryTotal = document.getElementById('summary-total');
    const promoInput = document.getElementById('promo_code');
    const applyPromoBtn = document.getElementById('apply-promo-btn');
    const promoFeedback = document.getElementById('promo-feedback');

    let appliedDiscount = 0;
    let overrideFee = null;

    const recalcTotals = () => {
      const qty = Math.max(1, Math.min(maxStock, Number(qtyInput?.value || 1)));
      if (qtyInput) qtyInput.value = String(qty);
      const subtotal = unitPrice * qty;
      const afterDisc = Math.max(0, subtotal - appliedDiscount);
      const fee = overrideFee !== null
        ? overrideFee
        : (afterDisc > 0 ? Math.max(350, Math.round(afterDisc * 0.036)) : 0);
      const total = afterDisc + fee;

      if (summaryQty) summaryQty.textContent = `${qty} paket`;
      if (summaryFee) summaryFee.textContent = formatRp(fee);
      if (feeHint) feeHint.textContent = formatRp(fee);
      if (summaryTotal) summaryTotal.textContent = formatRp(total);
      if (summaryDiscountRow && summaryDiscount) {
        summaryDiscountRow.hidden = appliedDiscount <= 0;
        summaryDiscount.textContent = '-' + formatRp(appliedDiscount);
      }
    };

    const refreshFeeFromServer = async () => {
      const qty = Math.max(1, Number(qtyInput?.value || 1));
      const subtotal = unitPrice * qty;
      const afterDisc = Math.max(100, subtotal - appliedDiscount);
      try {
        const base = window.DIGIVA_BASE || '/';
        const res = await fetch(`${base}api.php?action=payment_fee&amount=${afterDisc}`);
        const data = await res.json();
        if (typeof data.qris === 'number') {
          overrideFee = data.qris;
        }
      } catch {
        overrideFee = null;
      }
      recalcTotals();
    };

    const validatePromo = async () => {
      const code = (promoInput?.value || '').trim();
      const qty = Math.max(1, Number(qtyInput?.value || 1));
      const subtotal = unitPrice * qty;
      if (!code) {
        appliedDiscount = 0;
        overrideFee = null;
        if (promoFeedback) {
          promoFeedback.textContent = '';
        }
        await refreshFeeFromServer();
        return;
      }

      try {
        const base = window.DIGIVA_BASE || '/';
        const url = `${base}api.php?action=check_promo&code=${encodeURIComponent(code)}&subtotal=${subtotal}`;
        const res = await fetch(url);
        const data = await res.json();
        appliedDiscount = data.valid ? Number(data.discount || 0) : 0;
        overrideFee = typeof data.fee === 'number' ? data.fee : null;
        if (promoFeedback) {
          promoFeedback.textContent = data.message || '';
          promoFeedback.style.color = data.valid ? 'var(--success)' : 'var(--danger)';
        }
        recalcTotals();
      } catch {
        if (promoFeedback) {
          promoFeedback.textContent = 'Gagal memeriksa kode promo.';
          promoFeedback.style.color = 'var(--danger)';
        }
      }
    };

    qtyMinus?.addEventListener('click', () => {
      if (qtyInput) qtyInput.value = String(Math.max(1, Number(qtyInput.value) - 1));
      if (promoInput?.value.trim()) validatePromo();
      else refreshFeeFromServer();
    });
    qtyPlus?.addEventListener('click', () => {
      if (qtyInput) qtyInput.value = String(Math.min(maxStock, Number(qtyInput.value) + 1));
      if (promoInput?.value.trim()) validatePromo();
      else refreshFeeFromServer();
    });
    qtyInput?.addEventListener('change', () => {
      if (promoInput?.value.trim()) validatePromo();
      else refreshFeeFromServer();
    });
    applyPromoBtn?.addEventListener('click', validatePromo);
  }

  /* ------------------------------------------------------------------------
     5. Form Validation & Consistent ARIA Experience (Modern Web Guidance)
     ------------------------------------------------------------------------ */
  const supportsUserInvalid = CSS.supports('selector(:user-invalid)');
  const linkError = (el, shown) => {
    const errorId = el.dataset.error;
    if (!errorId) return;
    const ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => id && id !== errorId);
    if (shown) ids.push(errorId);
    if (ids.length) el.setAttribute('aria-describedby', ids.join(' '));
    else el.removeAttribute('aria-describedby');
  };

  const announcer = document.createElement('div');
  announcer.setAttribute('aria-live', 'polite');
  announcer.className = 'visually-hidden';
  document.body.append(announcer);
  let announceTimer;
  let announcedField;

  const spokenText = (node) => {
    const copy = node.cloneNode(true);
    copy.querySelectorAll('[aria-hidden="true"]').forEach((n) => n.remove());
    return copy.textContent.replace(/\s+/g, ' ').trim();
  };

  const announceError = (el) => {
    const errEl = document.getElementById(el.dataset.error || '');
    if (!errEl) return;
    const label = el.labels?.[0] ? `${spokenText(el.labels[0])}: ` : '';
    clearTimeout(announceTimer);
    announcer.textContent = '';
    announcedField = el;
    announceTimer = setTimeout(() => {
      announcer.textContent = label + spokenText(errEl);
    }, 450);
  };

  const flagged = new WeakSet();
  const announced = new WeakSet();
  const dirtyState = new WeakMap();

  const syncAria = (el, announce = false) => {
    if (!el.matches?.('input, select, textarea') || !el.dataset.error) return;
    const invalid = supportsUserInvalid
      ? el.matches(':user-invalid')
      : el.classList.contains('user-invalid-fallback');

    if (invalid) {
      if (announce && !announced.has(el)) {
        announceError(el);
        announced.add(el);
      }
      el.setAttribute('aria-invalid', 'true');
      linkError(el, true);
      flagged.add(el);
    } else if (flagged.has(el) || el.getAttribute('aria-invalid') !== 'true') {
      el.setAttribute('aria-invalid', 'false');
      linkError(el, false);
      flagged.delete(el);
      announced.delete(el);
      if (announcedField === el) {
        clearTimeout(announceTimer);
        announcer.textContent = '';
      }
    }
  };

  document.querySelectorAll('input[data-error], select[data-error], textarea[data-error]').forEach((el) => {
    el.setAttribute('aria-invalid', 'false');
  });

  if (!supportsUserInvalid) {
    const updateFallback = (input) => {
      const valid = input.validity.valid;
      input.classList.toggle('user-invalid-fallback', !valid);
      input.classList.toggle('user-valid-fallback', valid);
      syncAria(input, !valid);
    };
    document.addEventListener('blur', (e) => {
      const el = e.target;
      if (!el.matches?.('input[data-error], select[data-error], textarea[data-error]')) return;
      const st = dirtyState.get(el) || { interacted: false, blurred: false };
      st.blurred = true;
      dirtyState.set(el, st);
      if (st.interacted) updateFallback(el);
    }, true);
    document.addEventListener('input', (e) => {
      const el = e.target;
      if (!el.matches?.('input[data-error], select[data-error], textarea[data-error]')) return;
      const st = dirtyState.get(el) || { interacted: false, blurred: false };
      st.interacted = true;
      dirtyState.set(el, st);
      if (st.blurred) updateFallback(el);
    });
    document.addEventListener('invalid', (e) => {
      const el = e.target;
      if (!el.matches?.('input[data-error], select[data-error], textarea[data-error]')) return;
      dirtyState.set(el, { interacted: true, blurred: true });
      announced.add(el);
      updateFallback(el);
    }, true);
  } else {
    document.addEventListener('blur', (e) => syncAria(e.target, true), true);
    document.addEventListener('input', (e) => {
      if (e.target.hasAttribute('aria-invalid')) syncAria(e.target);
    });
    document.addEventListener('change', (e) => syncAria(e.target, true), true);
    document.addEventListener('invalid', (e) => {
      announced.add(e.target);
      syncAria(e.target);
    }, true);
  }

  /* ------------------------------------------------------------------------
     6. Invoice Countdown, Copy Credentials, CS Pre-fill & Dialog Modal
     ------------------------------------------------------------------------ */
  const countdownEl = document.querySelector('[data-countdown-until]');
  if (countdownEl) {
    const targetMs = Date.parse(countdownEl.dataset.countdownUntil.replace(' ', 'T'));
    const tick = () => {
      const diff = Math.max(0, Math.floor((targetMs - Date.now()) / 1000));
      const m = String(Math.floor(diff / 60)).padStart(2, '0');
      const s = String(diff % 60).padStart(2, '0');
      countdownEl.textContent = diff > 0 ? `Batas waktu pembayaran: ${m}:${s}` : 'Batas waktu invoice berakhir';
    };
    if (!Number.isNaN(targetMs)) {
      tick();
      setInterval(tick, 1000);
    }
  }

  document.querySelectorAll('[data-copy-target]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const el = document.getElementById(btn.dataset.copyTarget);
      if (!el) return;
      try {
        await navigator.clipboard.writeText(el.textContent.trim());
        showToast(btn.dataset.copyMessage || 'Detail akses berhasil disalin!');
      } catch {
        showToast('Pilih teks secara manual untuk menyalin.');
      }
    });
  });

  const csInvoiceInput = document.getElementById('cs-invoice-input');
  const csWaBtn = document.getElementById('cs-whatsapp-btn');
  if (csInvoiceInput && csWaBtn) {
    csInvoiceInput.addEventListener('input', () => {
      const baseWa = csWaBtn.dataset.waBase;
      const store = csWaBtn.dataset.storeName || 'DIGIVA.ID';
      const inv = csInvoiceInput.value.trim();
      const msg = inv
        ? `Halo CS ${store}, saya butuh bantuan terkait invoice ${inv}.`
        : `Halo CS ${store}, saya butuh bantuan pesanan.`;
      csWaBtn.href = `https://wa.me/${encodeURIComponent(baseWa)}?text=${encodeURIComponent(msg)}`;
    });
  }

  // Native <dialog> light-dismiss fallback & Admin Product Editor
  if (!('closedBy' in HTMLDialogElement.prototype)) {
    const isOutside = (e, dlg) => {
      const r = dlg.getBoundingClientRect();
      return e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
    };
    document.querySelectorAll('dialog[closedby="any"]').forEach((dlg) => {
      let downOut = false;
      dlg.addEventListener('pointerdown', (e) => { downOut = isOutside(e, dlg); });
      dlg.addEventListener('pointerup', (e) => { if (downOut && isOutside(e, dlg)) dlg.close(); });
    });
  }

  const productDialog = document.getElementById('product-editor-dialog');
  if (productDialog) {
    const dialogTitle = document.getElementById('product-dialog-title');
    const openAddBtn = document.getElementById('open-add-product-btn');
    const closeBtns = [
      document.getElementById('close-product-dialog'),
      document.getElementById('cancel-product-dialog'),
    ];

    const fillEditor = (data = null) => {
      document.getElementById('prod_id').value = data ? data.id : '0';
      document.getElementById('prod_title').value = data ? data.title : '';
      document.getElementById('prod_variant').value = data ? data.variant_label : '1 BULAN';
      const srcEl = document.getElementById('prod_source');
      const pidEl = document.getElementById('prod_provider_id');
      if (srcEl) srcEl.value = data ? (data.provider_source || 'local') : 'local';
      if (pidEl) pidEl.value = data ? (data.provider_product_id || '') : '';
      document.getElementById('prod_brand_key').value = data ? data.brand_key : 'netflix';
      document.getElementById('prod_brand_label').value = data ? data.brand_label : 'Netflix';
      document.getElementById('prod_group').value = data ? data.group_slug : 'custom';
      document.getElementById('prod_category').value = data ? data.category : 'Streaming';
      document.getElementById('prod_price').value = data ? data.price : '25000';
      document.getElementById('prod_stock').value = data ? data.stock : '50';
      document.getElementById('prod_delivery').value = data ? data.delivery_type : 'instant';
      document.getElementById('prod_desc').value = data ? data.description : '';
      document.getElementById('prod_tpl').value = data ? data.account_template : '';
      document.getElementById('prod_featured').checked = data ? Number(data.is_featured) === 1 : false;
      dialogTitle.textContent = data ? `Edit Produk #${data.id}` : 'Tambah Produk Digital';
      productDialog.showModal();
    };

    openAddBtn?.addEventListener('click', () => fillEditor(null));
    document.querySelectorAll('.js-edit-product').forEach((btn) => {
      btn.addEventListener('click', () => {
        try {
          fillEditor(JSON.parse(btn.dataset.product));
        } catch {
          fillEditor(null);
        }
      });
    });
    closeBtns.forEach((b) => b?.addEventListener('click', () => productDialog.close()));
  }

  /* ------------------------------------------------------------------------
     7. Toggle Secret Key, Accessible Admin Tabs, Live QRIS QR & Auto-Polling
     ------------------------------------------------------------------------ */
  document.querySelectorAll('[data-toggle-secret]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.toggleSecret);
      if (!target) return;
      const isPassword = target.type === 'password';
      target.type = isPassword ? 'text' : 'password';
      btn.textContent = isPassword ? 'Sembunyi' : 'Lihat';
    });
  });

  document.querySelectorAll('[data-tabs-root]').forEach((root) => {
    const tabs = [...root.querySelectorAll('[role="tab"]')];
    const panels = [...root.querySelectorAll('[role="tabpanel"]')];

    const selectTab = (tab, moveFocus = false) => {
      const targetId = tab.dataset.tabTarget;
      tabs.forEach((t) => {
        const active = t === tab;
        t.setAttribute('aria-selected', String(active));
        t.toggleAttribute('focusgroupstart', active);
      });
      panels.forEach((p) => {
        const match = p.id === targetId;
        if (match) {
          p.removeAttribute('hidden');
          p.setAttribute('tabindex', '0');
        } else {
          p.setAttribute('hidden', 'until-found');
          p.removeAttribute('tabindex');
        }
      });
      tab.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      if (moveFocus) tab.focus();
    };

    tabs.forEach((tab, idx) => {
      tab.addEventListener('click', () => selectTab(tab));
      tab.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') {
          e.preventDefault();
          selectTab(tabs[(idx + 1) % tabs.length], true);
        } else if (e.key === 'ArrowLeft') {
          e.preventDefault();
          selectTab(tabs[(idx - 1 + tabs.length) % tabs.length], true);
        } else if (e.key === 'Home') {
          e.preventDefault();
          selectTab(tabs[0], true);
        } else if (e.key === 'End') {
          e.preventDefault();
          selectTab(tabs[tabs.length - 1], true);
        }
      });
    });

    root.addEventListener('beforematch', (e) => {
      const panel = e.target.closest?.('[role="tabpanel"]');
      if (!panel) return;
      const matchingTab = tabs.find((t) => t.dataset.tabTarget === panel.id);
      if (matchingTab) selectTab(matchingTab);
    });
  });

  // Render real QRIS matrix when NandarPay provides qr_string
  const qrisContainer = document.getElementById('qris-qr-container');
  if (qrisContainer && qrisContainer.dataset.qrString) {
    const renderLiveQris = () => {
      if (typeof window.qrcode !== 'function') return false;
      try {
        const qr = window.qrcode(0, 'M');
        qr.addData(qrisContainer.dataset.qrString);
        qr.make();
        const count = qr.getModuleCount();
        const pad = 2;
        const size = count + pad * 2;
        const svgNS = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(svgNS, 'svg');
        svg.setAttribute('viewBox', `0 0 ${size} ${size}`);
        svg.setAttribute('width', '210');
        svg.setAttribute('height', '210');
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', 'Kode QRIS resmi siap scan');

        const bg = document.createElementNS(svgNS, 'rect');
        bg.setAttribute('width', String(size));
        bg.setAttribute('height', String(size));
        bg.setAttribute('fill', '#ffffff');
        svg.append(bg);

        let d = '';
        for (let r = 0; r < count; r += 1) {
          for (let c = 0; c < count; c += 1) {
            if (qr.isDark(r, c)) {
              d += `M${c + pad} ${r + pad}h1v1h-1z`;
            }
          }
        }
        const path = document.createElementNS(svgNS, 'path');
        path.setAttribute('d', d);
        path.setAttribute('fill', '#072D44');
        svg.append(path);

        qrisContainer.replaceChildren(svg);
        return true;
      } catch {
        return false;
      }
    };
    if (!renderLiveQris()) {
      window.addEventListener('load', renderLiveQris, { once: true });
    }
  }

  // Auto-poll NandarPay status every 5 seconds while waiting for QRIS scan
  const pollEl = document.querySelector('[data-poll-invoice]');
  if (pollEl) {
    const inv = pollEl.dataset.pollInvoice;
    const base = window.DIGIVA_BASE || '/';
    setInterval(async () => {
      try {
        const res = await fetch(`${base}api.php?action=check_payment&inv=${encodeURIComponent(inv)}`);
        if (!res.ok) return;
        const data = await res.json();
        if (data.status && data.status !== 'pending') {
          window.location.reload();
        }
      } catch {
        // Ignore transient offline errors
      }
    }, 5200);
  }
})();
