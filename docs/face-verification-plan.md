# Rancangan verifikasi wajah absensi — tahap 1

Tanggal audit: 3 Oktober 2026 (WIB).
Status: audit dan rancangan selesai; belum mengubah perilaku aplikasi.

## 1. Keputusan dan ruang lingkup

Keputusan pengguna pada tahap ini: **pendaftaran pertama langsung aktif;
penggantian wajah harus melalui HRD**. Tidak ada approval HRD untuk pendaftaran
pertama. Approval akun karyawan yang sudah ada tetap berlaku secara terpisah.
Pendaftaran pertama mengandalkan pemilik sesi; sistem belum membuktikan bahwa
wajah pertama itu sesuai identitas administratif karyawan. Ini keterbatasan
alurnya, bukan sesuatu yang boleh disebut telah diverifikasi HRD.

Target: akun aktif login → wajib daftar wajah bila belum punya → kamera absensi
→ pemeriksaan berkala → indikator → tombol aktif setelah wajah dan lokasi lolos
→ validasi foto final server → simpan masuk/pulang.

Pertahankan WIB, jadwal tanpa toleransi, menit bulat, radius maksimum 20 m,
ketelitian lokasi maksimum ±50 m, data absensi lama dan modul penggajian.
Kecocokan wajah bukan liveness. Tidak menjanjikan bebas foto/video palsu.

Audit berdasarkan working tree, termasuk perubahan Claude yang belum commit:

- `app/Http/Controllers/AttendanceController.php`
- `tests/Feature/AttendanceFaceMatchTest.php`
- `tests/Feature/ZZTempRealFaceBlockTest.php` (untracked)

Ketiganya tidak diedit atau dihapus pada tahap 1.

## 2. Temuan kode aktual

| Area / sumber                                  | Perilaku sekarang                                                                                              | Tindakan berikutnya                                                                                     |
| ---------------------------------------------- | -------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| `routes/web.php`, `bootstrap/app.php`          | Dashboard dan POST absen hanya auth + active; belum ada middleware wajah                                       | Tambah guard enrollment untuk dashboard/absensi, bukan seluruh route web                                |
| `EnsureUserIsActive`, `FortifyServiceProvider` | Login password menolak akun nonaktif; middleware active selalu redirect, termasuk permintaan JSON              | Pertahankan approval akun; tambahkan respons JSON terstruktur untuk endpoint verifikasi                 |
| `routes/settings.php`                          | Halaman/simpan wajah auth + konfirmasi password; belum active                                                  | Tambahkan active khusus route wajah; pastikan konfirmasi password/logout tidak terkena guard enrollment |
| `User::hasFaceEnrolled()`                      | Hanya memeriksa descriptor bukan null                                                                          | Validasi panjang 128, angka finite, data bisa didekripsi; jangan memberi bypass saat data rusak         |
| `FaceProfileController`                        | Simpan descriptor terenkripsi, foto sementara dihapus finally; daftar ulang langsung menimpa                   | Pertahankan cleanup/enkripsi; pisahkan pendaftaran pertama dari permintaan ganti wajah                  |
| `AttendanceController::assertFaceMatches()`    | Mismatch ditolak untuk user terdaftar; user tanpa wajah justru dilewatkan                                      | Wajib enrollment; tetap fail-closed pada verifikasi final                                               |
| `AttendanceController`                         | Semua kegagalan extractor diterjemahkan sebagai wajah tidak jelas                                              | Bedakan no_face/multiple_faces dari kegagalan proses/model                                              |
| `AttendanceController::storePhoto()`           | Nama tetap user/tanggal/masuk atau pulang; ditulis sebelum verifikasi                                          | File temporer unik per percobaan; jangan timpa/hapus foto absensi sah akibat request bersamaan          |
| `AttendanceController::clockOut()`             | Ekstraksi wajah berjalan di dalam transaksi dengan row lock                                                    | Pindah pemrosesan berat sebelum transaksi pendek, lalu cek ulang kondisi/versi saat commit              |
| `attendance-capture-screen.tsx`                | canSubmit hanya cameraReady + accuracyReady + !submitting                                                      | Tambahkan hasil wajah valid, radius valid, kesegaran lokasi, dan status frame siap                      |
| `attendance-capture-screen.tsx`                | Belum ada state pemeriksaan identitas atau indikator wajah                                                     | Tambah state machine dan pemeriksaan berkala                                                            |
| `attendance.ts`, dashboard props               | UI hanya kantor terdekat; radius props belum dicap; backend menerima salah satu kantor perusahaan dan cap 20 m | Samakan aturan pemilihan kantor dan radius efektif untuk UI/backend                                     |
| `watchBestPosition()`                          | Mempertahankan posisi terbaik tanpa batas usia; posisi baru yang kurang akurat diabaikan                       | Batasi usia posisi; invalidasi posisi lama agar indikator tidak terus hijau setelah pengguna bergerak   |
| `dashboard.tsx`                                | Error submit mengembalikan pengguna ke home                                                                    | Pertahankan layar kamera untuk error yang bisa dicoba ulang; reset status validasi                      |
| `FaceDescriptorExtractor`                      | Proses CLI baru setiap ekstraksi; timeout 30 s; panjang array dicek, semua elemen kemudian floatval            | Validasi tipe/nilai descriptor, klasifikasi error, benchmark sebelum menentukan interval                |
| `node-face/extract-descriptor.js`              | Model dimuat setiap panggilan; WASM; satu wajah; belum quality metrics/liveness                                | Pertahankan kompatibilitas WASM; tambah metrik kualitas yang terukur, optimasi berdasar benchmark       |
| `config/face.php`                              | Threshold 0.6                                                                                                  | Ini ambang jarak descriptor, bukan akurasi 60%; kalibrasi, jangan ubah untuk sekadar meloloskan tes     |

Dependency aktual: `@tensorflow/tfjs ^4.22.0`, backend WASM `^4.22.0`,
`@vladmandic/face-api ^1.7.15`, `@canvas/image ^2.0.0`. Tidak lagi memakai
`@tensorflow/tfjs-node` di manifest sekarang. Jangan memasang kembali native
backend tanpa memeriksa kompatibilitas CPU/server. Tahap ini bukan audit
kerentanan registry terbaru dan tidak menyatakan dependency bebas kerentanan.

## 3. Route dan otorisasi yang direncanakan

Nama route berikut rancangan, belum tersedia.

| Route                                                   | Respons dan otorisasi                                                                                 |
| ------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| GET dashboard                                           | auth → active → enrolled; tanpa wajah redirect ke settings/face                                       |
| GET/POST settings/face                                  | auth + active + konfirmasi password; pengecualian dari guard enrolled; POST hanya pendaftaran pertama |
| POST settings/face-change-requests                      | auth + active + enrolled + password confirmation + throttle; descriptor baru menunggu HRD             |
| GET hrd/face-change-requests                            | auth + active + role hrd; antrian perubahan                                                           |
| PATCH hrd/face-change-requests/{id}/approve atau reject | HRD; transaksi, keputusan sekali saja, alasan; larang menyetujui penggantian milik sendiri            |
| POST absen/face-verification                            | auth + active + enrolled + CSRF + throttle per user/session dan batas kapasitas; JSON                 |
| POST absen/masuk dan pulang                             | auth + active + enrolled; foto final, pemeriksaan lokasi/versi/duplikasi; Inertia seperti sekarang    |

Guard enrolled berlaku bagi semua role yang menggunakan dashboard/absensi,
tanpa pengecualian role untuk POST absen. Route kerja HRD tetap memakai guard
role yang ada sehingga kewajiban wajah tidak mengunci pengelolaan karyawan.

Konfirmasi password, logout, pending-approval, dan proses autentikasi tidak
boleh masuk redirect loop. Uji login password, 2FA, passkey dan intended URL.
Jika login menuju intended URL selain dashboard, gunakan penentu tujuan login
untuk mengarahkan akun aktif tanpa wajah ke enrollment tanpa kehilangan kontrol
atas whitelist tujuan setelah selesai.

Untuk JSON: 401 tidak login, 403 akun nonaktif/enrollment_required, 419 sesi/CSRF,
422 input invalid, 429 throttle (Retry-After), 503 mesin verifikasi tidak tersedia.
Jangan mengembalikan halaman HTML login sebagai hasil kecocokan wajah.

## 4. Kontrak verifikasi dan pengiriman akhir

Request preview multipart: photo + action (masuk/pulang) + attempt_id UUID +
sequence integer. user_id diambil hanya dari session. Terapkan batas bytes dan
dimensi/piksel gambar; resize terkontrol untuk menghindari beban decode besar.

Hasil bisnis 200:
`status`, `message`, `attempt_id`, `sequence`, `checked_at`, `expires_at`.
Status: matched, mismatch, no_face, multiple_faces, quality_failed jika metrik
sudah diimplementasikan. Service gagal menggunakan 503 service_unavailable.
Jangan kirim descriptor, foto referensi atau detail error proses internal.

Rencanakan receipt opaque acak server-side untuk matched, berumur pendek dan
terikat ke user, sesi, action, attempt, versi wajah, serta hash gambar preview.
Receipt hanya menunjukkan prapemeriksaan telah terjadi; tidak membuktikan foto
final identik. Payload mentah receipt tidak boleh hanya dipercaya dari browser.

Submit final: receipt + photo baru + lokasi/akurasi. Server wajib:

1. Memvalidasi receipt milik sesi/user/action, belum dipakai, belum kedaluwarsa.
2. Mengekstrak dan membandingkan foto final, walau receipt valid.
3. Memeriksa lokasi dan aturan absen yang berlaku.
4. Dalam transaksi pendek, cek ulang versi wajah dan kondisi attendance,
   konsumsi receipt secara atomik dan simpan tepat satu hasil.
5. Request gagal meminta verifikasi ulang; request ganda tidak menimpa hasil.

Storage receipt harus mendukung konsumsi atomik (database atau cache bersama
dengan lock yang sesuai deployment). Jangan memakai array cache produksi atau
cache per-worker untuk status yang harus konsisten lintas worker.
Waktu absen mengikuti waktu penerimaan request final oleh server (WIB), bukan
waktu browser atau waktu selesai inferensi. Tanggal/path diturunkan dari waktu
itu sekali untuk menghindari perbedaan saat melewati tengah malam.

## 5. State UI dan kesegaran

| State                                              | Warna / tombol                                               |
| -------------------------------------------------- | ------------------------------------------------------------ |
| initializing/checking                              | Oranye, nonaktif                                             |
| no_face/quality_failed                             | Oranye dan instruksi, nonaktif                               |
| mismatch/multiple_faces                            | Merah, nonaktif                                              |
| matched tetapi GPS belum valid                     | Label wajah cocok, label lokasi belum valid; tombol nonaktif |
| matched stabil + GPS valid + receipt masih berlaku | Hijau, aktif                                                 |
| camera_error/service_error                         | Pesan teknis tersendiri, nonaktif, coba lagi                 |
| submitting                                         | Nonaktif dan progres; jangan izinkan request kedua           |

Satu request preview aktif per layar. Jadwalkan berikutnya setelah respons,
bukan setInterval yang menumpuk. Pakai sequence/attempt untuk mengabaikan
respons lama; AbortController pada unmount/cancel. Kamera dianggap siap setelah
metadata/frame valid, bukan hanya getUserMedia berhasil.

Nilai awal untuk prototipe: 2 hasil cocok berturut-turut; jeda 2 detik setelah
respons; receipt 10 detik; usia lokasi maksimum 15 detik. **Belum angka produksi
tervalidasi**: tahap 7 mengukur dan menyesuaikan bersamaan dengan throttle.
Timer browser hanya membantu UI; masa berlaku tetap diperiksa server.

Reset kecocokan pada gagal, wajah hilang yang terdeteksi, stream ended,
perubahan akun/action, pendaftaran berubah, tab hidden atau receipt expired.
Saat tab kembali aktif wajib verifikasi ulang. Pemeriksaan berkala memiliki
jeda: tanpa deteksi lokal tambahan, perubahan wajah tidak diketahui seketika.
Validasi foto final menutup celah penyimpanan, bukan menjadikan UI real-time
sempurna. Deteksi lokal boleh mempercepat invalidasi, tetapi bukan otoritas.

## 6. Data pendaftaran dan perubahan wajah

Tahap 2 memakai descriptor yang ada; jangan menghapus atau mewajibkan semua
pengguna daftar ulang. Descriptor lama valid tetap dapat digunakan setelah
integritasnya diperiksa. Data korup/tidak valid diarahkan pemulihan, bukan bypass.

Tahap 3 rencanakan:

- `users.face_enrollment_version` integer monotonik; versi awal 1 untuk data lama
  valid dan 0 untuk belum terdaftar, dengan strategi backfill teruji.
- Metadata versi model/preprocessing agar descriptor beda model tidak dibandingkan
  secara diam-diam. Tidak mengasumsikan model berbeda kompatibel.
- `face_change_requests`: user_id, encrypted pending descriptor, versi wajah asal,
  model_version, status pending/approved/rejected/cancelled, alasan permintaan,
  requested_at, processed_by/at, decision_note.
- Histori keputusan tetap disimpan; descriptor pending dibersihkan setelah
  keputusan sesuai kebijakan retensi. Tidak menaruh descriptor pada log/JSON UI.

Wajah lama tetap aktif selama permintaan ganti menunggu; approval HRD mengganti
secara atomik dan menaikkan versi, membatalkan seluruh receipt sebelumnya.
Jika akun diduga diambil alih, HRD menonaktifkan akun melalui kebijakan terpisah.
Dua permintaan paralel/approval ganda harus diuji. HRD memeriksa identitas melalui
proses kantor, bukan hanya mengklik setuju tanpa dasar. Bukti foto jika dibutuhkan
memerlukan aturan penyimpanan privat dan retensi yang jelas; tidak otomatis
menyimpan ulang semua foto pendaftaran. Approver tetap lintas perusahaan seperti
role HRD sekarang; jangan diam-diam mengubah cakupan akses modul lain.

## 7. Penyimpanan foto dan pemrosesan

Gunakan nama file acak/UUID per percobaan, disk privat, cleanup finally pada
preview/penolakan. Jangan menimpa path masuk/pulang yang sama sebelum verifikasi.
Foto final yang lolos baru dipertahankan dan ditautkan ke row; kegagalan commit
membersihkan hanya file milik percobaan itu. Tentukan cleanup file yatim ketika
proses mati. Operasi filesystem tidak otomatis ikut rollback database.

Jangan menahan row lock selama proses model yang dapat mencapai timeout 30 s.
Setelah inferensi, transaksi pendek mengecek kembali status akun, versi descriptor,
receipt dan keunikan user/tanggal. Log teknis terstruktur tanpa descriptor/foto.

Tetap gunakan extractor interface agar CLI bisa diganti worker tanpa mengubah
alur bisnis. Benchmark CLI terlebih dahulu. Jika tidak memenuhi kebutuhan,
worker internal memuat model sekali, antrean terbatas, timeout/healthcheck,
restart terkelola, dan akses hanya lokal/terautentikasi. Jangan membuka endpoint
model langsung ke internet. Gagal model tidak boleh menjadi matched.

## 8. Urutan kerja tahap berikutnya

| Tahap | File/area utama                                                                | Kriteria selesai                                                        |
| ----- | ------------------------------------------------------------------------------ | ----------------------------------------------------------------------- |
| 2     | Middleware enrolled, bootstrap, routes, login response, User, tes auth/absen   | Wajib enrollment; HTML/JSON benar; tidak ada loop; tanpa bypass         |
| 3     | FaceProfileController, settings/face, request penggantian + HRD, model/migrasi | Daftar awal langsung aktif; ganti hanya HRD; versi dan cleanup benar    |
| 4     | Controller/request preview, service matcher, receipt store, limiter            | Kontrak JSON, integritas descriptor, isolasi akun/action/sesi, timeout  |
| 5     | AttendanceCaptureScreen, hook verification, attendance.ts, props dashboard     | Warna/state, kesegaran, satu request, radius sama dengan backend        |
| 6     | AttendanceController + receipt transaction + photo storage                     | Foto final diperiksa; concurrency tidak menimpa file/absensi            |
| 7     | Extractor/worker, benchmark, tes perangkat dan model                           | Ukuran performa/akurasi nyata; optimasi jika diperlukan                 |
| 8     | Deployment docs, checks/regression, smoke test server                          | Instalasi dapat diulang; rollback data aman; hasil server terverifikasi |

Sebelum tahap 6 selesai, tahap 2–5 merupakan implementasi bertahap; jangan
mengiklankannya sebagai alur produksi final yang seluruhnya telah aman.

## 9. Pengujian penerimaan

- Auth: guest, pending/rejected, aktif tanpa wajah, descriptor rusak, password,
  2FA/passkey, password confirmation, logout, intended URL.
- Enrollment: tepat satu wajah, invalid image, limit ukuran/piksel, descriptor
  finite 128, terenkripsi/tidak terserialisasi, perubahan wajib HRD, approval
  ganda/versi stale, email gagal tidak merusak enrollment.
- Preview: mock hasil bisnis dan error; sesi habis, CSRF, throttle, concurrency,
  receipt salah user/sesi/action, expiry, pendaftaran berubah.
- UI: state warna/tombol, gambar belum siap, late response, timer expiry tanpa
  frame baru, background tab, cleanup, kamera mati, GPS lama dan beberapa kantor.
- Final: foto berubah setelah hijau, request langsung, forged match flag, replay,
  klik ganda, database/file gagal, foto sah tidak terhapus; masuk/pulang.
- Regression: WIB, menit bulat, radius 20 m, akurasi 50 m, izin/HRD/penggajian.
- Nyata: dataset dengan izin dan asal jelas, orang sama/berbeda, variasi kondisi,
  HP/browser kantor, latency p50/p95, concurrency. Laporkan jumlah sampel serta
  false accept/reject; tidak menyimpulkan akurasi dari mock atau satu pasang foto.

## 10. Verifikasi tahap 1

Baseline terarah: jalankan PHPUnit untuk AttendanceFaceMatchTest,
FaceProfileTest, FaceDescriptorExtractorTest dan UserFaceDescriptorTest.
Hasil eksekusi: **28 tes lulus, 90 assertions**. Pemeriksaan sintaks controller
juga lulus. Mayoritas ekstraksi pada tes ini disimulasikan; bukan pengukuran
akurasi model. Seluruh suite, benchmark, dan uji perangkat tidak dijalankan
pada tahap audit ini.

`ZZTempRealFaceBlockTest.php` milik pekerjaan sebelumnya memakai dua berkas
absolut `/tmp/face-person-A.jpg` dan `/tmp/face-person-B.jpg`. Keduanya ditemukan
pada mesin ini, tetapi asal/izin dan reproduktibilitas tidak ditetapkan dalam
repo. Tidak dijalankan/dihapus pada audit ini. Jadikan integration test dengan
fixture berizin dan konfigurasi eksplisit pada tahap 7.

Tidak ada migrasi, perubahan dependency, penulisan data server, atau deployment
pada tahap 1. Dokumen ini merupakan hasil kerja tahap ini.
