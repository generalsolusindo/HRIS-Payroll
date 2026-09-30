# Absensi Gaji

Fondasi aplikasi web menggunakan Laravel 13, React 19, Inertia 3, TypeScript,
Tailwind CSS 4, dan Vite. Dibuat dari [starter kit React resmi Laravel](https://github.com/laravel/react-starter-kit).

Library tambahan: Framer Motion, GSAP, `@gsap/react`, React Icons, shadcn/ui,
Radix UI, Lucide, Sonner, clsx, dan tailwind-merge.

Starter kit menyediakan registrasi, login, reset kata sandi, pengaturan profil,
autentikasi dua faktor, passkeys, dan tema terang/gelap. Fitur bisnis absensi
dan penggajian belum dibuat.

## Menjalankan proyek

Gunakan PHP 8.3+, Composer, dan Node.js 22.12+ (direkomendasikan Node.js 24).
Database pengembangan menggunakan SQLite. Pastikan ekstensi PHP `pdo_sqlite`
dan `sqlite3` aktif. Pada Ubuntu dengan PHP 8.5:

```bash
sudo apt-get install -y php8.5-sqlite3
```

Dependensi, `.env`, application key, dan database lokal sudah disiapkan pada
instalasi awal ini. Jalankan dari folder proyek:

```bash
composer run dev
```

Buka http://localhost:8000. Perintah tersebut menjalankan server aplikasi,
Vite, dan layanan pengembangan dari starter kit. Tekan Ctrl+C untuk berhenti.

Untuk instalasi ulang dari checkout baru:

```bash
composer run setup
composer run dev
```

Konfigurasi berada di `.env`: nama aplikasi `Absensi Gaji`, zona waktu
`Asia/Jakarta`, dan koneksi database `sqlite`. Terjemahan antarmuka bawaan
starter kit masih menggunakan bahasa Inggris. Email lokal menggunakan log
di `storage/logs/laravel.log`; atur SMTP untuk pengiriman email sungguhan.

## Pengembangan

- `routes/web.php`: route Laravel yang menampilkan halaman Inertia.
- `app/`: controller, model, middleware, dan logika backend.
- `resources/js/pages/`: halaman React.
- `resources/js/components/`: komponen React dan shadcn/ui.
- `resources/css/app.css`: Tailwind CSS dan tema.
- `database/migrations/`: skema database.

Library animasi dan ikon siap diimpor sesuai kebutuhan:

```tsx
import { motion } from 'framer-motion';
import { gsap } from 'gsap';
import { useGSAP } from '@gsap/react';
import { FaUser } from 'react-icons/fa';
```

## Pemeriksaan

```bash
npm run build
npm run check
npm run types:check
composer test
```

Build mengunduh font Instrument Sans dan menyimpannya bersama aset lokal,
sehingga build pertama memerlukan internet. Versi dependensi dikunci dalam
`composer.lock` dan `package-lock.json`.
