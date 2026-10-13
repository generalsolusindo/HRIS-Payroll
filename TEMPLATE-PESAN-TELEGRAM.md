# Template Pesan Telegram — Sistem Izin (4 Kondisi)

Diambil persis dari `app/Services/TelegramNotificationService.php` yang aktif — jadi teks di bawah ini **sama persis** dengan yang beneran terkirim, bukan perkiraan.

---

## 🔵 KONDISI 1: Pengajuan Baru Masuk
*Terpicu saat: karyawan baru submit form izin*

**Template mentah:**
```
🔔 PENGAJUAN IZIN BARU

No. Pengajuan: {request_number}
Nama: {name}
Departemen: {department}
Jabatan: {position}
Jenis Izin: {type_label}
Tanggal Izin: {leave_date, format d/m/Y}
Alasan: {reason, atau "-" kalau kosong}
Status: 🔵 MENUNGGU PERSETUJUAN HRD

HRD meninjau terlebih dahulu. Setelah disetujui HRD, Manager memberikan keputusan final.
Login Web: {login_url}
```
**Tombol:** `Login Web Izin` → `{login_url}`

**Contoh terisi:**
```
🔔 PENGAJUAN IZIN BARU

No. Pengajuan: IZN-2026-000021
Nama: Budi Santoso
Departemen: General Solusindo
Jabatan: Admin Store
Jenis Izin: Izin Setengah Hari
Tanggal Izin: 13/10/2026
Alasan: Mengantar anak ke rumah sakit untuk kontrol rutin
Status: 🔵 MENUNGGU PERSETUJUAN HRD

HRD meninjau terlebih dahulu. Setelah disetujui HRD, Manager memberikan keputusan final.
Login Web: https://izin.delogic.net/login
```

---

## 🟡 KONDISI 2: Disetujui HRD (Tahap 1, Belum Final)
*Terpicu saat: HRD klik "Approve" — status pindah ke `pending_manager`, menunggu Manager*

**Template mentah:**
```
🟡 DISETUJUI HRD — MENUNGGU MANAGER

No. Pengajuan: {request_number}
Nama: {name}
Departemen: {department}
Jabatan: {position}
Jenis Izin: {type_label}
Tanggal Izin: {leave_date, format d/m/Y}
Alasan: {reason, atau "-" kalau kosong}
Disetujui HRD: {nama HRD}
Status: 🟡 MENUNGGU PERSETUJUAN MANAGER

Mohon Manager untuk meninjau dan memberikan keputusan final.
Login Web: {login_url}
```
**Tombol:** `Login Web Izin` → `{login_url}`

**Contoh terisi:**
```
🟡 DISETUJUI HRD — MENUNGGU MANAGER

No. Pengajuan: IZN-2026-000021
Nama: Budi Santoso
Departemen: General Solusindo
Jabatan: Admin Store
Jenis Izin: Izin Setengah Hari
Tanggal Izin: 13/10/2026
Alasan: Mengantar anak ke rumah sakit untuk kontrol rutin
Disetujui HRD: Tim HRD
Status: 🟡 MENUNGGU PERSETUJUAN MANAGER

Mohon Manager untuk meninjau dan memberikan keputusan final.
Login Web: https://izin.delogic.net/login
```

---

## 🟢 KONDISI 3: Disetujui Manager (Final)
*Terpicu saat: Manager klik "Approve" — status jadi `approved`, keputusan final*

**Template mentah:**
```
🟢 PENGAJUAN IZIN DISETUJUI (FINAL)

No. Pengajuan: {request_number}
Nama: {name}
Departemen: {department}
Jabatan: {position}
Jenis Izin: {type_label}
Tanggal Izin: {leave_date, format d/m/Y}
Alasan: {reason, atau "-" kalau kosong}
Disetujui HRD: {nama HRD}
Disetujui Manager: {nama Manager}
Status: 🟢 DISETUJUI

Silakan teruskan konfirmasi ke karyawan lewat tombol WhatsApp di bawah ini.
```
**Tombol:** `Kirim ke WhatsApp Karyawan` → `https://wa.me/{no_hp}?text={pesan WA terlampir di bawah}`

**Contoh terisi:**
```
🟢 PENGAJUAN IZIN DISETUJUI (FINAL)

No. Pengajuan: IZN-2026-000021
Nama: Budi Santoso
Departemen: General Solusindo
Jabatan: Admin Store
Jenis Izin: Izin Setengah Hari
Tanggal Izin: 13/10/2026
Alasan: Mengantar anak ke rumah sakit untuk kontrol rutin
Disetujui HRD: Tim HRD
Disetujui Manager: Manager
Status: 🟢 DISETUJUI

Silakan teruskan konfirmasi ke karyawan lewat tombol WhatsApp di bawah ini.
```

---

## 🔴 KONDISI 4: Ditolak (di Tahap HRD ATAU Manager)
*Terpicu saat: HRD atau Manager klik "Reject" — status jadi `rejected`, final di tahap manapun*

**Template mentah:**
```
🔴 PENGAJUAN IZIN DITOLAK

No. Pengajuan: {request_number}
Nama: {name}
Departemen: {department}
Jabatan: {position}
Jenis Izin: {type_label}
Tanggal Izin: {leave_date, format d/m/Y}
Alasan: {reason, atau "-" kalau kosong}
Ditolak oleh: {HRD/MANAGER} ({nama penolak})
Alasan Penolakan: {rejection_reason, atau "Tidak memenuhi ketentuan kebijakan kantor." kalau kosong}
Status: 🔴 DITOLAK

Silakan teruskan informasi penolakan ke karyawan lewat tombol WhatsApp di bawah ini.
```
**Tombol:** `Kirim ke WhatsApp Karyawan` → `https://wa.me/{no_hp}?text={pesan WA terlampir di bawah}`

**Contoh terisi (ditolak HRD):**
```
🔴 PENGAJUAN IZIN DITOLAK

No. Pengajuan: IZN-2026-000022
Nama: Siti Aminah
Departemen: Tabinaco
Jabatan: Marketing
Jenis Izin: Cuti
Tanggal Izin: 20/10/2026
Alasan: Acara keluarga di luar kota
Ditolak oleh: HRD (Tim HRD)
Alasan Penolakan: Kuota cuti tim sedang penuh di tanggal tersebut
Status: 🔴 DITOLAK

Silakan teruskan informasi penolakan ke karyawan lewat tombol WhatsApp di bawah ini.
```

**Contoh terisi (ditolak Manager, setelah sempat disetujui HRD):**
```
🔴 PENGAJUAN IZIN DITOLAK

No. Pengajuan: IZN-2026-000023
Nama: Andi Wijaya
Departemen: General Solusindo
Jabatan: SI Officer
Jenis Izin: Izin Keluar Kantor Sebentar
Tanggal Izin: 13/10/2026
Alasan: Mengambil dokumen di rumah
Ditolak oleh: MANAGER (Manager)
Alasan Penolakan: Sedang ada deadline project hari ini
Status: 🔴 DITOLAK

Silakan teruskan informasi penolakan ke karyawan lewat tombol WhatsApp di bawah ini.
```

---

## Bonus: Template Pesan WhatsApp (dibuka lewat tombol di Kondisi 3 & 4)

**Pesan WA — Disetujui:**
```
Halo {nama},

Pengajuan izin Anda telah DISETUJUI.

Nomor Pengajuan: {request_number}
Jenis Izin: {type_label}
Tanggal: {info tanggal sesuai jenis izin}
Status: DISETUJUI
Diproses oleh: {HRD/MANAGER}
Waktu proses: {tanggal + jam}

Silakan simpan pesan ini sebagai informasi pengajuan izin Anda.
```

**Pesan WA — Ditolak:**
```
Halo {nama},

Pengajuan izin Anda telah DITOLAK.

Nomor Pengajuan: {request_number}
Jenis Izin: {type_label}
Tanggal: {info tanggal sesuai jenis izin}
Status: DITOLAK

Alasan penolakan:
{rejection_reason}

Diproses oleh: {HRD/MANAGER}
Waktu proses: {tanggal + jam}

Silakan hubungi HRD apabila memerlukan informasi lebih lanjut.
```

---

## Catatan Penting Kalau Mau Dipakai Persis di Project Lain

Semua field dinamis (`{nama}`, `{alasan}`, dll) **wajib** di-`htmlspecialchars()` sebelum disisipkan ke teks (karena `parse_mode: HTML` dipakai saat kirim ke Telegram API) — supaya karyawan tidak bisa menyuntik tag HTML lewat input form. Jangan skip langkah escape ini walau cuma copy-paste teksnya.
