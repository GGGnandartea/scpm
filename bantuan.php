<?php
/**
 * Halaman Cara Beli & Pusat Bantuan (/bantuan.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brand_icons.php';

$settings = get_settings();
$storeName = $settings['store_name'] ?? 'DIGIVA.ID';
$waNumber = $settings['whatsapp_cs'] ?? '6285198090571';

$pageTitle = 'Cara Beli & Pusat Bantuan — ' . $storeName;
$activePage = 'bantuan';
$showDock = true;
$showContextHeader = true;
$contextLabel = 'Cara Beli & Bantuan';
$backUrl = app_url('index.php');

require __DIR__ . '/includes/header.php';
?>

<section class="ocean-sub-banner">
  <div class="sub-banner-eyebrow">CARA BELI &amp; PUSAT BANTUAN</div>
  <h1>Ada kami di setiap langkah.</h1>
  <p style="margin-bottom:16px;">Dari pilih paket hingga akses aktif. Semuanya lebih mudah.</p>
  <a
    href="<?= e(app_url('lacak.php')) ?>"
    style="display:inline-flex; align-items:center; gap:8px; padding:9px 15px; border-radius:10px; background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.28); color:#fff; font-size:12.5px; font-weight:700;"
  >
    <?= render_ui_icon('receipt', 'badge-icon-xs') ?>
    <span>Lacak Pesanan</span>
  </a>
</section>

<section id="cara-beli" aria-labelledby="steps-heading" style="margin-bottom:32px;">
  <div class="related-head">
    <h2 id="steps-heading">Tiga langkah menuju premium.</h2>
    <a href="<?= e(app_url('katalog.php')) ?>">Buka katalog &rarr;</a>
  </div>

  <div class="steps-3-grid">
    <article class="step-info-card">
      <div class="step-num-box">01</div>
      <h3 style="font-size:16px; font-weight:800; color:var(--navy-deep); margin-bottom:8px;">Pilih premium-mu</h3>
      <p style="margin:0; font-size:13.5px; color:var(--ink-muted);">Buka katalog, pilih produk dan paket durasi yang sesuai, lalu ketuk Beli Sekarang.</p>
    </article>

    <article class="step-info-card">
      <div class="step-num-box">02</div>
      <h3 style="font-size:16px; font-weight:800; color:var(--navy-deep); margin-bottom:8px;">Isi detail &amp; bayar QRIS</h3>
      <p style="margin:0; font-size:13.5px; color:var(--ink-muted);">Gunakan kontak WhatsApp dan email aktif. Bayar QRIS sebelum batas waktu 20 menit berakhir.</p>
    </article>

    <article class="step-info-card">
      <div class="step-num-box">03</div>
      <h3 style="font-size:16px; font-weight:800; color:var(--navy-deep); margin-bottom:8px;">Akses siap digunakan</h3>
      <p style="margin:0; font-size:13.5px; color:var(--ink-muted);">Detail akses langsung tampil setelah pembayaran terkonfirmasi. Paket manual dibantu CS dengan cepat.</p>
    </article>
  </div>
</section>

<section class="bantuan-bottom-grid" aria-labelledby="faq-heading">
  <div>
    <h2 id="faq-heading" style="font-size:20px; font-weight:800; color:var(--navy-deep); margin-bottom:14px;">Jawaban yang kamu butuhkan.</h2>
    <div class="accordion-stack">
      <details class="ocean-details" open>
        <summary>Sudah bayar, status belum berubah?</summary>
        <div class="details-body">
          Tunggu 10-30 detik lalu muat ulang halaman invoice Anda. Jika menggunakan pembayaran QRIS dan status masih menunggu setelah 2 menit, kirimkan bukti transfer bersama nomor invoice ke WhatsApp CS kami.
        </div>
      </details>

      <details class="ocean-details">
        <summary>Bagaimana cara mendapatkan detail akun?</summary>
        <div class="details-body">
          Detail akun langsung muncul di halaman invoice begitu status pembayaran berubah menjadi Lunas. Anda juga bisa membukanya kapan saja melalui menu <strong>Lacak Pesanan</strong> menggunakan nomor invoice/WhatsApp dan email pembelian.
        </div>
      </details>

      <details class="ocean-details">
        <summary>Ada kendala selama masa aktif?</summary>
        <div class="details-body">
          Tim dukungan kami siap membantu selama masa aktif paket. Pastikan Anda tidak mengubah email, password, nama profil, atau memakai VPN di luar ketentuan agar garansi tetap berlaku penuh.
        </div>
      </details>
    </div>
  </div>

  <aside class="step-card">
    <h2 style="font-size:18px; font-weight:800; color:var(--navy-deep); margin-bottom:8px;">Kami siap mendengarkan.</h2>
    <p style="margin:0 0 16px; font-size:13px; color:var(--ink-muted);">Sertakan nomor invoice agar tim bisa memeriksa pesananmu dengan lebih cepat.</p>

    <div class="field" style="margin-bottom:14px;">
      <label for="cs-invoice-input">Nomor invoice (jika sudah membeli)</label>
      <div class="input-box">
        <input id="cs-invoice-input" type="text" placeholder="NTR-...">
      </div>
    </div>

    <a
      id="cs-whatsapp-btn"
      class="btn-primary-ocean"
      style="width:100%;"
      href="<?= e(wa_link($waNumber, "Halo CS {$storeName}, saya butuh bantuan pesanan.")) ?>"
      data-wa-base="<?= e(normalize_wa($waNumber)) ?>"
      data-store-name="<?= e($storeName) ?>"
      target="_blank"
      rel="noopener noreferrer"
    >
      <?= render_ui_icon('whatsapp-outline', 'wa-icon-sm') ?>
      <span>Hubungi CS WhatsApp</span>
    </a>
  </aside>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
