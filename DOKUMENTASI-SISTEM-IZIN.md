# Dokumentasi Sistem Perizinan Kantor (Web Izin)

Dokumen ini menjelaskan konsep lengkap sistem pengajuan izin karyawan — mulai dari jenis izin, alur approval, integrasi Telegram, sampai struktur database — sebagai referensi untuk diimplementasikan ulang di project lain (mis. HRIS-Payroll).

---

## 1. Filosofi Dasar

Sistem ini dibangun dengan prinsip **zero-friction untuk karyawan**:

- Karyawan **tidak punya akun**, **tidak login**, dan **tidak punya dashboard**.
- Karyawan cukup membuka satu form publik (`/ajukan-izin`), isi data, submit — selesai.
- Setiap pengajuan menyimpan **snapshot identitas** karyawan (nama, email, no. HP, departemen, jabatan) langsung di baris data pengajuan itu sendiri — bukan relasi ke tabel akun karyawan (karena memang tidak ada tabel akun karyawan). Ini menjaga integritas data historis: walau jabatan karyawan berubah di kemudian hari, data pengajuan lama tetap mencerminkan kondisi saat diajukan.
- Karyawan bisa mengecek status pengajuannya sendiri kapan saja lewat `/cek-status`, cukup dengan **nomor pengajuan + email** (verifikasi dua faktor sederhana tanpa akun).

Hanya staf internal (**HRD** dan **Manager**) yang punya akun login, karena mereka yang memproses (approve/reject) pengajuan.

---

## 2. Role & Hak Akses

| Role (nilai di DB) | Label di UI | Login? | Akses |
|---|---|---|---|
| — (karyawan, tanpa akun) | Karyawan | Tidak | Form pengajuan publik, cek status mandiri |
| `hrd` | **HRD** | Ya | Dashboard HRD, approve/reject tahap 1, riwayat, laporan |
| `admin` | **Manager** | Ya | Dashboard Manager, approve/reject tahap 2 (final), riwayat, laporan |

Catatan penting: nilai kolom `role` di database untuk Manager tetap `admin` (alasan historis/kompatibilitas middleware), tapi **di semua tampilan & pesan selalu dilabeli "Manager"**, bukan "Admin".

**Login staf** menggunakan **username + password** (bukan email) sejak revisi terakhir. Field `email` di tabel `users` tetap ada sebagai data kontak, tapi bukan kredensial login.

**Middleware proteksi route**:
- `auth` — wajib login untuk semua route internal (`/dashboard`, `/pengajuan`, dll).
- `role:admin` — khusus Manager (contoh: `/admin/dashboard`).
- `role:hrd` — khusus HRD (contoh: `/hrd/dashboard`).
- `role:admin,hrd` — bisa diakses keduanya (list pengajuan, approve, reject, riwayat, laporan, profil).

---

## 3. Jenis-Jenis Izin (6 Tipe)

Field `type` pada tabel `leave_requests` punya 6 nilai valid. Setiap tipe punya field dinamis dan aturan validasi sendiri-sendiri (form berubah tampilan sesuai tipe yang dipilih, pakai Alpine.js di sisi frontend; validasi final selalu di backend/Form Request).

### 3.1 Izin Terlambat (`late`)
- Hanya untuk **tanggal hari ini**.
- Field: `estimated_arrival` (estimasi jam kedatangan), `reason`, `agreement` (centang pernyataan persetujuan konsekuensi).
- **Estimasi kedatangan maksimal pukul 09.30 WIB** — lewat itu ditolak.
- Submission **setelah pukul 07.00 WIB** wajib centang `emergency` + isi `emergency_reason`.
- `agreement` wajib dicentang.

### 3.2 Izin Setengah Hari (`half_day`)
- Wajib diajukan **minimal H-1** (tidak bisa untuk hari ini).
- Field: `start_time` (Jam Mulai), `end_time` (Jam Selesai), `reason`.
- `end_time` harus lebih besar dari `start_time`.
- **Jam Mulai boleh pukul berapa saja** (pagi/siang/sore, tidak dibatasi posisinya).
- **Durasi (`end_time` - `start_time`) maksimal 4 jam.** Konsepnya: jam kerja kantor 08.30–16.30 WIB (8 jam total); kalau izin ≤4 jam, sisa jam kerja hari itu otomatis ≥4 jam, di posisi manapun dalam sehari (bisa pagi, siang, atau sore).

### 3.3 Izin Keluar Kantor Sebentar (`temporary_exit`)
- Bisa untuk **hari ini atau tanggal mendatang**.
- Field: `start_time` (Jam Keluar), `end_time` (Estimasi Jam Kembali), `reason`.
- **Tidak ada batas durasi.**

### 3.4 Cuti (`leave`)
- Wajib diajukan **minimal H-7** sebelum tanggal cuti (untuk keperluan koordinasi delegasi kerjaan).
- Field: `duration` (jumlah hari, input manual 1–30), `reason`.
- Durasi dalam satuan **hari kalender**.

### 3.5 Izin Darurat (`emergency`)
- Hanya untuk **tanggal hari ini**.
- Field: `reason`, `contactable` (radio: bisa/tidak bisa dihubungi untuk koordinasi mendesak — **wajib dipilih**).
- **Submit maksimal pukul 08.30 WIB** (jam kantor mulai). Lewat itu, pengajuan **ditolak validasi** dan dianggap **"Alpha"** (ketidakhadiran tanpa izin) — **tidak bisa dialihkan ke tipe izin lain**.
- Tidak perlu centang `emergency` terpisah — tipe ini secara inheren darurat.

### 3.6 Izin Sakit (`sick`)
- Bisa untuk hari ini atau tanggal mendatang.
- Field: `duration` (estimasi lama istirahat, dalam hari), `reason`, `contactable` (wajib dipilih), lampiran surat dokter (opsional/kondisional).
- Lapor **sebelum pukul 08.30 WIB** = normal. Lapor **pada/setelah 08.30 WIB di hari H** = wajib centang `emergency` + isi `emergency_reason`.
- **Surat dokter wajib diunggah** (field `attachments[]` atau `attachment`) jika estimasi sakit **lebih dari 1 hari**.

### Tipe Legacy (sudah tidak bisa diajukan baru, hanya untuk kompatibilitas data lama)
- `early_departure` ("Izin Pulang Lebih Awal") — dihapus dari pilihan, konsepnya melebur ke `half_day`.
- `personal` — sudah di-migrate otomatis menjadi `emergency`.
- Kolom `half_day_type` — sisa dari desain lama (dulu ada sub-jenis di Izin Setengah Hari), sekarang selalu `NULL`, tidak perlu dibuat ulang di project baru.

### Field yang Selalu Ada di Semua Tipe
`name`, `email`, `phone`, `department` (`General Solusindo` atau `Tabinaco`), `position` (12 pilihan jabatan tetap), `leave_date`, `reason` (hampir selalu wajib kecuali beberapa kasus), lampiran file opsional (`attachments[]`, mime `pdf,jpg,jpeg,png`, maks 5MB/file).

---

## 4. Alur Approval 2 Tahap (HRD → Manager)

```
                    Pengajuan masuk
                    status = 'pending'
                           │
                           ▼
                     ┌───────────┐
                     │ HRD review │   (hanya role 'hrd' yang bisa aksi di tahap ini)
                     └───────────┘
                     ╱             ╲
            approve ╱               ╲ reject
                   ╱                 ╲
                  ▼                   ▼
   status = 'pending_manager'   status = 'rejected'  (FINAL)
                  │
                  ▼
           ┌─────────────┐
           │Manager review│   (hanya role 'admin' yang bisa aksi, dan HANYA jika
           └─────────────┘    hrd_decision sudah 'approved')
           ╱             ╲
  approve ╱               ╲ reject
         ╱                 ╲
        ▼                   ▼
status='approved' (FINAL)  status='rejected' (FINAL)
```

**Aturan intinya** (lihat `LeaveRequest::canBeProcessedBy()`):
```php
'pending'         => $user->role === 'hrd',
'pending_manager' => $user->role === 'admin' && $this->hrd_decision === 'approved',
```

- **Penolakan di tahap manapun langsung FINAL** — tidak perlu kedua pihak menolak.
- **Persetujuan butuh KEDUANYA setuju** — HRD approve saja belum final, masih menunggu Manager.
- Proses approve/reject dibungkus **database transaction dengan row lock** (`lockForUpdate()`) supaya tidak ada race condition kalau 2 orang klik approve bersamaan.
- Setiap pengajuan yang sudah final (`approved`/`rejected`) tidak bisa diproses ulang — dicek via `isPending()`.

**Jejak data yang disimpan** (2 pasang kolom approval terpisah):
| Kolom | Diisi kapan |
|---|---|
| `hrd_processed_by`, `hrd_processed_at`, `hrd_decision`, `hrd_note` | Saat status masih `pending` (tahap HRD) |
| `processed_by`, `processed_at`, `approval_note` / `rejection_reason` | Saat keputusan **final** dibuat (HRD kalau langsung reject, atau Manager kalau lanjut ke tahap 2) |

Jadi `processed_by` selalu berisi **siapa yang membuat keputusan FINAL** (bisa HRD, bisa Manager — tergantung siapa yang mengakhiri proses), sedangkan `hrd_processed_by` selalu berisi **siapa HRD yang mereview tahap 1** (hanya terisi kalau sempat melewati tahap HRD).

---

## 5. Integrasi Telegram — Konsep Lengkap

### 5.1 Arsitektur

Notifikasi dikirim ke **satu grup Telegram internal** (tempat HRD & Manager berada) lewat **Telegram Bot API**, dipicu otomatis di 4 momen siklus hidup pengajuan. Implementasi di `App\Services\TelegramNotificationService`, dipanggil dari `App\Services\LeaveRequestService` (untuk approve/reject) dan `PublicLeaveRequestController` (untuk pengajuan baru).

**Desain fail-safe**: semua pemanggilan Telegram dibungkus try-catch di level pemanggil (`LeaveRequestService::dispatchXxxTelegramNotification()`). Kalau Telegram API gagal/timeout, proses approve/reject di database **tetap berhasil** — hanya notifikasinya yang gagal (dicatat ke log aplikasi), tidak menghentikan alur bisnis.

### 5.2 Konfigurasi

File config: `config/telegram.php`
```php
return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'chat_id' => env('TELEGRAM_CHAT_ID'),
    'login_url' => env('TELEGRAM_LOGIN_URL', 'https://izin.delogic.net/login'),
];
```

Diisi lewat environment variable di `.env` (**jangan pernah di-hardcode di kode / commit ke git** — selalu lewat `.env` yang di-gitignore):

| Variable | Isi | Cara dapat |
|---|---|---|
| `TELEGRAM_BOT_TOKEN` | Token bot Telegram | Buat bot baru via chat `@BotFather` di Telegram (`/newbot`), copy token yang diberikan |
| `TELEGRAM_CHAT_ID` | ID grup/chat tujuan notifikasi | Masukkan bot ke grup HRD/Manager, kirim 1 pesan apa saja di grup, lalu panggil `https://api.telegram.org/bot<TOKEN>/getUpdates` — ID grup muncul di `result[0].message.chat.id` (biasanya angka negatif untuk grup, contoh: `-1001234567890`) |
| `TELEGRAM_LOGIN_URL` | URL halaman login staf (dipakai di tombol notifikasi) | URL production dengan HTTPS, contoh: `https://izin.delogic.net/login` |

**Catatan penting soal tombol inline di pesan Telegram**: Telegram API **menolak tombol dengan URL `http://`** — hanya menerima `https://`. Kalau `TELEGRAM_LOGIN_URL` (atau URL WhatsApp) bukan `https://`, sistem otomatis mengirim pesan **tanpa tombol** (fallback aman), bukan error.

### 5.3 Empat Jenis Notifikasi & Template Teks Persis

Semua pesan pakai `parse_mode: HTML` dan field-field dinamis **selalu di-escape dengan `htmlspecialchars()`** sebelum disisipkan (mencegah pengguna menyuntik tag HTML/format aneh lewat input form, mis. field nama atau alasan).

Komponen "ringkasan pengajuan" yang dipakai berulang di semua jenis pesan (fungsi `formatRequestSummary()`):
```
No. Pengajuan: <b>{request_number}</b>
Nama: {name}
Departemen: {department}
Jabatan: {position}
Jenis Izin: {type_label}
Tanggal Izin: {leave_date, format d/m/Y}
Alasan: {reason, atau "-" kalau kosong}
```

---

**A. Pengajuan Baru Masuk** (dipicu di `PublicLeaveRequestController::store()`, tepat setelah data berhasil disimpan ke DB)

```
🔔 PENGAJUAN IZIN BARU

{ringkasan pengajuan}
Status: 🔵 MENUNGGU PERSETUJUAN HRD

HRD meninjau terlebih dahulu. Setelah disetujui HRD, Manager memberikan keputusan final.
Login Web: {login_url}
```
Tombol: **"Login Web Izin"** → `{login_url}`.

Hasil pengiriman disimpan ke kolom `telegram_status` (`sent`/`failed`), `telegram_sent_at`, `telegram_error` pada baris pengajuan itu sendiri — ini **satu-satunya** jenis notifikasi yang statusnya di-tracking di database (3 jenis lainnya hanya dicatat ke log kalau gagal, tidak update kolom di tabel `leave_requests`).

---

**B. HRD Approve (Tahap 1, belum final)** (dipicu di `LeaveRequestService` saat status berubah jadi `pending_manager`)

```
🟡 DISETUJUI HRD — MENUNGGU MANAGER

{ringkasan pengajuan}
Disetujui HRD: {nama HRD}
Status: 🟡 MENUNGGU PERSETUJUAN MANAGER

Mohon Manager untuk meninjau dan memberikan keputusan final.
Login Web: {login_url}
```
Tombol: **"Login Web Izin"** → `{login_url}`.

---

**C. Manager Approve (Final)** (dipicu saat status berubah jadi `approved`)

```
🟢 PENGAJUAN IZIN DISETUJUI (FINAL)

{ringkasan pengajuan}
Disetujui HRD: {nama HRD}
Disetujui Manager: {nama Manager}
Status: 🟢 DISETUJUI

Silakan teruskan konfirmasi ke karyawan lewat tombol WhatsApp di bawah ini.
```
Tombol: **"Kirim ke WhatsApp Karyawan"** → link `wa.me/{no_hp_karyawan}` dengan pesan approval ter-template otomatis (lihat §6).

---

**D. Ditolak (di tahap manapun, HRD atau Manager)** (dipicu saat status berubah jadi `rejected`)

```
🔴 PENGAJUAN IZIN DITOLAK

{ringkasan pengajuan}
Ditolak oleh: {HRD/MANAGER} ({nama penolak})
Alasan Penolakan: {rejection_reason, atau pesan default kalau kosong}
Status: 🔴 DITOLAK

Silakan teruskan informasi penolakan ke karyawan lewat tombol WhatsApp di bawah ini.
```
Tombol: **"Kirim ke WhatsApp Karyawan"** → link `wa.me/{no_hp_karyawan}` dengan pesan penolakan ter-template otomatis.

### 5.4 Skema Warna Status (Bulat Emoji)

| Warna | Arti |
|---|---|
| 🔵 Biru | Pengajuan baru masuk / masih menunggu HRD |
| 🟡 Kuning | Sudah disetujui HRD, menunggu Manager |
| 🟢 Hijau | Disetujui final (Manager) |
| 🔴 Merah | Ditolak (di tahap manapun) |

### 5.5 Request HTTP ke Telegram API

```
POST https://api.telegram.org/bot{TELEGRAM_BOT_TOKEN}/sendMessage
Body (form):
  chat_id: {TELEGRAM_CHAT_ID}
  text: {pesan di atas}
  parse_mode: HTML
  disable_web_page_preview: true
  reply_markup: {"inline_keyboard":[[{"text":"...","url":"..."}]]}   // hanya kalau URL tombol https://
```
Timeout request: 10 detik. Kalau gagal/exception, dicatat ke `storage/logs/laravel.log` dengan prefix pesan yang jelas per jenis notifikasi (mis. "Gagal mengirim notifikasi persetujuan HRD Telegram untuk pengajuan ...").

---

## 6. Notifikasi Lain (Email & WhatsApp)

### Email
Dikirim otomatis ke **email karyawan** (bukan grup) hanya saat keputusan **final** tercapai (approved/rejected) — tidak dikirim di tahap HRD-approve-saja. Pakai Laravel Mailable (`LeaveRequestApprovedMail`, `LeaveRequestRejectedMail`), status tracking di kolom `email_status`, `email_sent_at`, `email_error` pada tabel `leave_requests`. Sama-sama fail-safe (try-catch, tidak menghentikan proses approve/reject).

### WhatsApp (Click-to-Chat, bukan API resmi)
Tidak pakai WhatsApp Business API — cuma link `https://wa.me/{nomor}?text={pesan}` yang membuka WhatsApp dengan pesan **sudah ter-isi otomatis**, tinggal staf klik "Kirim". Dipakai di 2 tempat:
1. **Manual** — tombol di halaman detail pengajuan (`/pengajuan/{id}`), staf klik kapan saja setelah approve/reject.
2. **Otomatis via Telegram** — tombol langsung di notifikasi Telegram (poin C & D di atas), staf tinggal klik dari Telegram, tidak perlu buka web dulu.

Template pesan WhatsApp (`WhatsAppMessageService`):
- **Approved**: `Halo {nama}, Pengajuan izin Anda telah DISETUJUI. Nomor: {request_number}, Jenis: {type_label}, Tanggal: {info tanggal sesuai tipe}, Diproses oleh: {HRD/MANAGER}, Waktu: {tanggal+jam}.`
- **Rejected**: sama + `Alasan penolakan: {rejection_reason}`.

Normalisasi nomor HP: `08xxx` → `628xxx` (format internasional tanpa `+`).

---

## 7. Struktur Database Lengkap

### 7.1 Tabel `users` (akun staf HRD & Manager)
```
id                  bigint, PK
name                string
username            string, UNIQUE, nullable   -- kredensial login
email               string, UNIQUE              -- data kontak, BUKAN kredensial login
email_verified_at   timestamp, nullable
password            string (hashed)
phone               string, nullable
role                string(20), default 'hrd'   -- 'admin' (=Manager di UI) atau 'hrd'
remember_token      string
created_at / updated_at
```

### 7.2 Tabel `leave_requests` (tabel inti)
```
id                   bigint, PK
request_number       string(50), UNIQUE          -- format: IZN-YYYY-XXXXXX

-- Snapshot identitas karyawan (bukan relasi ke users) --
name                 string
email                string, indexed
phone                string(50)
department           string(50)
position             string

-- Detail izin --
type                 string(20), indexed         -- late|half_day|temporary_exit|leave|emergency|sick
leave_date           date, indexed
start_time           time, nullable
end_time             time, nullable
half_day_type        string(50), nullable        -- legacy, selalu NULL
estimated_arrival    time, nullable
duration             decimal(5,2), nullable
reason               text, nullable
emergency            boolean, default false
emergency_reason     text, nullable
contactable          boolean, nullable

-- Status & approval final --
status               string(20), default 'pending', indexed  -- pending|pending_manager|approved|rejected
processed_by         FK → users.id, nullable, null on delete
processed_at         timestamp, nullable
approval_note        text, nullable
rejection_reason     text, nullable

-- Approval tahap HRD (terpisah) --
hrd_processed_by     FK → users.id, nullable, null on delete
hrd_processed_at     timestamp, nullable
hrd_decision         string(20), nullable        -- 'approved' atau 'rejected'
hrd_note             text, nullable

-- Tracking notifikasi --
email_status         string(20), default 'pending'    -- pending|sent|failed
email_sent_at        timestamp, nullable
email_error          text, nullable
telegram_status      string(20), default 'pending'    -- hanya di-update untuk notifikasi "pengajuan baru"
telegram_sent_at     timestamp, nullable
telegram_error       text, nullable

created_at / updated_at
```

### 7.3 Tabel `leave_request_attachments`
```
id                  bigint, PK
leave_request_id    FK → leave_requests.id, CASCADE on delete
file_name           string              -- nama file asli
file_path           string(500)         -- path di storage privat (nama disk di-random)
mime_type           string(100)
file_size           unsignedBigInteger (bytes)
created_at / updated_at
```
File fisik disimpan di **disk privat** (bukan public), diakses lewat route terotorisasi (`/pengajuan/{id}/attachment/{attachment_id}`) yang hanya bisa diakses staf login — tidak bisa diakses langsung lewat URL publik.

### 7.4 Tabel `leave_request_sequences`
```
year              unsignedSmallInteger, PK   -- contoh: 2026
last_sequence     unsignedInteger, default 0
```
Dipakai `LeaveRequestService::generateRequestNumber()` untuk generate nomor urut **atomic per tahun** (pakai `lockForUpdate()` dalam DB transaction supaya tidak bentrok kalau ada 2 submission bersamaan). Format hasil: `IZN-{tahun}-{6 digit angka, leading zero}`.

### 7.5 Relasi Antar Tabel (Eloquent)

```
User
 ├─ hasMany(LeaveRequest, 'processed_by')      -- sebagai processor() dari sisi LeaveRequest
 └─ hasMany(LeaveRequest, 'hrd_processed_by')  -- sebagai hrdProcessor() dari sisi LeaveRequest

LeaveRequest
 ├─ belongsTo(User, 'processed_by')      as processor()      -- siapa yang memutuskan FINAL
 ├─ belongsTo(User, 'hrd_processed_by')  as hrdProcessor()   -- siapa HRD-nya
 └─ hasMany(LeaveRequestAttachment)      as attachments()

LeaveRequestAttachment
 └─ belongsTo(LeaveRequest)
```

Tidak ada foreign key dari `leave_requests` ke tabel karyawan manapun — semua data karyawan murni snapshot teks (lihat §1).

---

## 8. Bagian HRD — Tabel & Konsep yang Terelasi

**Tabel yang disentuh HRD**: `leave_requests` (baca semua, tulis kolom `hrd_processed_by`/`hrd_processed_at`/`hrd_decision`/`hrd_note`, dan kalau langsung reject juga kolom `processed_by`/`processed_at`/`rejection_reason`), `leave_request_attachments` (baca, untuk verifikasi lampiran mis. surat dokter), `users` (dirinya sendiri sebagai `hrd_processed_by`).

**Konsep dashboard HRD** (`HrdDashboardController`):
- Statistik: `total` (semua pengajuan), `pending` = `COUNT(status='pending')` (yang **menunggu aksi HRD**), `approved`, `rejected`, `today` (masuk hari ini).
- List pengajuan dengan filter: status, type, department, search (nomor/nama/email) — eager load relasi `processor`.
- Bisa approve/reject **hanya** pengajuan berstatus `pending` (lihat `canBeProcessedBy`) — begitu approve, pengajuan "lepas" dari radar HRD (pindah jadi tanggung jawab Manager), begitu reject, selesai.

**Yang membedakan HRD dari Manager**: HRD adalah **gatekeeper tahap 1** — penyaring awal. HRD tidak bisa memproses pengajuan yang statusnya `pending_manager` (sudah bukan wewenangnya lagi).

---

## 9. Bagian Manager — Tabel & Konsep yang Terelasi

**Tabel yang disentuh Manager**: sama seperti HRD (`leave_requests`, `leave_request_attachments`, `users` sebagai dirinya di `processed_by`), **plus** akses ke `/laporan` (export CSV laporan, lihat `ReportController` — biasanya fitur ini lebih ditujukan untuk role Manager/Admin walau secara middleware bisa diakses `role:admin,hrd` juga).

**Konsep dashboard Manager** (`AdminDashboardController`):
- Statistik: `pending` = `COUNT(status='pending_manager')` (beda dengan HRD! — Manager cuma peduli pengajuan yang **sudah lolos HRD** dan menunggu keputusan dia).
- List & filter sama persis strukturnya dengan HRD, tapi makna "pending" beda konteks.
- Bisa approve/reject **hanya** pengajuan berstatus `pending_manager` **dan** `hrd_decision === 'approved'` — Manager **tidak bisa** memproses pengajuan yang masih di tahap HRD (`status='pending'`) sama sekali, walau dia login dan buka halaman detailnya (akan kena `403 Forbidden` via `abort_unless(canBeProcessedBy())`).

**Yang membedakan Manager dari HRD**: Manager adalah **pemegang keputusan final** — begitu dia approve, baru karyawan benar-benar dianggap diizinkan. Manager approve = otomatis trigger notifikasi Telegram dengan tombol WhatsApp (karena di titik ini keputusan sudah pasti, saatnya informasikan ke karyawan).

---

## 10. Keamanan yang Diterapkan

- **Session-gated success page**: halaman "pengajuan berhasil" (`/pengajuan/sukses/{request_number}`) hanya bisa diakses oleh sesi browser yang baru saja submit form itu (disimpan di Laravel session), bukan oleh siapapun yang menebak nomor urut — karena nomor pengajuan bersifat sequential dan predictable.
- **Cek status 2 faktor**: wajib nomor pengajuan + email sekaligus, tidak bisa hanya salah satu.
- **File lampiran privat**: disimpan di disk privat, nama file di-random, diakses lewat route terautentikasi.
- **Rate limiting**: `throttle:30,1` untuk submit pengajuan, `throttle:10,1` untuk cek status & login.
- **XSS protection**: semua input dinamis di-escape (`htmlspecialchars()` untuk Telegram, Blade `{{ }}` auto-escape untuk halaman web).
- **Role-based middleware**: setiap route internal dijaga `role:admin`/`role:hrd`/`role:admin,hrd` sesuai kebutuhan.
- **Row locking** saat approve/reject untuk cegah race condition (2 orang approve bersamaan).
- **Password & token tidak pernah di-hardcode** di kode — selalu lewat `.env` yang di-gitignore.

---

## 11. Ringkasan Routes

| Route | Method | Akses | Fungsi |
|---|---|---|---|
| `/ajukan-izin` | GET/POST | Publik | Form pengajuan izin |
| `/pengajuan/sukses/{request_number}` | GET | Publik (session-gated) | Halaman sukses submit |
| `/cek-status` | GET/POST | Publik | Cek status mandiri |
| `/login`, `/logout` | GET/POST | Publik / Auth | Login-logout staf |
| `/dashboard` | GET | Auth | Redirector sesuai role |
| `/admin/dashboard` | GET | role:admin | Dashboard Manager |
| `/hrd/dashboard` | GET | role:hrd | Dashboard HRD |
| `/pengajuan` | GET | role:admin,hrd | List semua pengajuan |
| `/pengajuan/{id}` | GET | role:admin,hrd | Detail pengajuan |
| `/pengajuan/{id}/approve` | POST | role:admin,hrd | Approve (dicek ulang wewenang via `canBeProcessedBy`) |
| `/pengajuan/{id}/reject` | POST | role:admin,hrd | Reject |
| `/pengajuan/{id}/attachment/{attachment_id}` | GET | role:admin,hrd | Download lampiran |
| `/riwayat` | GET | role:admin,hrd | Riwayat pengajuan |
| `/laporan`, `/laporan/export` | GET | role:admin,hrd | Laporan & export CSV |
| `/profil` | GET | role:admin,hrd | Profil staf login |
