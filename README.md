# DIGIVA.ID — Digital Product Storefront

Website jualan produk digital otomatis 24 jam berbasis **PHP 8.2 + SQLite** dengan tampilan **Desktop (Laptop)** dan **Mobile (HP)** yang berbeda secara struktural, terintegrasi dengan **PremiumKita Open API** dan **NandarPay Payment Gateway v2**.

## Fitur Utama

- **Desain Adaptif Laptop vs HP:**
  - **Laptop (`>= 1024px`):** *Floating frosted-glass pill navbar* yang menyusut menjadi orb `54px` saat scroll, hero banner 3 slide dengan tile 3D, *Trust Strip*, kolom pencarian terpisah, dan grid produk 4–6 kolom.
  - **HP (`< 1024px`):** Sticky header yang berubah menjadi kapsul mengambang (*scroll-morph*), kategori geser horizontal, grid produk 2 kolom, *floating curved-notch bottom dock* dengan orb WhatsApp CS, serta *sticky bottom action bar* di halaman detail produk dan checkout.
- **Integrasi PremiumKita Open API (`https://premiumkita.com/app/`):**
  - Sinkronisasi katalog (`get_ready_product` / `get_all_product`) dengan markup persentase & nominal tetap.
  - Order otomatis (`koneksi=order`) dengan token `ref_id` anti-duplikat setelah pembayaran lunas.
- **Integrasi NandarPay Payment Gateway v2 (`https://pay.nandarstore.my.id`):**
  - Pembuatan tagihan QRIS otomatis (`/api/v2/create-transaction/{slug}/{order_id}`), polling status transaksi, dukungan mode Sandbox & Live, serta endpoint webhook [`webhook.php`](webhook.php) dengan verifikasi HMAC-SHA256 `X-PG-Signature`.
- **Portal Admin Terpisah (`admin-login.php` & `admin.php`):**
  - Halaman login khusus admin, pengelolaan produk & pesanan, pengaturan API Key / Secret Key, log webhook, dan ganti kredensial admin.

## Menjalankan Secara Lokal

Pastikan PHP 8.1+ dengan ekstensi `pdo_sqlite` dan `curl` aktif:

```bash
php -S 127.0.0.1:8080
```

Atau letakkan di `htdocs` XAMPP lalu buka melalui `http://localhost/jualan/`.

- **Halaman Toko:** `/`
- **Login Admin:** `/admin-login.php` (default awal: `admin` / `digiva2026`, segera ubah di tab *Pengaturan & Keamanan*)
- **Webhook Callback NandarPay:** `/webhook.php`
