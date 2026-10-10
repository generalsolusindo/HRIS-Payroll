# Tahap 1 — Aturan absensi sebagai dasar payroll

Tanggal pemeriksaan: 10 Oktober 2026, WIB.
Status: pemetaan selesai; keputusan pengguna untuk lupa pulang, setengah hari, dan pengelolaan reward dicatat pada 10 Oktober 2026. Nilai kebijakan reward per perusahaan masih harus ditentukan HRD sesuai kesepakatan Manager. Dokumen ini tidak mengubah tarif, data absensi, atau payroll.

## Dasar yang sudah disepakati

- Perhitungan per karyawan, kemudian agregasi divisi dan perusahaan.
- Pertahankan potongan telat per menit, alpha tarif harian tetap, dan lembur tarif per jam. Nominal mengikuti pengaturan perusahaan, bukan contoh screenshot.
- HRD memeriksa draft dan dapat menambah/membatalkan penyesuaian dengan alasan. Pengajuan, revisi, approval dan pembayaran mengikuti pembatasan status.
- Hasil lama dan slip harus mempertahankan snapshot perhitungannya.
- WIB (Asia/Jakarta) sebagai acuan waktu absensi.

## Pemetaan aturan

| Kondisi                                 | Kode saat ini                                                                                                                         | Rancangan / status keputusan                                                                                                                                                                                                                                                                                              |
| --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Batas masuk                             | AttendancePunctuality memakai jadwal perusahaan; seeder 08.35, toleransi 0. Arahan percakapan lama 08.30.                             | Belum menetapkan ulang: pastikan jadwal yang berlaku beserta tanggal efektif. Database produksi belum diperiksa. Jangan menyamakan waktu seeder dengan data server.                                                                                                                                                       |
| Menit telat                             | Lebih dari batas berarti telat; menit dibulatkan ke bawah, minimum 1 untuk keterlambatan kurang dari 1 menit.                         | Pertahankan cara existing untuk tahap ini; jangan diam-diam beralih ke pembulatan ke atas.                                                                                                                                                                                                                                |
| Toleransi potongan                      | Payroll mengalikan seluruh menit telat yang masuk total dengan tarif; tidak ada toleransi potongan terpisah.                          | Pertahankan existing; bila kelak ditambah, pisahkan dari status telat dan tentukan apakah mengurangi menit atau hanya sebagai ambang.                                                                                                                                                                                     |
| Hadir penuh                             | Durasi masuk–pulang >= 50% jadwal sudah dianggap present. Tidak ada pengurangan jam istirahat.                                        | Dokumentasikan batas existing; tepat 50% termasuk hadir penuh. Ini belum berarti benar-benar bekerja satu shift penuh.                                                                                                                                                                                                    |
| Setengah hari                           | Durasi < 50% jadwal; tunjangan 50%; gaji pokok tidak otomatis diprorata; menit telat tidak dijumlahkan.                               | Disepakati: durasi < 50% jadwal, tunjangan 50%, gaji pokok tidak otomatis diprorata, dan menit telat tetap masuk potongan dengan tarif existing.                                                                                                                                                                          |
| Lupa absen pulang                       | Hadir penuh dan worked_minutes diisi sepanjang jadwal, walau durasi sebenarnya tidak diketahui.                                       | Disepakati: perlu konfirmasi HRD sebelum payroll diajukan. Belum dikonfirmasi tidak otomatis berarti alpha. Bangun jalur konfirmasi sebelum blokir pengajuan diaktifkan.                                                                                                                                                  |
| Tidak ada absen masuk                   | Hari kerja tanpa izin disetujui menjadi alpha otomatis, termasuk saat hari belum selesai bila rekap dibuka.                           | Usulan tahap 2: hari berjalan/future belum dianggap alpha final; pastikan masa kerja dan jadwal sebelum menetapkan alpha.                                                                                                                                                                                                 |
| Izin/sakit/cuti disetujui               | Mendahului pemeriksaan absen; dianggap satu hari penuh. Tidak diberi tunjangan kehadiran, tidak dipotong alpha.                       | Pertahankan nominal existing sebelum ada keputusan lain. Izin sebagian jam perlu aturan tersendiri agar tidak menimpa kehadiran seharian.                                                                                                                                                                                 |
| Reward                                  | Syarat jumlah rekap lengkap, tidak ada telat, half_day, alpha, unresolved. Izin/sakit/cuti dan seluruh hari libur tidak menggugurkan. | Disepakati: kebijakan izin/sakit/cuti terhadap reward ditentukan melalui kesepakatan HRD–Manager dan diatur oleh HRD; bukan keputusan tetap yang ditanam dalam kode. Nilai kebijakan aktual belum ditentukan. Usulan tambahan: wajib ada hari kerja eligible; periode seluruhnya libur tidak otomatis mendapatkan reward. |
| Pulang cepat                            | Menit dicatat, tetapi tidak ada tarif potongan khusus; dapat menjadi half_day bila durasi < 50%.                                      | Jangan menambah potongan baru tanpa keputusan perusahaan. Pengaruh ke reward masih perlu ditetapkan.                                                                                                                                                                                                                      |
| Dinas luar                              | Klasifikasi business_trip dikenal rekap, tetapi generator izin tidak secara khusus memetakannya ke dinas.                             | Perlu alur persetujuan dinas dan definisi kelayakan tunjangan/reward sebelum otomatisasi dinas.                                                                                                                                                                                                                           |
| Hari kerja/libur                        | Kalender perusahaan mengungguli default Senin–Sabtu kerja, Minggu libur. Libur tidak alpha.                                           | Pertahankan default sambil memastikan pola perusahaan; shift malam belum didukung perhitungan durasi jadwal saat ini.                                                                                                                                                                                                     |
| Lembur                                  | Hanya menit lembur disetujui dalam rentang periode, dikali tarif per jam.                                                             | Pertahankan. Pulang lewat jadwal bukan persetujuan lembur otomatis.                                                                                                                                                                                                                                                       |
| Karyawan masuk/keluar di tengah periode | Generator memakai karyawan aktif saat ini, belum membatasi setiap hari terhadap masa kerja.                                           | Perlu masa kerja efektif agar hari sebelum mulai bekerja tidak dianggap alpha; jangan mengaktifkan prorata nominal tanpa aturan.                                                                                                                                                                                          |

## Keputusan pengguna yang disepakati

1. **Lupa pulang:** wajib konfirmasi HRD sebelum payroll diajukan. Hasil konfirmasi dan alasannya tercatat; jalur penyelesaian harus tersedia sebelum pengajuan diblokir.
2. **Setengah hari:** durasi kerja kurang dari 50% jadwal, tunjangan kehadiran 50%, gaji pokok tidak otomatis dipotong, dan menit telat tetap dikenai tarif existing. Batas tepat 50% mengikuti existing (present).
3. **Reward:** HRD mengatur kebijakan berdasarkan kesepakatan dengan Manager. Pengguna belum menetapkan bahwa izin, sakit, atau cuti selalu menggugurkan maupun selalu mempertahankan reward.

Rancangan pengaturan reward untuk tahap implementasi:

- Pengaturan per perusahaan untuk pengaruh izin, sakit, dan cuti terhadap kelayakan reward; nominal reward mengikuti pengaturan yang tersedia.
- HRD menyimpan aturan berikut alasan/catatan kesepakatan, tanggal berlaku dan versi. Nilai awal memerlukan keputusan HRD; jangan otomatis menganggap semua kategori menggugurkan.
- Payroll menyimpan snapshot aturan yang dipakai. Aturan baru tidak mengubah payroll yang sudah disetujui atau slip yang telah terbit.
- Penyesuaian individual tetap melalui penyesuaian draft beralasan dan approval payroll Manager.
- Persetujuan kebijakan lewat tombol Manager terpisah belum diminta pengguna; catatan kesepakatan tidak boleh ditampilkan sebagai bukti approval digital Manager.

Keputusan di atas adalah spesifikasi tahap berikutnya, belum perubahan perilaku aplikasi.

Keputusan lanjutan sebelum perubahan terkait diaktifkan: 08.30 vs 08.35 dan tanggal efektif; izin sebagian jam; pengaruh pulang cepat pada reward; hak tunjangan/reward dinas; masa kerja; kebijakan berlaku per perusahaan. Nominal tarif baru tidak diminta pada tahap ini karena memakai existing.

## Kontrak pelaksanaan tahap berikutnya

- Satu rekap menjadi sumber daftar periode dan payroll; klasifikasi, menit telat, hari tunjangan dan nominal konsisten.
- Simpan data mentah; koreksi merupakan catatan beralasan dengan pelaku dan waktu, bukan penghapusan bukti absen.
- Menu koreksi saat ini sudah dihapus. Jika konfirmasi HRD dipilih, bangun kembali jalur penyelesaian sebelum mengaktifkan blokir payroll.
- Data belum lengkap boleh terlihat dalam draft, tetapi tidak boleh dianggap sudah dikonfirmasi. Pengecualian dan pengaruh nominal harus tampak pada rincian.
- Perubahan sumber setelah kalkulasi harus menandai draft perlu hitung ulang. Pemeriksaan tidak cukup membandingkan review lama bila sumber absensi/izin berubah tanpa regenerasi.
- Penyesuaian manual ikut versi draft berikutnya dan tidak menggantikan perbaikan data sumber.
- Simpan versi aturan/tanggal efektif sebelum memberlakukan aturan baru pada periode lama. Tidak menghitung ulang hasil disetujui atau slip terbit secara diam-diam.
- Jangan menghitung manfaat dua kali ketika menambahkan makan/transport di atas tunjangan kehadiran existing.
- BPJS, pajak, otomatisasi jadwal dan transfer bank bukan keluaran tahap 1 ini.

## Contoh penerimaan untuk tahap implementasi

Gunakan D sebagai batas masuk, S sebagai durasi jadwal, T sebagai tarif telat, H sebagai tunjangan harian, A sebagai tarif alpha, L sebagai tarif lembur.

| Kasus                                        | Hasil yang harus diperiksa                                                                                        |
| -------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Masuk tepat D                                | Tepat waktu, menit telat 0.                                                                                       |
| Masuk D + 4 menit                            | Telat 4 menit; potongan 4 × T jika hari eligible.                                                                 |
| Masuk D + 30 detik / D + 4 menit 59 detik    | Existing: 1 / 4 menit, bukan angka pecahan.                                                                       |
| Durasi S/2 dan sedikit di bawahnya           | Existing: present dan half_day; tunjangan H dan 0,5 × H.                                                          |
| Half_day sekaligus telat                     | Tunjangan 0,5 × H; potongan telat tetap berlaku sesuai keputusan 2.                                               |
| Masuk tetapi belum pulang pada hari berjalan | Jangan menganggap durasi kerja final telah diketahui.                                                             |
| Lupa pulang setelah hari selesai             | Wajib konfirmasi HRD sebelum pengajuan; tersedia cara menyelesaikannya dan jejak koreksi.                         |
| Izin/sakit/cuti disetujui                    | Tidak alpha; reward mengikuti kebijakan perusahaan yang diatur HRD berdasarkan kesepakatan Manager (keputusan 3). |
| Tanpa absen pada hari kerja selesai          | Alpha dan potongan A, setelah memeriksa izin, masa kerja, dan jadwal.                                             |
| Hari libur / periode seluruhnya libur        | Tidak alpha; uji agar reward tidak muncul hanya karena nol pelanggaran.                                           |
| Lembur 90 menit disetujui                    | 1,5 × L; permintaan pending/rejected tidak dihitung.                                                              |
| Sumber absensi berubah setelah hitung draft  | Harus terdeteksi sebelum approval, lalu hitung ulang sesuai aturan.                                               |
| Koreksi / penyesuaian manual                 | Ada alasan/pelaku/waktu; total karyawan, divisi, dan perusahaan cocok.                                            |
| Data sebelum tanggal mulai bekerja           | Tidak otomatis dianggap alpha sebelum keputusan masa kerja diimplementasikan.                                     |

## Sumber kode yang diperiksa

- app/Services/AttendancePunctuality.php
- app/Services/AttendanceReviewGenerator.php
- app/Services/PayrollReadiness.php
- app/Http/Controllers/Hrd/PayrollRunController.php
- app/Http/Requests/Hrd/UpdatePayrollPolicyRequest.php
- database/seeders/CompanySeeder.php
- config/app.php
- routes/web.php

Pemeriksaan dilakukan pada kode lokal, bukan verifikasi pengaturan atau data produksi. Tidak menjalankan migrasi, seeding, atau perhitungan gaji pada tahap ini.


## Pelaksanaan langkah 2 — 10 Oktober 2026

Diterapkan: lupa absen pulang menjadi draft/unresolved; form konfirmasi pada Rekap Periode meminta jam pulang WIB dan alasan, tersedia setelah hari selesai; hanya HRD perusahaan yang bersangkutan. Konfirmasi menyimpan versi, pelaku, waktu dan audit, tanpa mengubah foto/jam mentah absensi. Hadir/setengah hari ditentukan dari durasi terkonfirmasi. Menit telat half_day masuk rekap dan payroll. Draft lama tanpa konfirmasi atau dengan total telat lama diblokir sebelum pengajuan; perbarui draft sesudah konfirmasi.

Batas langkah ini: konfirmasi jam pulang pada tanggal yang sama, bukan shift lintas hari. Jalur ini khusus lupa pulang, bukan editor semua jenis koreksi absensi. Kebijakan reward configurable, jam masuk 08.30/08.35, izin parsial, masa kerja/prorata dan komponen tambahan masih pekerjaan berikutnya; tarif existing tidak diganti.
