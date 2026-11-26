<?php
/**
 * Footer bersama (Desktop 3-kolom vs Mobile 1-kolom terpusat)
 * serta Mobile Curved-Notch Floating Bottom Dock dengan tombol orb WhatsApp CS di tengah.
 */

declare(strict_types=1);

$settings = $settings ?? get_settings();
$storeName = $settings['store_name'] ?? 'DIGIVA.ID';
$storeTagline = $settings['store_tagline'] ?? 'Digital Solutions';
$poweredBy = $settings['powered_by'] ?? 'Digiva Cloud Engine';
$waNumber = $settings['whatsapp_cs'] ?? '6285198090571';
$waCsUrl = wa_link($waNumber, "Halo CS {$storeName}, saya butuh bantuan pemesanan.");
$activePage = $activePage ?? 'home';
$showDock = $showDock ?? true;
$extraFooterScript = $extraFooterScript ?? '';
?>
  </main>

  <footer class="market-footer shell-footer">
    <div class="footer-identity">
      <a class="footer-brand-row" href="<?= e(app_url('index.php')) ?>">
        <span class="footer-monogram-wrap"><?= render_brand_monogram('footer-monogram') ?></span>
        <span class="footer-brand-meta">
          <strong><?= e($storeName) ?></strong>
          <span><?= e($storeTagline) ?></span>
        </span>
      </a>
      <p class="footer-powered">
        Powered by <span class="powered-badge"><?= e($poweredBy) ?></span>
      </p>
    </div>

    <nav class="footer-nav" aria-label="Tautan bantuan dan kebijakan">
      <a href="<?= e(app_url('bantuan.php')) ?>">Bantuan</a>
      <a href="<?= e(app_url('syarat.php')) ?>">Syarat</a>
      <a href="<?= e(app_url('privasi.php')) ?>">Privasi</a>
    </nav>

    <div class="market-footer-payment">
      <span>Bayar dengan</span>
      <?= render_ui_icon('qris', 'qris-badge-svg') ?>
    </div>
  </footer>

  <?php if ($showDock): ?>
  <!-- HP / MOBILE (<1024px): Floating Bottom Dock dengan lekukan lengkung (notch) & orb WhatsApp -->
  <nav class="market-mobile-dock" aria-label="Navigasi seluler">
    <svg class="dock-bg-svg" viewBox="0 0 400 76" preserveAspectRatio="none" aria-hidden="true">
      <path
        d="M28 10 H148 C163 10 170 43 200 43 C230 43 237 10 252 10 H372 C387.5 10 398 20.5 398 36 V48 C398 63.5 387.5 74 372 74 H28 C12.5 74 2 63.5 2 48 V36 C2 20.5 12.5 10 28 10 Z"
        fill="rgba(255,255,255,0.96)"
        stroke="rgba(226,232,240,0.95)"
        stroke-width="1.2"
      />
    </svg>

    <div class="dock-items">
      <a
        href="<?= e(app_url('index.php')) ?>"
        class="dock-tab"
        <?= $activePage === 'home' ? 'aria-current="page"' : '' ?>
      >
        <?= render_ui_icon('home', 'dock-icon') ?>
        <span>Beranda</span>
      </a>

      <a
        href="<?= e(app_url('katalog.php')) ?>"
        class="dock-tab"
        <?= $activePage === 'katalog' ? 'aria-current="page"' : '' ?>
      >
        <?= render_ui_icon('grid', 'dock-icon') ?>
        <span>Katalog</span>
      </a>

      <a
        href="<?= e($waCsUrl) ?>"
        class="whatsapp-orb"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Hubungi WhatsApp CS"
      >
        <?= render_ui_icon('whatsapp', 'dock-wa-svg') ?>
      </a>

      <a
        href="<?= e(app_url('lacak.php')) ?>"
        class="dock-tab"
        <?= $activePage === 'lacak' ? 'aria-current="page"' : '' ?>
      >
        <?= render_ui_icon('receipt', 'dock-icon') ?>
        <span>Lacak</span>
      </a>

      <a
        href="<?= e(app_url('bantuan.php#cara-beli')) ?>"
        class="dock-tab"
        <?= $activePage === 'bantuan' ? 'aria-current="page"' : '' ?>
      >
        <?= render_ui_icon('book', 'dock-icon') ?>
        <span>Cara Beli</span>
      </a>
    </div>
  </nav>
  <?php endif; ?>

  <div class="toast-stack" id="toast-stack" role="status" aria-live="polite"></div>

  <script>
    window.DIGIVA_BASE = <?= json_encode(app_url(), JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
  <?php if ($extraFooterScript !== ''): ?>
    <?= $extraFooterScript ?>
  <?php endif; ?>
</body>
</html>
