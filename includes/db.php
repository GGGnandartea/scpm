<?php
/**
 * Inisialisasi koneksi SQLite, pembuatan skema tabel otomatis,
 * seeding produk digital + varian + kode promo + pengaturan toko,
 * serta query helper untuk katalog, pesanan, dan admin.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/api_clients.php';

/**
 * Mengembalikan instance PDO SQLite tunggal dan memastikan skema + seed siap.
 */
function get_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dataDir = dirname(DB_PATH);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    $isNew = !file_exists(DB_PATH);
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA journal_mode = WAL;');

    init_schema($pdo);
    if ($isNew || (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        seed_initial_data($pdo);
    }

    return $pdo;
}

/**
 * Membuat skema tabel SQLite jika belum tersedia.
 */
function init_schema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_slug TEXT NOT NULL,
            brand_key TEXT NOT NULL,
            brand_label TEXT NOT NULL,
            title TEXT NOT NULL,
            variant_label TEXT NOT NULL,
            category TEXT NOT NULL,
            price INTEGER NOT NULL,
            stock INTEGER NOT NULL DEFAULT 0,
            sold_count INTEGER NOT NULL DEFAULT 0,
            delivery_type TEXT NOT NULL DEFAULT 'instant',
            is_featured INTEGER NOT NULL DEFAULT 0,
            description TEXT NOT NULL,
            account_template TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS promos (
            code TEXT PRIMARY KEY,
            type TEXT NOT NULL CHECK(type IN ('percent', 'fixed')),
            value INTEGER NOT NULL,
            max_discount INTEGER NOT NULL DEFAULT 0,
            min_total INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1
        );

        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice TEXT NOT NULL UNIQUE,
            product_id INTEGER NOT NULL,
            product_title TEXT NOT NULL,
            variant_label TEXT NOT NULL,
            quantity INTEGER NOT NULL,
            unit_price INTEGER NOT NULL,
            subtotal INTEGER NOT NULL,
            discount INTEGER NOT NULL DEFAULT 0,
            fee INTEGER NOT NULL DEFAULT 0,
            total INTEGER NOT NULL,
            promo_code TEXT DEFAULT '',
            customer_name TEXT NOT NULL,
            customer_wa TEXT NOT NULL,
            customer_email TEXT NOT NULL,
            payment_method TEXT NOT NULL DEFAULT 'QRIS',
            status TEXT NOT NULL DEFAULT 'pending',
            delivered_data TEXT NOT NULL DEFAULT '',
            expires_at TEXT NOT NULL,
            paid_at TEXT DEFAULT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id)
        );

        CREATE TABLE IF NOT EXISTS webhook_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id TEXT NOT NULL DEFAULT '',
            txn_id TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT '',
            http_code INTEGER NOT NULL DEFAULT 200,
            signature_valid INTEGER NOT NULL DEFAULT 0,
            payload TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    SQL);

    migrate_schema($pdo);
}

/**
 * Menambahkan kolom integrasi API ke database yang sudah ada secara aman.
 */
function migrate_schema(PDO $pdo): void
{
    $ensureColumns = static function (string $table, array $columns) use ($pdo): void {
        $existing = [];
        foreach ($pdo->query("PRAGMA table_info({$table})")->fetchAll() as $col) {
            $existing[$col['name']] = true;
        }
        foreach ($columns as $name => $definition) {
            if (!isset($existing[$name])) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$name} {$definition}");
            }
        }
    };

    $ensureColumns('products', [
        'provider_source' => "TEXT NOT NULL DEFAULT 'local'",
        'provider_product_id' => "TEXT NOT NULL DEFAULT ''",
        'provider_cost' => 'INTEGER NOT NULL DEFAULT 0',
        'icon_url' => "TEXT NOT NULL DEFAULT ''",
    ]);

    $ensureColumns('orders', [
        'pg_txn_id' => "TEXT NOT NULL DEFAULT ''",
        'pg_payment_url' => "TEXT NOT NULL DEFAULT ''",
        'pg_qr_string' => "TEXT NOT NULL DEFAULT ''",
        'pg_unique_code' => 'INTEGER NOT NULL DEFAULT 0',
        'pg_is_sandbox' => 'INTEGER NOT NULL DEFAULT 0',
        'last_pg_poll_at' => 'TEXT DEFAULT NULL',
        'pk_ref_id' => "TEXT NOT NULL DEFAULT ''",
        'pk_invoice_number' => "TEXT NOT NULL DEFAULT ''",
        'pk_status' => "TEXT NOT NULL DEFAULT ''",
        'pk_error' => "TEXT NOT NULL DEFAULT ''",
    ]);

    $defaultSettings = [
        'admin_username' => ADMIN_DEFAULT_USER,
        'pk_enabled' => '0',
        'pk_base_url' => 'https://premiumkita.com/app/',
        'pk_id_user' => '',
        'pk_key_user' => '',
        'pk_markup_percent' => '0',
        'pk_markup_fixed' => '0',
        'pg_enabled' => '0',
        'pg_base_url' => 'https://pay.nandarstore.my.id',
        'pg_slug' => '',
        'pg_api_key' => '',
        'pg_webhook_secret' => '',
        'pg_is_sandbox' => '1',
    ];
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    foreach ($defaultSettings as $k => $v) {
        $stmt->execute([$k, $v]);
    }
}

/**
 * Mengisi data awal toko: pengaturan, 28 SKU produk digital lintas 5 kategori,
 * kode promo aktif, dan 1 contoh order demo untuk pengujian Lacak Pesanan.
 */
function seed_initial_data(PDO $pdo): void
{
    $settings = [
        'store_name' => 'DIGIVA.ID',
        'store_tagline' => 'Digital Solutions',
        'powered_by' => 'Digiva Cloud Engine',
        'whatsapp_cs' => '6285198090571',
        'admin_password_hash' => password_hash(ADMIN_DEFAULT_PASS, PASSWORD_DEFAULT),
    ];

    $stmtSetting = $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
    foreach ($settings as $k => $v) {
        $stmtSetting->execute([$k, $v]);
    }

    $products = [
        // 1. Streaming - Netflix Group (4 varian)
        [
            'group_slug' => 'netflix',
            'brand_key' => 'netflix',
            'brand_label' => 'Netflix',
            'title' => 'NETFLIX 1 BULAN ANTILIMIT',
            'variant_label' => '1 BULAN',
            'category' => 'Streaming',
            'price' => 25500,
            'stock' => 93,
            'sold_count' => 168,
            'delivery_type' => 'instant',
            'is_featured' => 1,
            'description' => "Akun Netflix Premium 4K UHD Sharing 1 Profile (Anti-Limit / Tanpa Household).\n- Login cepat via link/kode aktivasi resmi tanpa VPN\n- Kualitas gambar hingga Ultra HD 4K & Dolby Atmos\n- Berlaku untuk HP Android, Laptop, Windows/Mac, dan Smart TV resmi\n- Dilarang mengganti password, email, nama profil, atau menyalakan VPN\n- Garansi FULL 23-30 hari sesuai syarat & ketentuan aktif",
            'account_template' => "Email Login: stream-vip{RAND}@digiva.id\nLink Aktivasi: https://portal.digiva.id/redeem/{INV}\nProfil: Slot #{SLOT} (Jangan ubah nama profil/PIN)",
        ],
        [
            'group_slug' => 'netflix',
            'brand_key' => 'netflix',
            'brand_label' => 'Netflix',
            'title' => 'NETFLIX 20 HARI HEMAT',
            'variant_label' => '20 DAY',
            'category' => 'Streaming',
            'price' => 18000,
            'stock' => 14,
            'sold_count' => 84,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Paket Netflix Premium 20 hari hemat untuk 1 perangkat.\n- Kualitas Ultra HD 4K\n- Cocok untuk maraton serial favorit tanpa komitmen bulanan\n- Garansi penuh selama masa aktif 20 hari",
            'account_template' => "Email Login: nf20-{RAND}@digiva.id\nPIN Profil: 2026\nProfil: Slot #{SLOT}",
        ],
        [
            'group_slug' => 'netflix',
            'brand_key' => 'netflix',
            'brand_label' => 'Netflix',
            'title' => 'NETFLIX 2 BULAN PRIVATE PROFIL',
            'variant_label' => '2 BULAN',
            'category' => 'Streaming',
            'price' => 49000,
            'stock' => 42,
            'sold_count' => 91,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Netflix 2 bulan langsung tanpa perlu perpanjang di tengah jalan.\n- 1 Profile eksklusif untuk 1 device\n- Dukungan CS aktif 24 jam selama masa langganan",
            'account_template' => "Email: nf2m-{RAND}@digiva.id\nAkses Portal: https://portal.digiva.id/redeem/{INV}\nProfil: VIP-{SLOT}",
        ],
        [
            'group_slug' => 'netflix',
            'brand_key' => 'netflix',
            'brand_label' => 'Netflix',
            'title' => 'NETFLIX 3 BULAN SUPER SAVER',
            'variant_label' => '3 BULAN',
            'category' => 'Streaming',
            'price' => 72000,
            'stock' => 0,
            'sold_count' => 112,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Paket jangka panjang 3 bulan dengan harga paling hemat per hari.\n- Kualitas 4K UHD\n- Full garansi aktif",
            'account_template' => "Email: nf3m-{RAND}@digiva.id\nKode: {INV}",
        ],

        // 2. Desain & Editing - Adobe & Alight Motion & Canva & CapCut
        [
            'group_slug' => 'adobe-express',
            'brand_key' => 'adobe',
            'brand_label' => 'Adobe',
            'title' => 'ADOBE EXPRESS - 4 BULAN',
            'variant_label' => '4 BULAN',
            'category' => 'Desain & Editing',
            'price' => 10500,
            'stock' => 32,
            'sold_count' => 240,
            'delivery_type' => 'instant',
            'is_featured' => 1,
            'description' => "Adobe Express Premium 4 Bulan siap pakai di web, iOS, dan Android.\n- Akses penuh template premium, hapus background instan, dan Adobe Stock\n- Bisa menggunakan email pribadi atau akun siap pakai",
            'account_template' => "Link Invite Tim: https://adobe.digiva.id/join/{INV}\nStatus: Lisensi 4 Bulan Aktif",
        ],
        [
            'group_slug' => 'adobe-lightroom',
            'brand_key' => 'adobe',
            'brand_label' => 'Adobe',
            'title' => 'ADOBE LIGHTROOM - 1 TAHUN',
            'variant_label' => '1 TAHUN',
            'category' => 'Desain & Editing',
            'price' => 11500,
            'stock' => 28,
            'sold_count' => 174,
            'delivery_type' => 'instant',
            'is_featured' => 1,
            'description' => "Adobe Lightroom Premium 1 Tahun untuk edit foto RAW, preset pro, dan selective masking.\n- Unlock semua fitur masking, healing, dan cloud sync",
            'account_template' => "Akun Lightroom: lr-{RAND}@digiva.id\nPass: Light#{RAND}\nPanduan: Login via aplikasi Lightroom Mobile/Desktop",
        ],
        [
            'group_slug' => 'alight-motion',
            'brand_key' => 'alight',
            'brand_label' => 'Alight',
            'title' => 'ALIGHT MOTION PRIVATE - 1 TAHUN IOS ANDRO',
            'variant_label' => '1 TAHUN PRIVATE',
            'category' => 'Desain & Editing',
            'price' => 3150,
            'stock' => 24,
            'sold_count' => 265,
            'delivery_type' => 'instant',
            'is_featured' => 1,
            'description' => "Alight Motion Pro Private 1 Tahun untuk Android dan iOS.\n- Tanpa watermark, ekspor 4K 60fps, XML import aktif\n- Akun private khusus untuk kamu sendiri",
            'account_template' => "Email Alight: am-{RAND}@digiva.id\nPassword: AMPro*{RAND}\nBerlaku iOS & Android",
        ],
        [
            'group_slug' => 'alight-motion',
            'brand_key' => 'alight',
            'brand_label' => 'Alightmotion',
            'title' => 'ALIGHTMOTION PRO 1 TAHUN',
            'variant_label' => '1 TAHUN SHARING',
            'category' => 'Desain & Editing',
            'price' => 3380,
            'stock' => 20,
            'sold_count' => 115,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Alight Motion Pro 1 Tahun paket ekonomis.\n- Semua efek pro, font custom, dan export tanpa watermark terbuka penuh",
            'account_template' => "Email: ams-{RAND}@digiva.id\nPass: Share#{RAND}",
        ],
        [
            'group_slug' => 'canva',
            'brand_key' => 'canva',
            'brand_label' => 'Canva',
            'title' => 'CANVA 1 BULAN INVITE',
            'variant_label' => '1 BULAN',
            'category' => 'Desain & Editing',
            'price' => 7000,
            'stock' => 65,
            'sold_count' => 318,
            'delivery_type' => 'instant',
            'is_featured' => 1,
            'description' => "Canva Pro langsung aktif di email pribadimu via link invite tim resmi.\n- Desain lama tetap aman di akun pribadimu\n- Unlock Magic Resize, Background Remover, Brand Kit, dan jutaan elemen Pro",
            'account_template' => "Link Invite Canva Pro: https://canva.digiva.id/invite/{INV}\nPetunjuk: Klik link dengan kondisi sudah login akun Canva pribadi Anda.",
        ],
        [
            'group_slug' => 'canva',
            'brand_key' => 'canva',
            'brand_label' => 'Canva',
            'title' => 'CANVA PRO 1 TAHUN GARANSI',
            'variant_label' => '1 TAHUN',
            'category' => 'Desain & Editing',
            'price' => 22000,
            'stock' => 40,
            'sold_count' => 195,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Canva Pro 1 Tahun penuh bergaransi untuk kreator konten, pelajar, dan UMKM.\n- Semua fitur Magic Studio & Pro aset terbuka",
            'account_template' => "Link Invite 1 Tahun: https://canva.digiva.id/vip/{INV}",
        ],
        [
            'group_slug' => 'capcut',
            'brand_key' => 'capcut',
            'brand_label' => 'CapCut',
            'title' => 'CAPCUT PRO 1 BULAN PRIVATE',
            'variant_label' => '1 BULAN',
            'category' => 'Desain & Editing',
            'price' => 15500,
            'stock' => 38,
            'sold_count' => 210,
            'delivery_type' => 'instant',
            'is_featured' => 1,
            'description' => "CapCut Pro 1 Bulan untuk Mobile (iOS/Android) dan Desktop (Windows/Mac).\n- Bebas pakai semua efek Pro, Auto Captions, AI Remove Background, dan ekspor 4K HDR",
            'account_template' => "Email CapCut: cc-{RAND}@digiva.id\nPassword: CapPro#{RAND}",
        ],
        [
            'group_slug' => 'capcut',
            'brand_key' => 'capcut',
            'brand_label' => 'CapCut',
            'title' => 'CAPCUT PRO 1 TAHUN SHARING',
            'variant_label' => '1 TAHUN',
            'category' => 'Desain & Editing',
            'price' => 59000,
            'stock' => 15,
            'sold_count' => 76,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Langganan CapCut Pro 1 tahun penuh dengan biaya super hemat.\n- Bisa digunakan di 1 device utama (HP atau PC)",
            'account_template' => "Email: cc1y-{RAND}@digiva.id\nPass: Pro1Y#{RAND}",
        ],
        [
            'group_slug' => 'beautyplus',
            'brand_key' => 'beauty',
            'brand_label' => 'Beauty',
            'title' => 'BEAUTY PLUS PRIVATE - 7 DAYS',
            'variant_label' => '7 HARI',
            'category' => 'Desain & Editing',
            'price' => 9300,
            'stock' => 19,
            'sold_count' => 54,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "BeautyPlus VIP Private 7 Hari untuk retouch foto & filter AI kualitas studio.\n- Bebas iklan dan akses seluruh filter VIP",
            'account_template' => "Akun BeautyPlus: bp-{RAND}@digiva.id\nPass: Beauty#{RAND}",
        ],

        // 3. Musik - Apple Music & Spotify & YouTube Music
        [
            'group_slug' => 'apple-music',
            'brand_key' => 'apple',
            'brand_label' => 'Apple',
            'title' => 'APPLE MUSIC ANDRO - 1 BULAN HEAD',
            'variant_label' => '1 BULAN HEAD',
            'category' => 'Musik',
            'price' => 10000,
            'stock' => 0,
            'sold_count' => 264,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Apple Music 1 Bulan Head Family untuk perangkat Android & Apple.\n- Audio Lossless & Spatial Audio Dolby Atmos",
            'account_template' => "Apple ID: amhead-{RAND}@digiva.id\nPass: Music#{RAND}",
        ],
        [
            'group_slug' => 'apple-music',
            'brand_key' => 'apple',
            'brand_label' => 'Apple',
            'title' => 'APPLE MUSIC IOS/ANDRO - 1 BULAN INVITED',
            'variant_label' => '1 BULAN INVITE',
            'category' => 'Musik',
            'price' => 8000,
            'stock' => 0,
            'sold_count' => 265,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Apple Music 1 Bulan via invite Family langsung ke Apple ID kamu.",
            'account_template' => "Link Invite Family: https://music.apple.com/family/{INV}",
        ],
        [
            'group_slug' => 'spotify',
            'brand_key' => 'spotify',
            'brand_label' => 'Spotify',
            'title' => 'SPOTIFY PREMIUM 1 BULAN INDPLAN',
            'variant_label' => '1 BULAN',
            'category' => 'Musik',
            'price' => 17500,
            'stock' => 54,
            'sold_count' => 342,
            'delivery_type' => 'instant',
            'is_featured' => 1,
            'description' => "Spotify Premium Individual 1 Bulan bebas iklan & download offline sepuasnya.\n- Bisa pakai akun baru siap pakai atau perpanjang akun pribadi\n- Kualitas audio Very High 320kbps",
            'account_template' => "Email Spotify: sp-{RAND}@digiva.id\nPassword: Spot#{RAND}\nRegion: Indonesia",
        ],
        [
            'group_slug' => 'spotify',
            'brand_key' => 'spotify',
            'brand_label' => 'Spotify',
            'title' => 'SPOTIFY PREMIUM 3 BULAN',
            'variant_label' => '3 BULAN',
            'category' => 'Musik',
            'price' => 45000,
            'stock' => 22,
            'sold_count' => 129,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Spotify Premium 3 Bulan penuh tanpa iklan & skip lagu tak terbatas.",
            'account_template' => "Email Spotify: sp3m-{RAND}@digiva.id\nPassword: Spot3M#{RAND}",
        ],

        // 4. Streaming Tambahan (YouTube, Disney+, Viu, Bstation, WeTV)
        [
            'group_slug' => 'youtube',
            'brand_key' => 'youtube',
            'brand_label' => 'YouTube',
            'title' => 'YOUTUBE PREMIUM 1 BULAN FAMILY',
            'variant_label' => '1 BULAN',
            'category' => 'Streaming',
            'price' => 9500,
            'stock' => 77,
            'sold_count' => 412,
            'delivery_type' => 'instant',
            'is_featured' => 1,
            'description' => "YouTube Premium + YouTube Music tanpa iklan 1 bulan via invite Gmail pribadi.\n- Putar di background & download video offline\n- Legal 100% melalui undangan Google Family resmi",
            'account_template' => "Link Google Family Invite: https://families.google.com/join/{INV}\nCatatan: Terima undangan menggunakan email aktif Anda.",
        ],
        [
            'group_slug' => 'youtube',
            'brand_key' => 'youtube',
            'brand_label' => 'YouTube',
            'title' => 'YOUTUBE PREMIUM 3 BULAN INDIVIDUAL',
            'variant_label' => '3 BULAN',
            'category' => 'Streaming',
            'price' => 27000,
            'stock' => 31,
            'sold_count' => 154,
            'delivery_type' => 'manual',
            'is_featured' => 0,
            'description' => "YouTube Premium Individual 3 bulan tanpa batas family.\n- Diproses admin maksimal 1x15 menit jam kerja",
            'account_template' => "Email YT: yt3m-{RAND}@digiva.id\nPass: YTPro#{RAND}",
        ],
        [
            'group_slug' => 'bstation',
            'brand_key' => 'bstation',
            'brand_label' => 'Bstation',
            'title' => 'BSTATION SHARING - 1 BULAN',
            'variant_label' => '1 BULAN',
            'category' => 'Streaming',
            'price' => 8300,
            'stock' => 45,
            'sold_count' => 136,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Bstation / Bilibili VIP 1 Bulan untuk nonton anime simulcast HD-4K tanpa iklan.",
            'account_template' => "Email Bstation: bs-{RAND}@digiva.id\nPass: Anime#{RAND}",
        ],
        [
            'group_slug' => 'disney',
            'brand_key' => 'disney',
            'brand_label' => 'Disney+',
            'title' => 'DISNEY+ HOTSTAR PREMIUM 1 BULAN',
            'variant_label' => '1 BULAN',
            'category' => 'Streaming',
            'price' => 24000,
            'stock' => 26,
            'sold_count' => 188,
            'delivery_type' => 'instant',
            'is_featured' => 1,
            'description' => "Disney+ Hotstar Premium 1 Bulan.\n- Koleksi lengkap Marvel, Pixar, Star Wars, National Geographic, & drama Korea eksklusif",
            'account_template' => "No/Email Login: dsn-{RAND}@digiva.id\nLink OTP Live: https://portal.digiva.id/otp/{INV}",
        ],
        [
            'group_slug' => 'viu',
            'brand_key' => 'viu',
            'brand_label' => 'Viu',
            'title' => 'VIU PREMIUM PRIVATE - 1 TAHUN',
            'variant_label' => '1 TAHUN',
            'category' => 'Streaming',
            'price' => 14000,
            'stock' => 58,
            'sold_count' => 221,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Viu Premium Private 1 Tahun tanpa iklan.\n- Download drama & variety show Korea kualitas Full HD",
            'account_template' => "Email Viu: viu-{RAND}@digiva.id\nPass: ViuVIP#{RAND}",
        ],

        // 5. AI & Produktivitas - ChatGPT, Gemini, Claude, Microsoft, Zoom, Grammarly, VPN, CamScanner
        [
            'group_slug' => 'openai',
            'brand_key' => 'openai',
            'brand_label' => 'ChatGPT',
            'title' => 'CHATGPT PLUS TEAM - 1 BULAN',
            'variant_label' => '1 BULAN SHARING',
            'category' => 'AI & Produktivitas',
            'price' => 35000,
            'stock' => 41,
            'sold_count' => 389,
            'delivery_type' => 'instant',
            'is_featured' => 1,
            'description' => "ChatGPT Plus / Team 1 Bulan akses penuh model flagship, Deep Research, analisis file PDF/Excel, dan generate gambar.\n- Limit sangat longgar, respons cepat\n- Full garansi selama masa aktif",
            'account_template' => "Email OpenAI: gpt-{RAND}@digiva.id\nPassword: GPTPlus*{RAND}\n2FA Backup Code: {INV}",
        ],
        [
            'group_slug' => 'openai',
            'brand_key' => 'openai',
            'brand_label' => 'ChatGPT',
            'title' => 'CHATGPT PLUS PRIVATE INVITE - 1 BULAN',
            'variant_label' => '1 BULAN PRIVATE',
            'category' => 'AI & Produktivitas',
            'price' => 115000,
            'stock' => 12,
            'sold_count' => 94,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Invite Workspace ChatGPT Team langsung ke email pribadi Anda.\n- Riwayat chat 100% private milik Anda sendiri\n- Tidak bercampur dengan pengguna lain",
            'account_template' => "Link Workspace Invite: https://chatgpt.com/invite/{INV}\nLogin dengan email pribadi saat mendaftar checkout.",
        ],
        [
            'group_slug' => 'gemini',
            'brand_key' => 'gemini',
            'brand_label' => 'Gemini',
            'title' => 'GEMINI AI PRO + 2TB GOOGLE ONE - 1 BULAN',
            'variant_label' => '1 BULAN',
            'category' => 'AI & Produktivitas',
            'price' => 29500,
            'stock' => 25,
            'sold_count' => 147,
            'delivery_type' => 'instant',
            'is_featured' => 1,
            'description' => "Google AI Pro (Gemini Advanced) + penyimpanan cloud Google One.\n- Terintegrasi dengan Gmail, Docs, dan Drive\n- Cocok untuk riset, coding, dan tugas kuliah",
            'account_template' => "Akun Google: gem-{RAND}@digiva.id\nPass: GemAI#{RAND}",
        ],
        [
            'group_slug' => 'claude',
            'brand_key' => 'claude',
            'brand_label' => 'Claude',
            'title' => 'CLAUDE PRO SHARING - 1 BULAN',
            'variant_label' => '1 BULAN',
            'category' => 'AI & Produktivitas',
            'price' => 48000,
            'stock' => 18,
            'sold_count' => 103,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Claude Pro 1 Bulan untuk penulisan mendalam, analisis dokumen panjang, dan pemrograman.\n- Akses Artifacts dan Projects",
            'account_template' => "Email Claude: cld-{RAND}@digiva.id\nAkses Login Portal: https://portal.digiva.id/magic/{INV}",
        ],
        [
            'group_slug' => 'microsoft',
            'brand_key' => 'microsoft',
            'brand_label' => 'Microsoft',
            'title' => 'MICROSOFT 365 + 1TB ONEDRIVE - 1 TAHUN',
            'variant_label' => '1 TAHUN',
            'category' => 'AI & Produktivitas',
            'price' => 38000,
            'stock' => 29,
            'sold_count' => 162,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Lisensi Microsoft 365 Family resmi di akun Microsoft pribadimu.\n- Word, Excel, PowerPoint, Outlook original\n- Bonus 1 TB penyimpanan OneDrive pribadi",
            'account_template' => "Link Aktivasi M365: https://account.microsoft.com/family/redeem/{INV}",
        ],
        [
            'group_slug' => 'zoom',
            'brand_key' => 'zoom',
            'brand_label' => 'Zoom',
            'title' => 'ZOOM PRO UNLIMITED MEETING - 1 BULAN',
            'variant_label' => '1 BULAN',
            'category' => 'AI & Produktivitas',
            'price' => 32000,
            'stock' => 21,
            'sold_count' => 119,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Zoom Pro 100 Peserta hingga 30 jam tanpa putus per sesi.\n- Bisa Live Streaming & Cloud Recording",
            'account_template' => "Email Host Zoom: zm-{RAND}@digiva.id\nPass: Zoom#{RAND}",
        ],
        [
            'group_slug' => 'camscanner',
            'brand_key' => 'camscanner',
            'brand_label' => 'Camscanner',
            'title' => 'CAMSCANNER VIP - 1 BULAN',
            'variant_label' => '1 BULAN',
            'category' => 'AI & Produktivitas',
            'price' => 12300,
            'stock' => 0,
            'sold_count' => 88,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "CamScanner Premium tanpa watermark untuk scan dokumen PDF HD & OCR.",
            'account_template' => "Email CS: cs-{RAND}@digiva.id\nPass: Scan#{RAND}",
        ],
        [
            'group_slug' => 'nordvpn',
            'brand_key' => 'nordvpn',
            'brand_label' => 'Avira',
            'title' => 'AVIRA PHANTOM VPN PRO - 3 BULAN',
            'variant_label' => '3 BULAN',
            'category' => 'AI & Produktivitas',
            'price' => 6300,
            'stock' => 36,
            'sold_count' => 231,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Avira VPN Pro 3 Bulan bandwidth unlimited untuk koneksi aman di Wi-Fi publik.",
            'account_template' => "Email VPN: vpn-{RAND}@digiva.id\nPass: Avira#{RAND}",
        ],

        // 6. E-Book & Lainnya
        [
            'group_slug' => 'ebook-novel',
            'brand_key' => 'ebook',
            'brand_label' => 'Paket',
            'title' => 'PAKET E-BOOK NOVEL 100+ SESUAI PAKET',
            'variant_label' => '100+ E-BOOK',
            'category' => 'E-Book & Lainnya',
            'price' => 15500,
            'stock' => 978,
            'sold_count' => 75,
            'delivery_type' => 'instant',
            'is_featured' => 0,
            'description' => "Koleksi 100+ E-Book Novel pilihan format EPUB & PDF rapi.\n- Link Google Drive akses seumur hidup\n- Bisa dibaca di HP, tablet, laptop, maupun Kindle",
            'account_template' => "Link Download Folder: https://drive.digiva.id/bundle/{INV}\nFormat: PDF & EPUB Siap Baca",
        ],
        [
            'group_slug' => 'ebook-bisnis',
            'brand_key' => 'ebook',
            'brand_label' => 'E-Book',
            'title' => 'BUNDLE E-BOOK BISNIS & DIGITAL MARKETING',
            'variant_label' => 'PAKET LENGKAP',
            'category' => 'E-Book & Lainnya',
            'price' => 19500,
            'stock' => 540,
            'sold_count' => 142,
            'delivery_type' => 'instant',
            'is_featured' => 1,
            'description' => "Kumpulan materi & template strategi bisnis digital, copywriting, dan manajemen keuangan UMKM.\n- Bonus spreadsheet tracker keuangan & kalender konten",
            'account_template' => "Akses Drive Bundle: https://drive.digiva.id/bisnis/{INV}\nPassword ZIP: digivabisnis",
        ],
    ];

    $stmtProduct = $pdo->prepare(<<<'SQL'
        INSERT INTO products (
            group_slug, brand_key, brand_label, title, variant_label, category,
            price, stock, sold_count, delivery_type, is_featured, description, account_template
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    SQL);

    foreach ($products as $p) {
        $stmtProduct->execute([
            $p['group_slug'],
            $p['brand_key'],
            $p['brand_label'],
            $p['title'],
            $p['variant_label'],
            $p['category'],
            $p['price'],
            $p['stock'],
            $p['sold_count'],
            $p['delivery_type'],
            $p['is_featured'],
            $p['description'],
            $p['account_template'],
        ]);
    }

    $promos = [
        ['HEMAT10', 'percent', 10, 15000, 10000, 1],
        ['DIGIVA5', 'fixed', 2000, 2000, 15000, 1],
        ['CREATOR15', 'percent', 15, 20000, 25000, 1],
    ];
    $stmtPromo = $pdo->prepare('INSERT OR REPLACE INTO promos (code, type, value, max_discount, min_total, active) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($promos as $promo) {
        $stmtPromo->execute($promo);
    }

    // Seed 1 order demo yang sudah lunas untuk uji coba fitur Lacak Pesanan
    $firstId = (int) $pdo->query('SELECT id FROM products ORDER BY id ASC LIMIT 1')->fetchColumn();
    $stmtOrder = $pdo->prepare(<<<'SQL'
        INSERT OR IGNORE INTO orders (
            invoice, product_id, product_title, variant_label, quantity, unit_price,
            subtotal, discount, fee, total, promo_code, customer_name, customer_wa,
            customer_email, payment_method, status, delivered_data, expires_at, paid_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    SQL);
    $stmtOrder->execute([
        'NTR-DEMO-001',
        $firstId,
        'NETFLIX 1 BULAN ANTILIMIT',
        '1 BULAN',
        1,
        25500,
        25500,
        0,
        929,
        26429,
        '',
        'Pengguna Demo',
        '6281234567890',
        'demo@digiva.id',
        'QRIS by ShopeePay',
        'paid',
        "Email Login: stream-vip402@digiva.id\nLink Aktivasi: https://portal.digiva.id/redeem/NTR-DEMO-001\nProfil: Slot #3 (Jangan ubah nama profil/PIN)",
        date('Y-m-d H:i:s', strtotime('+20 minutes')),
        date('Y-m-d H:i:s'),
    ]);
}

/**
 * Mengambil seluruh pengaturan toko sebagai associative array.
 */
function get_settings(): array
{
    $pdo = get_db();
    $rows = $pdo->query('SELECT key, value FROM settings')->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[$row['key']] = $row['value'];
    }
    return $out;
}

/**
 * Mengambil semua produk untuk halaman beranda / katalog.
 */
function get_all_products(): array
{
    $pdo = get_db();
    return $pdo->query('SELECT * FROM products ORDER BY is_featured DESC, sold_count DESC, id ASC')->fetchAll();
}

/**
 * Mengambil 1 produk berdasarkan ID.
 */
function get_product_by_id(int $id): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Mengambil daftar varian satu grup produk untuk selector "Pilih paket".
 */
function get_product_variants(string $groupSlug): array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM products WHERE group_slug = ? ORDER BY price ASC, id ASC');
    $stmt->execute([$groupSlug]);
    return $stmt->fetchAll();
}

/**
 * Mengambil produk terkait selain ID aktif.
 */
function get_related_products(int $excludeId, int $limit = 7): array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id != ? ORDER BY (stock > 0) DESC, sold_count DESC LIMIT ?');
    $stmt->bindValue(1, $excludeId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Menghitung estimasi biaya QRIS (seperti 3.6% / min Rp 350 agar realistis).
 */
function calculate_qris_fee(int $amountAfterDiscount): int
{
    if ($amountAfterDiscount <= 0) {
        return 0;
    }
    return max(350, (int) round($amountAfterDiscount * 0.036));
}

/**
 * Memvalidasi kode promo dan menghitung potongan harga.
 *
 * @return array{valid:bool, discount:int, message:string, code:string}
 */
function evaluate_promo(string $rawCode, int $subtotal): array
{
    $code = strtoupper(trim($rawCode));
    if ($code === '') {
        return ['valid' => false, 'discount' => 0, 'message' => 'Masukkan kode promo terlebih dahulu.', 'code' => ''];
    }
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM promos WHERE code = ? AND active = 1');
    $stmt->execute([$code]);
    $promo = $stmt->fetch();
    if (!$promo) {
        return ['valid' => false, 'discount' => 0, 'message' => 'Kode promo tidak ditemukan atau sudah tidak aktif.', 'code' => $code];
    }
    if ($subtotal < (int) $promo['min_total']) {
        return [
            'valid' => false,
            'discount' => 0,
            'message' => 'Minimal transaksi untuk kode ini ' . format_rp((int) $promo['min_total']) . '.',
            'code' => $code,
        ];
    }

    if ($promo['type'] === 'percent') {
        $discount = (int) floor($subtotal * ((int) $promo['value'] / 100));
        if ((int) $promo['max_discount'] > 0) {
            $discount = min($discount, (int) $promo['max_discount']);
        }
    } else {
        $discount = (int) $promo['value'];
    }
    $discount = min($discount, $subtotal);

    return [
        'valid' => true,
        'discount' => $discount,
        'message' => 'Kode promo ' . $code . ' diterapkan (-' . format_rp($discount) . ').',
        'code' => $code,
    ];
}

/**
 * Mengisi template akun digital menjadi detail akses siap pakai setelah bayar.
 */
function generate_delivered_credentials(array $product, string $invoice): string
{
    $tpl = trim((string) ($product['account_template'] ?? ''));
    if ($tpl === '') {
        return "Produk: {$product['title']} ({$product['variant_label']})\nInvoice: {$invoice}\nSilakan hubungi WhatsApp CS jika butuh bantuan aktivasi.";
    }
    $rand = (string) random_int(100, 999);
    $slot = (string) random_int(1, 5);
    return str_replace(['{RAND}', '{SLOT}', '{INV}'], [$rand, $slot, $invoice], $tpl);
}

/**
 * Menjalankan pengiriman produk ke PremiumKita Open API dengan ref_id idempoten.
 *
 * @return array{ok:bool, delivered:string, invoice_number:string, error:string}
 */
function execute_premiumkita_fulfillment(array $order, array $product): array
{
    $qty = max(1, (int) $order['quantity']);
    $baseRef = trim((string) ($order['pk_ref_id'] ?? ''));
    if ($baseRef === '') {
        $baseRef = 'TRX-' . bin2hex(random_bytes(8));
        get_db()->prepare('UPDATE orders SET pk_ref_id = ? WHERE invoice = ?')->execute([$baseRef, $order['invoice']]);
    }

    $serialParts = [];
    $invNumbers = [];
    for ($i = 1; $i <= $qty; $i++) {
        $ref = $qty === 1 ? $baseRef : "{$baseRef}-{$i}";
        $res = pk_create_order((string) $product['provider_product_id'], $ref, [
            'buyer' => (string) $order['customer_name'],
            'email' => (string) $order['customer_email'],
            'durasi' => (string) $order['variant_label'],
            'catatan' => 'Invoice ' . $order['invoice'],
        ]);

        if (!$res['ok'] || !is_array($res['json'])) {
            return [
                'ok' => false,
                'delivered' => implode("\n\n", $serialParts),
                'invoice_number' => implode(', ', $invNumbers),
                'error' => $res['error'] ?: 'Gagal menghubungi API PremiumKita.',
            ];
        }

        $data = $res['json']['data'] ?? $res['json'];
        $sn = trim((string) ($data['serial_number'] ?? $data['sn'] ?? ''));
        $pkInv = trim((string) ($data['invoice_number'] ?? ''));
        if ($pkInv !== '') {
            $invNumbers[] = $pkInv;
        }
        $prefix = $qty > 1 ? "[Unit #{$i}] " : '';
        $serialParts[] = $prefix . ($sn !== '' ? $sn : 'Order sukses di PremiumKita (' . ($pkInv ?: $ref) . ').');
    }

    return [
        'ok' => true,
        'delivered' => implode("\n\n", $serialParts),
        'invoice_number' => implode(', ', $invNumbers),
        'error' => '',
    ];
}

/**
 * Mengirim ulang order lunas yang gagal diproses ke PremiumKita (menggunakan ref_id yang sama).
 */
function retry_premiumkita_fulfillment(string $invoice): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE invoice = ?');
    $stmt->execute([$invoice]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }

    $product = get_product_by_id((int) $order['product_id']);
    if (!$product || ($product['provider_source'] ?? 'local') !== 'premiumkita') {
        return $order;
    }

    $pkRes = execute_premiumkita_fulfillment($order, $product);
    if ($pkRes['ok']) {
        $pdo->prepare("UPDATE orders SET delivered_data = ?, pk_invoice_number = ?, pk_status = 'sukses', pk_error = '' WHERE invoice = ?")
            ->execute([$pkRes['delivered'], $pkRes['invoice_number'], $invoice]);
    } else {
        $pdo->prepare("UPDATE orders SET pk_status = 'failed', pk_error = ? WHERE invoice = ?")
            ->execute([$pkRes['error'], $invoice]);
    }

    $stmt->execute([$invoice]);
    return $stmt->fetch() ?: null;
}

/**
 * Menandai pesanan lunas, mengurangi stok, menambah terjual, dan merilis akses produk instan
 * (otomatis meneruskan ke PremiumKita API jika produk berasal dari sinkronisasi PremiumKita).
 */
function fulfill_order(string $invoice): ?array
{
    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE invoice = ?');
        $stmt->execute([$invoice]);
        $order = $stmt->fetch();
        if (!$order) {
            $pdo->rollBack();
            return null;
        }
        if ($order['status'] === 'paid') {
            $pdo->commit();
            return $order;
        }

        $product = get_product_by_id((int) $order['product_id']);
        $qty = max(1, (int) $order['quantity']);

        if ($product && (int) $product['stock'] >= $qty) {
            $updProd = $pdo->prepare('UPDATE products SET stock = MAX(0, stock - ?), sold_count = sold_count + ? WHERE id = ?');
            $updProd->execute([$qty, $qty, $product['id']]);
        }

        $refId = trim((string) ($order['pk_ref_id'] ?? ''));
        if ($refId === '') {
            $refId = 'TRX-' . bin2hex(random_bytes(8));
        }

        $pdo->prepare("UPDATE orders SET status = 'paid', paid_at = ?, pk_ref_id = ? WHERE invoice = ?")
            ->execute([date('Y-m-d H:i:s'), $refId, $invoice]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE invoice = ?');
    $stmt->execute([$invoice]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }

    $product = get_product_by_id((int) $order['product_id']);
    $pkCreds = pk_get_credentials();
    $isPkProduct = $product
        && ($product['provider_source'] ?? 'local') === 'premiumkita'
        && trim((string) $product['provider_product_id']) !== ''
        && $pkCreds['enabled'];

    if ($isPkProduct) {
        $pkRes = execute_premiumkita_fulfillment($order, $product);
        if ($pkRes['ok']) {
            $pdo->prepare("UPDATE orders SET delivered_data = ?, pk_invoice_number = ?, pk_status = 'sukses', pk_error = '' WHERE invoice = ?")
                ->execute([$pkRes['delivered'], $pkRes['invoice_number'], $invoice]);
        } else {
            $fallbackMsg = "Pembayaran diterima! Pesanan sedang disiapkan oleh sistem & admin CS.\nInvoice: {$invoice}\nRef: {$order['pk_ref_id']}";
            $pdo->prepare("UPDATE orders SET delivered_data = ?, pk_status = 'failed', pk_error = ? WHERE invoice = ?")
                ->execute([$fallbackMsg, $pkRes['error'], $invoice]);
        }
    } elseif (trim((string) $order['delivered_data']) === '' && $product) {
        $delivered = ($product['delivery_type'] === 'manual')
            ? "Pembayaran terkonfirmasi! Produk ini diproses oleh admin maksimal 1x15 menit jam aktif.\nInvoice: {$invoice}\nJika lebih dari 15 menit, hubungi WhatsApp CS dengan menyertakan nomor invoice."
            : generate_delivered_credentials($product, $invoice);
        $pdo->prepare("UPDATE orders SET delivered_data = ?, pk_status = 'local' WHERE invoice = ?")
            ->execute([$delivered, $invoice]);
    }

    $stmt->execute([$invoice]);
    return $stmt->fetch() ?: null;
}

/**
 * Mengecek status pembayaran ke NandarPay (dengan rate-limit guard 4 detik per transaksi).
 */
function sync_order_payment_status(string $invoice): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE invoice = ?');
    $stmt->execute([$invoice]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }

    $pgTxnId = trim((string) ($order['pg_txn_id'] ?? ''));
    if ($order['status'] !== 'pending' || $pgTxnId === '') {
        return $order;
    }

    $lastPoll = !empty($order['last_pg_poll_at']) ? strtotime((string) $order['last_pg_poll_at']) : 0;
    if ($lastPoll > 0 && (time() - $lastPoll) < 4) {
        return $order;
    }

    $pdo->prepare('UPDATE orders SET last_pg_poll_at = ? WHERE invoice = ?')->execute([date('Y-m-d H:i:s'), $invoice]);
    $res = pg_check_transaction_status($pgTxnId);
    if (!$res['ok'] || !is_array($res['json'])) {
        return $order;
    }

    $pgStatus = strtolower(trim((string) ($res['json']['status'] ?? '')));
    if ($pgStatus === 'completed') {
        return fulfill_order($invoice);
    }
    if ($pgStatus === 'canceled') {
        $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE invoice = ?")->execute([$invoice]);
        $stmt->execute([$invoice]);
        return $stmt->fetch() ?: $order;
    }

    return $order;
}
