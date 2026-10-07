# Pemeriksaan tahap 8 frontend payroll

Tanggal: 6 Oktober 2026 (WIB).
Baseline kode: `dd6561a`, ditambah dokumen tahap 1 dan perubahan pengujian di bawah.

## Kesimpulan penerimaan

**Belum lulus penerimaan desain baru.** Tahap 2–7 pada
[rencana frontend](payroll-frontend-plan.md) belum diimplementasikan di working
tree yang diperiksa. Riwayat commit bertuliskan Tahap 7/8/9/10 adalah pekerjaan
backend sebelumnya, bukan bukti selesainya tahapan frontend ini.

Pengujian tahap 8 dilakukan terhadap fungsi existing yang dapat diuji sekarang.
Hasil tersebut tidak menyatakan ketiga screenshot sudah diimplementasikan.

## Pekerjaan yang dilakukan

- Memeriksa route, props, halaman, status payroll dan cakupan tes existing.
- Menambah satu tes integrasi pada [PenggajianTest](../tests/Feature/PenggajianTest.php):
  perhitungan/pengajuan HRD → persetujuan Manager → penerbitan slip → penguncian
  Finance → dua pembayaran hingga lunas → pembatalan pembayaran kedua → akses
  slip oleh karyawan. Tes memeriksa konsistensi total line/entry/run/slip pada
  fixture gaji tetap Rp3.000.000 serta snapshot slip yang tidak berubah.
- Merapikan format dokumen tahap 1 mengikuti formatter project.
- Menjalankan build, pemeriksaan tipe, analisis PHP dan tes backend pada SQLite
  in-memory. Tidak melakukan migrasi, seeding atau transaksi ke database produksi.

## Hasil pemeriksaan otomatis

| Pemeriksaan                                                   | Hasil                                                                |
| ------------------------------------------------------------- | -------------------------------------------------------------------- |
| `npm run build`                                               | Lulus; ada peringatan opsional fontaine untuk optimasi fallback font |
| `npm run types:check`                                         | Lulus                                                                |
| `vendor/bin/phpstan analyse --no-progress --debug`            | Lulus, 0 error                                                       |
| `php artisan test --compact tests/Feature/PenggajianTest.php` | Lulus: 7 tes, 56 assertions                                          |
| Seluruh tes setelah build                                     | Lulus: 360 tes, 1.320 assertions                                     |
| `npm run check`                                               | Lulus: 124 berkas terformat; 109 berkas tanpa warning/error lint     |

Pemeriksaan awal menjalankan build bersamaan dengan tes: 358 dari 359 tes lulus,
satu tes gagal membaca font CSS dari manifest ketika aset sedang diganti build.
Karena keduanya berbagi `public/build`, pengujian penuh diulang setelah build
selesai. Build dan tes render halaman tidak boleh dijalankan bersamaan pada
direktori aset yang sama.

## Matriks fungsi dan batas bukti

| Skenario                                             | Bukti saat ini                                 | Status terhadap konsep baru                                                       |
| ---------------------------------------------------- | ---------------------------------------------- | --------------------------------------------------------------------------------- |
| HRD menghitung dan mengajukan                        | Tes PayrollRunCalculation/Submission           | Tersedia; readiness belum lengkap                                                 |
| Manager menyetujui atau meminta revisi               | Tes PayrollRunApproval/Submission              | Tersedia; belum pengujian klik browser                                            |
| Finance mengunci, mencicil, melunasi, membatalkan    | Tes PayrollPayment + tes integrasi baru        | Tersedia untuk alur existing                                                      |
| Pengiriman pembayaran ganda/nominal berlebih         | Tes PayrollPayment                             | Cakupan request existing; bukan uji beban transaksi simultan di DB produksi       |
| Snapshot slip konsisten sesudah perubahan pembayaran | Tes integrasi baru                             | Lulus untuk fixture gaji tetap; tidak membuktikan rumus BPJS/PPh21/prorata        |
| Slip karyawan lain ditolak                           | PenggajianTest                                 | Ada cakupan; akses HRD lintas perusahaan masih diizinkan kode/tes existing        |
| Pembatasan perusahaan                                | Tes Manager/Finance/publikasi slip             | Sebagian; master HRD dan detail slip belum konsisten                              |
| Daftar kosong                                        | Cabang UI pada daftar slip, Manager            | Diperiksa pada kode, belum browser                                                |
| Pesan validasi/gagal simpan                          | Form error/processing pada Finance/Manager     | Diperiksa pada kode; simulasi jaringan putus belum dilakukan                      |
| Total divisi                                         | UI mengagregasi berdasarkan nama dengan Number | Belum uji visual/rounding komprehensif; perlu ID divisi dan aturan uang konsisten |
| PDF dan kesamaan nominal PDF                         | Belum ada route/generator download             | Belum tersedia, tidak dapat dinyatakan lulus                                      |

## Perbandingan terhadap screenshot

| Referensi                                                  | Kode saat ini                                                                   | Hasil                                         |
| ---------------------------------------------------------- | ------------------------------------------------------------------------------- | --------------------------------------------- |
| Proses Gaji dua panel, sidebar navy                        | Halaman hasil memakai tabel dengan ekspansi baris/divisi dan sidebar existing   | Desain target belum dibuat                    |
| Badge Master/Absensi/Otomatis dan rincian rumus            | Props line masih nama, jenis, nominal                                           | Belum tersedia lengkap                        |
| Aturan & Komponen dalam tiga kelompok kartu                | Halaman kebijakan berisi tarif telat/alpha/reward/lembur per perusahaan         | Berbeda dari desain dan cakupan aturan target |
| Slip mobile dengan kartu THP, 5 statistik, status transfer | Halaman detail existing menampilkan identitas, ringkasan terbatas, line dan THP | Belum sesuai screenshot                       |
| Pemilih periode di detail dan tombol PDF                   | Daftar slip tersedia terpisah; PDF belum tersedia                               | Belum selesai                                 |

Tidak dilakukan screenshot browser atau pengujian perangkat fisik pada tahap ini.
Pemeriksaan kelas responsif/overflow pada kode bukan bukti tampilan desktop,
tablet dan HP sudah sesuai. Pengujian visual final menunggu halaman target dibuat;
keberadaan Chrome lokal saja tidak memberikan bukti visual.

## Penghambat penerimaan dan urutan tindak lanjut

1. Implementasikan fondasi visual serta halaman tahap 2–4 sebelum membandingkan
   screenshot. Pertahankan menu existing dan hak akses sesuai peran.
2. Selesaikan readiness server, versi efektif policy/profil, penyesuaian manual,
   tarif yang disepakati serta pembulatan pada tahap 5. Saat ini submit hanya
   memeriksa draft, bukan seluruh kelengkapan absensi/profil.
3. Rapikan batas perusahaan dan akses detail slip pada tahap 6–7. Daftar slip
   memfilter published, tetapi detail langsung belum membatasi status serupa.
   Tes existing yang lulus bukan bukti batas akses ini sudah benar.
4. Lengkapi snapshot kehadiran, informasi pembayaran, dan PDF di tahap 7.
5. Ulangi penerimaan browser pada viewport usulan 1440×900, 768×1024 dan
   390×844: empat peran, nama panjang, banyak baris, data kosong, keyboard/fokus,
   pengajuan gagal, revisi, cicilan, batal bayar, preview/PDF dan semua total.

Tidak ada perubahan perilaku aplikasi pada pemeriksaan ini. Skenario yang belum
didukung dicatat untuk implementasi, bukan diloloskan dengan data contoh atau
pelemahan assertion pengujian.
