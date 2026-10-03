# Diagnosis lokasi absensi

Pesan "Akurasi GPS terlalu rendah" berasal dari pemeriksaan `accuracy > 50`,
sebelum pemeriksaan radius. Angka accuracy adalah estimasi ketelitian koordinat
perangkat dalam meter, bukan jarak karyawan dari kantor. Jadi seseorang di dalam
kantor masih bisa ditolak bila ketelitian yang dilaporkan, misalnya, ±80 m.

Radius awal pada CompanySeeder adalah 30 m. Nilai di database server mungkin
berbeda. Memperbesar radius tidak menyelesaikan penolakan karena akurasi.

## Perubahan

- Pembacaan lokasi berulang menggunakan watchPosition (maximumAge 0), maksimal
  25 detik; hasil paling akurat dipilih. Berhenti lebih cepat bila akurasi ≤20 m.
  Pada batas waktu, hasil ≤50 m diterima untuk dikirim ke backend; hasil lebih
  buruk ditolak dengan penjelasan. Watch/timer dibersihkan ketika selesai.
- Permintaan izin ditolak langsung ditampilkan; error lokasi sementara masih
  diberi kesempatan pulih. Progres dan error tetap terlihat di dashboard.
- Pembatalan input foto ditangani melalui event cancel agar tombol bisa aktif
  lagi pada browser yang mendukung event tersebut.
- Backend menerima lokasi yang berada di salah satu kantor milik perusahaan
  karyawan. Sebelumnya hanya radius kantor terdekat yang diperiksa, sehingga
  kantor lain yang lebih luas dan mencakup titik tersebut bisa terabaikan.
- Penolakan backend dicatat sebagai `Attendance location rejected.` dengan
  alasan, ID pengguna, akurasi, dan bila relevan jarak/radius/ID kantor. Tidak
  mencatat foto atau koordinat perangkat. Gagal memperoleh lokasi di browser
  tidak mengirim POST, sehingga tidak tercatat dalam log backend.
- Batas akurasi dan radius tidak dilonggarkan otomatis. GPS browser tidak
  menjamin keberadaan fisik atau kebal pemalsuan koordinat.

## Pemeriksaan server setelah deploy

Push/pull seluruh perubahan, build frontend (`npm run build`), lalu refresh
browser. Tidak ada migrasi database untuk perubahan ini. Untuk melihat titik
kantor sebenarnya tanpa mengubah konfigurasi:

```bash
php artisan attendance:location-status
```

Pastikan titik pusat benar dan radius mencakup area kerja. Uji dengan beberapa
HP di posisi yang sama. Catat angka akurasi dan teks error yang tampil. Jika
accuracy tetap buruk, periksa izin lokasi presisi untuk browser/perangkat dan
coba dekat jendela/pintu. Jika accuracy baik tetapi radius tetap ditolak,
periksa koordinat kantor dan jangkauan area yang memang diizinkan.

Jangan menggunakan CompanySeeder untuk mengubah radius pada server berisi
karyawan. Perubahan radius/titik kantor perlu didasarkan pada pemeriksaan lokasi
sebenarnya. Jika gedung tetap tidak memungkinkan lokasi presisi, perlukan alur
verifikasi HRD/kehadiran alternatif; jangan otomatis menganggap lokasi valid.

## Verifikasi

`node --test tests/Frontend/attendance.test.mjs` menguji perbaikan hasil awal,
batas waktu, pemilihan hasil terbaik, penolakan izin, pemulihan error sementara,
cleanup, dan pembatalan kamera menggunakan simulasi API browser.
Tes PHP mencakup penolakan GPS buruk masuk/pulang, lokasi di luar radius,
beberapa lokasi kantor, dan pembatasan perusahaan. Pengujian perangkat fisik di
kantor tetap diperlukan; lingkungan pengembangan tidak mengukur sinyal di sana.

Referensi: https://developer.mozilla.org/en-US/docs/Web/API/GeolocationCoordinates/accuracy
https://developer.mozilla.org/en-US/docs/Web/API/Geolocation/watchPosition
