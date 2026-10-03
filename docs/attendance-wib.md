# Status absensi WIB tanpa toleransi

Jam masuk mengikuti `work_schedules.jam_masuk` perusahaan (seed: 08:30:00).
Toleransi 15 menit lama tidak lagi digunakan untuk menentukan status masuk.
Kolom toleransi lama tetap ada untuk kompatibilitas, tetapi tidak memengaruhi
perhitungan. Seeder baru memakai 0. Jangan menjalankan ulang CompanySeeder
pada data yang sudah ada karena seeder tersebut membuat perusahaan baru.

Backend menggunakan Asia/Jakarta, demikian juga tampilan jam/tanggal dashboard,
riwayat dan rekap HRD; zona waktu perangkat tidak mengubah tampilan.

- 08:30:00: tepat waktu.
- 08:30:01: telat, minimum tampilan 1 menit.
- 08:34:00 hingga 08:34:59: telat 4 menit (menit penuh yang berlalu).
- 08:35:00: telat 5 menit.

Data lama menyimpan status hasil perhitungan sebelumnya, sehingga pemasangan
kode saja tidak mengubah status historis. Setelah deploy backend dan build
frontend, segarkan cache konfigurasi di server (`php artisan config:cache`).
Untuk tanggal contoh screenshot, jalankan pratinjau:

```bash
php artisan attendance:recalculate-lateness 2026-10-03
```

Pastikan jam masuk perusahaan saat ini sama dengan jadwal pada tanggal itu,
kemudian terapkan:

```bash
php artisan attendance:recalculate-lateness 2026-10-03 --apply
```

Perintah memakai jadwal perusahaan saat ini karena absensi lama belum menyimpan
snapshot jadwal. Perusahaan dengan nol/lebih dari satu jadwal dilewati, bukan
ditebak. Tanggal wajib eksplisit. Perintah hanya mengoreksi status dan menit
keterlambatan; timestamp, foto, koordinat, dan data pulang tetap utuh. Nilai
sebelum/sesudah dicatat ke log aplikasi. Menjalankan ulang tidak menggandakan
perubahan. Snapshot payroll/review yang sudah disahkan tidak ikut diubah.

Timestamp lama diasumsikan disimpan sebagai waktu lokal WIB sesuai konfigurasi
project sebelumnya. Perintah ini bukan alat konversi data historis UTC.
Periksa konfigurasi lama terlebih dahulu bila server pernah menyimpan timestamp
dengan zona waktu berbeda. Perubahan lokal belum berarti situs publik terdeploy.
