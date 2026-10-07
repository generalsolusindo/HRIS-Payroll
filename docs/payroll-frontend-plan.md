# Pemetaan frontend payroll — tahap 1

Tanggal pemeriksaan: 6 Oktober 2026 (WIB).

Arahan terbaru pengguna: pertahankan palet dan identitas web existing, bukan warna screenshot. Acuan tahap 2 adalah token tema pada `resources/css/app.css` (terang/gelap); screenshot hanya acuan tata letak. Warna contoh screenshot tidak menjadi target implementasi.
Status: pemetaan selesai. Tahap 2 diterapkan pada 6 Oktober 2026; tahap 3 diterapkan pada 6 Oktober 2026; tahap 4 diterapkan pada 6 Oktober 2026; tahap 5 diselesaikan sesuai lingkup aturan existing pada 7 Oktober 2026; tahap 6–7 belum diterapkan. Pemeriksaan tahap 8 sebelumnya adalah audit baseline, bukan penerimaan desain akhir.

Dokumen ini memetakan tiga screenshot pengguna ke kode aktual. Screenshot adalah
referensi tata letak dan interaksi, bukan persetujuan atas tarif, rumus, nama,
nominal, tanggal, maupun status contoh. Tidak ada perubahan aplikasi, migrasi,
seeding, atau data produksi pada tahap 1.

## 1. Sasaran dan batas pekerjaan

- Screenshot 1: Proses Gaji, daftar karyawan kiri dan rincian kanan.
- Screenshot 2: Aturan & Komponen Gaji, pengaturan dalam tiga kelompok kartu.
- Screenshot 3: Slip Gaji, tampilan mobile dengan THP dan rincian pembayaran.
- Pertahankan perhitungan per karyawan, agregasi divisi, lalu total perusahaan.
- Gunakan data backend, snapshot hasil payroll, dan hak akses yang nyata.
- Nilai belum tersedia ditampilkan sebagai belum tersedia, bukan angka nol palsu.
- Desain memakai token warna existing untuk latar, kartu, aksi dan garis tipis,
  angka rata kanan, serta hierarki teks pada referensi. Ukuran persis disesuaikan
  saat implementasi responsif; gambar referensi bukan aset logo produksi.

## 2. Peta halaman dan navigasi

Path frontend di bawah relatif terhadap `resources/js/pages/`.

| Tujuan desain      | Halaman/route saat ini                                                                                                                     | Rencana penggunaan                                                                                         |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------- |
| Dashboard          | `/dashboard`, `DashboardController`                                                                                                        | Tetap halaman sesuai peran; tahap 2 merapikan kerangka navigasi, bukan membuat analitik baru               |
| Kehadiran Hari Ini | `/hrd/absensi`, `hrd/attendance-dashboard.tsx`                                                                                             | Tautan rekap dengan konteks tanggal hari ini; konfirmasi dukungan filter sebelum membuat tautan            |
| Data Absensi       | `/hrd/koreksi-absensi`, `hrd/attendance-reviews.tsx`                                                                                       | Rekap yang ditinjau HRD untuk payroll; riwayat pribadi tetap terpisah                                      |
| Proses Gaji        | `/hrd/periode-gaji`, `hrd/payroll-periods.tsx`; `/hrd/hasil-payroll/{run}`, `hrd/payroll-run-show.tsx`                                     | Periode sebagai pintu masuk, hasil sebagai workspace dua panel                                             |
| Aturan & Komponen  | `/hrd/kebijakan-absensi`, `hrd/attendance-policy.tsx`; `/hrd/komponen-gaji`, `hrd/payroll-components.tsx`                                  | Satukan pengalaman pengaturan lewat kartu/tab; backend tetap dipisahkan menurut tanggung jawab             |
| Profil gaji        | `/hrd/profil-gaji`, `hrd/payroll-profiles.tsx`, `hrd/payroll-profile-edit.tsx`                                                             | Tetap tersedia sebagai pengelolaan data master pendapatan/rekening                                         |
| Approval & Bayar   | `/manager/payroll`, `manager/payroll-run-approval.tsx`; `/finance/payroll`, `finance/payroll-payments.tsx`, `finance/payroll-run-show.tsx` | Akses berbeda menurut peran; HRD tidak memperoleh izin approve/bayar hanya karena menu screenshot digabung |
| Slip karyawan      | `/penggajian`, `penggajian.tsx`; `/penggajian/{payslip}`, `penggajian-detail.tsx`                                                          | Daftar periode/slip dan detail mobile pada screenshot 3                                                    |

File kerangka yang akan digunakan: [AppSidebar](../resources/js/components/app-sidebar.tsx),
[layout sidebar](../resources/js/layouts/app/app-sidebar-layout.tsx),
[CSS aplikasi](../resources/css/app.css). Komponen UI yang ada dipakai kembali.
Menu karyawan, izin, lembur, kalender, divisi, audit, pengaturan akun, dan tema
tetap dapat dijangkau; navigasi screenshot bukan alasan menghapusnya.

## 3. Screenshot 1 — Proses Gaji

| Elemen                           | Data/fungsi tersedia                                       | Kesenjangan dan pekerjaan                                                                                                           |
| -------------------------------- | ---------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Periode dan perusahaan           | `run.period`, snapshot perusahaan                          | Pemilih periode mengambil daftar periode yang diizinkan untuk pengguna                                                              |
| “Absensi terkunci 26 Okt, 10:05” | Snapshot absensi ada pada entry                            | Belum ada proses pembekuan absensi beserta waktu yang mendukung klaim ini; jangan menyamakan `run.locked_at` dengan kunci absensi   |
| Nama, jabatan, divisi            | `employee_snapshot`, `position_name`, `division_name`      | Nomor karyawan contoh GS-024 belum dikirim dalam props/snapshot ini; tambahkan sumber identitas bila diperlukan                     |
| Bruto, potongan, gaji bersih     | `gross_amount`, `deduction_amount`, `net_amount`           | Tata ulang tabel; jumlah tetap berasal dari server                                                                                  |
| Klik karyawan                    | Entry dan lines sudah dikirim                              | Ganti ekspansi tabel menjadi selection dengan panel kanan; pilih berdasarkan entry ID                                               |
| Ringkasan divisi/perusahaan      | Agregasi divisi sudah ada di UI, total run tersedia        | Pertahankan filter/ringkasan; sertakan `division_id` agar tidak mengelompokkan berdasarkan nama saja                                |
| Badge “Siap”                     | Ada `unresolved_days`, belum ada readiness lengkap         | Tambah hasil validasi server per entry beserta alasan; belum ada error bukan otomatis siap                                          |
| Badge “Ada penyesuaian”          | Schema line mendukung `manual`/`adjustment`                | Belum ada alur penyesuaian manual; jangan menampilkan badge statis                                                                  |
| Label Master/Absensi/Otomatis    | DB line punya `source`, `category`, `calculation_snapshot` | Props saat ini hanya nama, jenis, nominal; perlu perluasan respons                                                                  |
| “19 hari × Rp40.000”             | DB line punya `quantity`, `rate`, snapshot rumus           | Kirim rincian yang benar-benar dihitung; belum ada transport/makan per hari pada kalkulator                                         |
| Hitung ulang semua               | POST `/hrd/periode-gaji/{period}/hitung`                   | Saat ini hanya hitung pertama atau setelah `revision_requested`; recalculation draft membutuhkan pengembangan, bukan sekadar tombol |
| Ajukan approval                  | PATCH `/hrd/hasil-payroll/{run}/ajukan`                    | Saat ini hanya memeriksa `draft`; tambah pemeriksaan kelengkapan sebelum mengaktifkan alur final                                    |
| Tambah penyesuaian manual        | Kolom alasan, sumber, pembuat sudah tersedia di schema     | Perlu endpoint, validasi nominal/jenis/alasan, pembatasan status, pembaruan total dan audit                                         |
| Pratinjau slip                   | Halaman detail slip dan penerbitan sudah ada               | Pratinjau draft perlu alur tersendiri tanpa menerbitkan slip; jangan memakai aksi publikasi sebagai preview                         |

Usulan kontrak data (belum endpoint/props yang sudah tersedia):

- `run`: ID, versi, status, periode, perusahaan, rentang absensi, total,
  waktu perhitungan; waktu penguncian hanya bila benar-benar dicatat.
- `entries[]`: ID, snapshot identitas, ID/nama divisi, total, readiness,
  alasan masalah, penanda penyesuaian, tautan slip yang boleh diakses.
- `lines[]`: ID/kode, nama, jenis, kategori, sumber, quantity, rate,
  amount, ringkasan perhitungan, alasan penyesuaian jika ada.
- `capabilities`: hak lihat/hitung/ajukan/adjust/preview/publish dari server;
  tombol tidak menjadi pengganti otorisasi endpoint.

Pemetaan badge yang diusulkan: `salary` → Master;
`attendance`, `overtime`, `reward` → Absensi dengan keterangan asal yang jelas;
`manual`/`adjustment` → Manual. Label Otomatis untuk mesin aturan seperti
BPJS/pajak membutuhkan metadata rumus/sumber tambahan; jangan menganggap
semua komponen berkategori insurance/tax otomatis sudah dihitung.

## 4. Screenshot 2 — Aturan & Komponen Gaji

| Aturan pada gambar                          | Implementasi saat ini                                                      | Kebutuhan berikutnya                                                                                         |
| ------------------------------------------- | -------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Transport per hari hadir                    | Komponen allowance bisa dibuat; profil menyimpan nominal bulanan           | Tarif harian, jumlah hari eligible, sumber kehadiran dan snapshot perhitungan                                |
| Makan per hari hadir                        | Sama seperti transport                                                     | Tentukan perlakuan izin, sakit, dinas, dan hadir sebagian                                                    |
| Dinas luar per hari + foto lokasi           | Koreksi HRD mendukung `business_trip` dan bukti                            | Belum ada alur dinas/foto lokasi khusus serta kalkulator tunjangan dinas                                     |
| Lembur gaji pokok ÷ 173, pengali bertingkat | Menit lembur disetujui × tarif per jam                                     | Algoritme pada gambar belum tersedia; definisi dasar upah dan pengali perlu keputusan/verifikasi             |
| Toleransi terlambat 10 menit                | Payroll membaca total menit telat                                          | Pisahkan toleransi potongan dari status keterlambatan absensi; jangan mengubah jadwal masuk secara diam-diam |
| Potongan telat Rp50.000 per kejadian        | `late_deduction_per_minute`                                                | Perlu mode per kejadian, ambang, dan jumlah kejadian eligible                                                |
| Alpha proporsional                          | `absence_deduction_per_day` nominal tetap                                  | Tentukan pembagi, dasar upah, periode dan prorata                                                            |
| Lupa absen pulang → koreksi HRD             | Hari tidak lengkap menjadi unresolved, bisa dikoreksi                      | Aturan tidak mendapat lembur pada hari tersebut belum diterapkan di kalkulator                               |
| Cut-off tanggal 25                          | Rentang `attendance_starts_on` / `attendance_ends_on` diisi per periode    | Perlu template cut-off bulanan, penanganan beda bulan dan tahun                                              |
| Tarik otomatis tanggal 26 pukul 06.00       | Rekap diperbarui saat perhitungan dijalankan                               | Belum ada scheduler payroll; idempotensi, kegagalan/retry dan pencatatan waktu perlu dibuat                  |
| Gajian tanggal 28, maju jika libur          | `payment_due_on` tersedia                                                  | Belum ada aturan otomatis pergeseran memakai kalender kerja                                                  |
| BPJS Kesehatan, JHT, JP                     | Kategori insurance dan identitas tersedia di schema                        | Mesin hitung, dasar/cap upah, kepesertaan, pemisahan porsi perusahaan/karyawan belum lengkap                 |
| PPh21 TER                                   | Kategori tax dan identitas tersedia di schema                              | Data perpajakan, tabel tarif berversi dan metode perhitungan perlu dilengkapi                                |
| “Berlaku periode berikutnya”                | Policy memiliki kolom versi/tanggal; controller mengubah versi 1 di tempat | Buat alur versi efektif dan pemilihan policy sesuai periode sebelum menampilkan janji tersebut               |

Aturan existing reward kehadiran sempurna tetap perlu dipertahankan meskipun
tidak muncul di referensi. Jangan mengubah nilai produksi untuk menyerupai contoh.
Tarif BPJS, tabel pajak, dan formula lembur pada gambar belum diverifikasi sebagai
ketentuan berlaku; verifikasi sumber resmi dilakukan pada tahap implementasi rumus.

## 5. Screenshot 3 — Slip Gaji

| Elemen                                | Data/fungsi tersedia                                         | Kebutuhan berikutnya                                                                                             |
| ------------------------------------- | ------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------- |
| Pemilih bulan                         | Daftar slip terbit milik pengguna tersedia                   | Hubungkan detail dengan daftar periode/versi yang boleh dilihat                                                  |
| Kartu THP                             | `snapshot.net_amount`                                        | Label “Gaji bersih” saat belum dibayar; “diterima” harus mengikuti catatan pembayaran                            |
| Tanggal/metode pembayaran             | `payroll_payments` menyimpan metode, status, paid_at         | Belum dikirim ke detail slip; tampilkan belum bayar/sebagian/lunas, abaikan pembayaran voided                    |
| “Ditransfer ke rekening terdaftar”    | Metode bank_transfer/cash/other tersedia                     | Jangan menyebut transfer untuk tunai; sumber adalah catatan Finance, bukan konfirmasi bank otomatis              |
| Rentang 26 Sep–25 Okt                 | Periode punya rentang absensi terpisah                       | Snapshot slip baru menyimpan tanggal periode gaji; tambahkan rentang absensi eksplisit                           |
| Hadir, telat, izin, dinas, jam lembur | Snapshot entry punya rincian hari; lembur disetujui tersedia | Snapshot slip baru berisi menit telat, hari alpha dan perfect_attendance; perlu ringkasan lengkap saat publikasi |
| Pendapatan/potongan dan total         | Snapshot nominal dan lines tersedia                          | Susun mengikuti screenshot; simpan quantity/rate agar keterangan seperti “19 hari” dapat ditampilkan             |
| BPJS digabung                         | Lines memiliki nama/jenis/nominal                            | Tambahkan kode/kategori stabil di snapshot; jangan mengelompokkan hanya berdasarkan teks nama                    |
| Unduh slip PDF                        | Schema menyediakan `file_path`, `file_sha256`                | Belum ada generator maupun route download; buat dari snapshot versi slip dan lindungi akses                      |

Ketentuan kontrak: jumlah telat adalah kejadian, bukan menit; durasi lembur tetap
disimpan dalam menit lalu diformat sebagai jam/menit. Definisikan apakah “hadir”
mencakup dinas agar angka tidak dihitung ganda. Slip lama yang belum memiliki
field baru tetap bisa dibuka dengan keterangan data tidak tersedia, tanpa
merekonstruksi angka histori dari profil atau absensi yang sudah berubah.

Perhitungan slip dibekukan saat publikasi. Informasi pembayaran dapat disajikan
terpisah sebagai status terbaru dengan waktu pemeriksaan; PDF perlu menyatakan
waktu/status yang direkam agar perubahan pembayaran tidak diam-diam mengubah
arti dokumen versi lama.

## 6. Hak akses, status dan kendala backend

Peran Manager saat ini menggunakan nilai role `admin`, bukan `manager`.

| Peran             | Sasaran hak akses                                                             |
| ----------------- | ----------------------------------------------------------------------------- |
| HRD               | Master, proses, koreksi, pengajuan, penerbitan/preview sesuai perusahaan      |
| Manager (`admin`) | Melihat, menyetujui atau meminta revisi payroll perusahaan                    |
| Finance           | Melihat hasil disetujui, mengunci, mencatat/membatalkan pembayaran perusahaan |
| Karyawan          | Slip miliknya sendiri yang diterbitkan dan unduhan yang diizinkan             |

Alur tersedia: draft → submitted → approved → locked → partially_paid/paid.
Cabang revisi: submitted → revision_requested → run baru draft; run lama
menjadi superseded. Membatalkan pembayaran dapat mengembalikan status pembayaran.
Nilai cancelled/reopen pada schema tidak berarti seluruh aksinya sudah tersedia.

Kesenjangan yang wajib dibereskan sebelum alur akhir dinyatakan siap:

1. Master HRD dan periode/profil belum konsisten membatasi perusahaan pengguna.
   `ScopesToOwnCompany` sudah dipakai pada run/pembayaran, tetapi belum merata.
2. `PenggajianController::show` mengizinkan HRD tanpa pemeriksaan perusahaan dan
   belum mensyaratkan published untuk akses karyawan ke detail langsung.
   Lengkapi bersama akses preview/PDF; jangan hanya mengandalkan daftar yang difilter.
3. Submit draft belum memblokir unresolved, periode belum selesai, atau data
   karyawan yang terlewat. Perlu hasil validasi menyeluruh dengan alasan tindakan.
4. Profil gaji masih diubah sebagai satu profil; tanggal efektif belum dipilih
   kalkulator. Policy selalu memakai REGULAR versi 1. Perubahan efektif/prorata
   perlu diselesaikan agar label periode berikutnya benar.
5. Profil UI menawarkan earning, tetapi validator komponen belum memastikan
   jenis earning/aktif; kalkulator menjumlah seluruh komponen profil sebagai bruto.
6. Perhitungan nominal masih mencampur float dan decimal. Tentukan pembulatan
   serta gunakan aritmetika uang konsisten agar total tabel/detail/PDF sama.
7. Label siap, kunci absensi, preview draft, dan manual adjustment adalah
   pengembangan baru. Jangan menampilkan seolah-olah sudah punya endpoint.

## 7. Keputusan bisnis yang masih terbuka

Daftar ini tidak menghalangi tahap 2 (kerangka visual). Bagian rumus/otomasi yang
bergantung pada keputusan ini tidak diaktifkan sebelum jawabannya tersedia.

| Keputusan                                                                                                           | Perlu selesai sebelum                                                                 |
| ------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| Apakah tarif/rumus pada screenshot benar-benar menjadi aturan perusahaan? Nilai per perusahaan?                     | Tahap 4 menyimpan aturan baru dan tahap 5 menghitung                                  |
| Jam masuk dan toleransi potongan: seeder kini 08.35, permintaan terdahulu 08.30; gambar menyebut toleransi 10 menit | Tahap 5; pisahkan status absensi dari ambang potongan                                 |
| Hari eligible transport/makan, dinas, izin/sakit, masuk/keluar kerja di tengah periode                              | Tahap 5                                                                               |
| Dasar dan pembagi alpha, lembur, pembulatan, perlakuan THP negatif                                                  | Tahap 5                                                                               |
| Cut-off/gajian, jadwal otomatis dan arti penguncian absensi                                                         | Tahap 4–6                                                                             |
| Penyesuaian manual boleh di status apa, pengaruh terhadap approval, dan siapa berwenang                             | Tahap 5–6                                                                             |
| HRD masing-masing perusahaan atau HRD pusat lintas perusahaan                                                       | Tahap 3–6, sebelum membuka endpoint baru; jangan memperluas akses berdasarkan sidebar |
| Identitas merek GS Payroll atau nama aplikasi/perusahaan existing                                                   | Tahap 2; default mempertahankan identitas existing                                    |

## 8. Pembagian tahap dan kriteria selesai

| Tahap       | Lingkup                             | Kriteria selesai                                                                                                                                              |
| ----------- | ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1 — selesai | Pemetaan ini                        | Ketiga screenshot dipetakan, route/data/gap/keputusan tercatat, tidak mengubah aturan produksi                                                                |
| 2           | Kerangka visual dan navigasi        | Palet existing/kartu konsisten, navigasi sesuai peran, sidebar mobile dan fokus keyboard bekerja, fitur existing tetap terjangkau                             |
| 3           | Workspace Proses Gaji               | Daftar-detail memakai data nyata, pencarian/filter divisi, status/error/loading/kosong, props rincian dan akses server aman                                   |
| 4           | Aturan & Komponen                   | Kartu/tab dan form terhubung; field yang belum didukung diberi keterangan, bukan tombol simpan palsu; tanggal efektif hanya dijanjikan bila backend mendukung |
| 5           | Mesin hitung dan penyesuaian        | Aturan disepakati/diuji, jejak rumus, versi efektif, koreksi nominal dan konsistensi total; semua field tahap 4 yang diaktifkan benar-benar bekerja           |
| 6           | Approval dan pembayaran             | Transisi status, readiness, pembatasan perusahaan, request ganda dan pencatatan pembayaran diuji; revisi tidak merusak histori                                |
| 7           | Slip mobile dan PDF                 | Snapshot lengkap, pemilih periode, status pembayaran benar, download privat, slip lama tetap terbaca                                                          |
| 8           | Uji integrasi dan kesesuaian visual | Desktop/tablet/HP, alur empat peran, gagal simpan, data kosong, revisi dan pembayaran sebagian; nominal semua representasi konsisten                          |

Ketergantungan penting: tahap 3–4 dapat membangun antarmuka lebih dahulu, tetapi
fitur baru yang bergantung tahap 5–6 harus dinyatakan belum tersedia sampai
backend selesai. Tidak memakai dummy data dalam halaman produksi. UI desktop
dua panel berubah menjadi daftar/detail pada layar sempit. Status selalu memakai
teks selain warna, angka terbaca, tabel tidak memaksa seluruh halaman melebar,
dan tombol bawah slip tidak menutupi isi atau area aman perangkat.

## 9. Acuan kode dan validasi tahap 1

- [Route](../routes/web.php), [scheduler](../routes/console.php).
- [Kalkulator dan respons hasil](../app/Http/Controllers/Hrd/PayrollRunController.php).
- [Policy](../app/Http/Controllers/Hrd/PayrollPolicyController.php),
  [profil](../app/Http/Controllers/Hrd/PayrollProfileController.php),
  [periode](../app/Http/Controllers/Hrd/PayrollPeriodController.php).
- [Publikasi slip](../app/Http/Controllers/Hrd/PayrollPayslipController.php),
  [akses slip](../app/Http/Controllers/PenggajianController.php),
  [pembayaran](../app/Http/Controllers/Finance/PayrollPaymentController.php).
- [Generator rekap](../app/Services/AttendanceReviewGenerator.php),
  [validator koreksi](../app/Http/Requests/Hrd/UpdateAttendanceReviewRequest.php).
- [Rancangan schema awal](payroll-schema.md): acuan struktur, bukan inventaris
  fitur terkini; pernyataan belum ada UI/kalkulator di dokumen itu bersifat historis.

Validasi tahap 1 dilakukan dengan membaca route, controller, validator, schema,
props halaman, dan scheduler serta memeriksa tautan berkas dokumen. Tes aplikasi
tidak dijalankan ulang untuk perubahan dokumentasi ini. Audit ini tidak mengakses
database produksi dan bukan verifikasi kepatuhan tarif pajak/asuransi terkini.

## Implementasi tahap 2

- Palet CSS dan identitas aplikasi tetap: seluruh elemen baru memakai token
  primary, background, muted, border dan sidebar yang mendukung terang/gelap.
- Sidebar mengelompokkan Proses Gaji, Aturan Gaji, Komponen dan Profil Gaji
  dalam Penggajian HRD. Menu HRD lain dan menu pribadi tetap tersedia.
- Kerangka PayrollWorkspace memberikan lebar konten, jarak responsif dan
  navigasi lokal HRD; Manager/Finance/karyawan tetap memakai akses existing.
- Menu aktif mengikuti halaman detail, sidebar mobile menutup ketika tautan
  dipilih, tautan aktif memakai aria-current dan tersedia skip link ke konten.
- Header/breadcrumb disesuaikan untuk layar sempit. Tahap ini tidak mengubah
  isi kalkulator, formulir aturan, pembayaran atau desain detail slip.
- Pemeriksaan tipe TypeScript dan formatter/lint lulus. Build dijalankan untuk
  memastikan bundling. Belum ada verifikasi visual pada HP fisik/browser.

## Implementasi tahap 3

- Halaman hasil payroll kini memakai daftar karyawan dan panel rincian pada
  desktop; layar di bawah breakpoint xl membuka rincian setelah nama dipilih
  dengan tombol kembali ke daftar. Fokus keyboard diarahkan ke rincian.
- Pencarian nama/jabatan/divisi, filter divisi, reset filter dan keadaan kosong
  tersedia. Total perusahaan tidak berubah mengikuti filter. Total per divisi
  dikirim server melalui aritmetika desimal dan dikelompokkan berdasarkan ID.
- Panel memisahkan pendapatan, potongan, kontribusi perusahaan di luar THP,
  serta THP. Sumber, quantity, rate dan alasan penyesuaian memakai data line
  tersimpan, bukan kalkulasi ulang browser atau data contoh screenshot.
- Capabilities submit/recalculate/publish dikirim berdasarkan role/status.
  Ajukan, hitung ulang setelah revisi, publikasi slip dan histori dipertahankan.
  Tautan lihat slip hanya ditampilkan untuk HRD ketika publikasi diperbolehkan.
- Absensi belum lengkap ditandai sebagai perlu tinjauan. Absensi tercatat tidak
  dilabeli siap karena validasi readiness menyeluruh masih pekerjaan tahap 5–6.
- Tidak ada tombol penyesuaian manual atau preview draft palsu. Aturan gaji,
  versi perhitungan dan endpoint pembayaran tidak diubah pada tahap ini.
- Tes kontrak hasil diperluas untuk data line, total divisi, hak aksi, rentang
  absensi serta tidak membocorkan tujuan rekening. Verifikasi browser/perangkat
  fisik dan kecocokan visual akhir masih belum dilakukan.

## Implementasi tahap 4

- Halaman Aturan & Komponen Gaji memakai tiga kelompok kartu: komponen absensi,
  periode/jadwal, dan potongan wajib. Seluruh warna mengikuti token tema existing.
- Pemilih perusahaan menggunakan daftar yang diberikan endpoint existing. Form
  perusahaan tetap terpasang saat berpindah pilihan agar isian belum disimpan
  tidak hilang karena pergantian pilihan. Batas akses backend existing belum
  diubah dalam tahap presentasi ini.
- Empat tarif existing tetap disimpan ke endpoint kebijakan: telat per menit,
  alpha per hari, reward per periode dan lembur per jam. Field wajib bernilai
  nonnegatif, mendukung dua angka desimal; nol berarti tarif dinonaktifkan.
- Alasan perubahan opsional dikirim ke audit log existing. Ada status menyimpan,
  perubahan belum tersimpan, berhasil, ringkasan error dan error tiap kolom.
- Link ke komponen, profil karyawan dan periode memakai route yang sudah ada.
- Transport/makan/dinas otomatis, lembur bertingkat, toleransi per kejadian,
  BPJS/pajak, penjadwalan dan versi efektif diberi label belum tersedia; tidak
  berupa input palsu dan tidak ikut payload penyimpanan.
- Penjelasan masa berlaku menyebut perhitungan berikutnya (termasuk revisi),
  bukan menjanjikan berlaku periode depan. Snapshot hasil lama dipertahankan.
- Tes existing kebijakan, perhitungan absensi dan lembur: 12 lulus, 55 assertions.
  Pemeriksaan visual pada browser/HP belum dilakukan. Tidak ada perubahan rumus
  atau migrasi database pada tahap ini.

## Implementasi tahap 5 — aturan existing

Tanggal: 7 Oktober 2026 (WIB). Pengguna memilih: pertahankan aturan existing,
terutama lengkapi penyesuaian manual dan ketepatan perhitungan. Dengan keputusan
ini, rumus screenshot, prorata dan penjadwalan versi efektif tidak diaktifkan.

- Kalkulator tetap memakai telat per menit, alpha tarif harian tetap, reward
  periode dan menit lembur yang disetujui × tarif per jam. Aritmetika nominal
  memakai BCMath, tanpa konversi float; setiap line dibulatkan ke 2 desimal
  (half away from zero), baru dijumlah menjadi entry/run. Reward termasuk bruto.
- Form tarif/profil menerima nominal desimal biasa nonnegatif, maksimal 12
  digit sebelum koma dan 2 digit desimal. Profil hanya menerima komponen
  earning aktif milik perusahaan karyawan, tanpa ID komponen duplikat.
- Kalkulator juga menolak komponen profil bukan earning/perusahaan lain pada
  data lama, agar potongan tidak keliru ditambahkan sebagai pendapatan.
- HRD dapat menambah pendapatan/potongan manual pada entry draft melalui panel
  rincian. Nama, jenis, nominal dan alasan wajib. Request UUID mencegah retry
  menambahkan nominal dua kali; pemakaian UUID yang sama untuk payload berbeda
  ditolak. Endpoint memeriksa role dan perusahaan, bukan hanya tombol frontend.
- Total entry/run/divisi diperbarui atomik di bawah row lock run lalu entry.
  Penyesuaian dan pembatalannya tidak boleh menyebabkan THP negatif.
- Pembatalan hanya untuk line manual, membutuhkan alasan; amount/rate menjadi
  nol dan metadata pembatalan tersimpan. Catatan dan UUID tetap ada sehingga
  retry penambahan setelah pembatalan tidak menghidupkan kembali nominal.
- Penambahan/pembatalan masuk audit log bersama pelaku, alasan, nilai sebelum/
  sesudah dan run terkait. Tidak memuat nomor rekening dalam audit penyesuaian.
- Revisi menyalin line manual (termasuk histori pembatalan) ke entry versi baru
  dengan copied_from_line_id, memperbarui total, dan mempertahankan versi lama.
  Jika karyawan dengan penyesuaian aktif tidak masuk run baru, revisi ditolak
  dengan pesan perbaikan profil/status agar keputusan manual tidak hilang.
- Penghitungan versi baru mengunci periode serta memeriksa ulang run terbaru
  untuk menolak perhitungan ganda dari request bersamaan. Tes SQLite bukan
  pengganti uji konkurensi pada DB produksi.
- Tidak ada migrasi atau perubahan data produksi. Backend pembayaran Finance,
  mesin BPJS/pajak, formula baru, prorata dan versi efektif masih di luar
  lingkup perubahan ini. Pembayaran Finance belum dimigrasikan dari float;
  ketepatan desimal tahap ini berlaku pada kalkulator dan penyesuaian manual.

Pengujian meliputi pembulatan nominal besar/pecahan, tarif lembur berdasarkan
menit, penambahan/pembatalan, audit, retry, role/perusahaan/status, rollback THP
negatif, revisi tanpa kehilangan penyesuaian, dan total beberapa karyawan.
Pengujian browser/HP untuk modal belum dilakukan.

Hasil verifikasi tahap 5: suite penuh sebelum regresi terakhir lulus 372 tes
(1.456 assertions); pengujian terarah sesudah penambahan regresi komponen salah
lulus 16 tes (130 assertions). TypeScript, PHPStan, lint dan build lulus pada
implementasi ini. Form profil juga mendukung nominal 2 desimal. Tidak ada
pengujian visual atau pengujian bank/transfer langsung pada tahap ini.
