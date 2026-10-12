# Manual Book LMS

Panduan pemakaian untuk **Admin Sekolah**, **Guru**, dan **Siswa**.
Ditulis untuk orang yang belum pernah membuka website ini sama sekali — ikuti dari atas ke bawah.

---

## Daftar Isi

**Bagian A — Dasar**
1. [Apa itu LMS ini & siapa memakai apa](#1-apa-itu-lms-ini--siapa-memakai-apa)
2. [Dua pintu masuk (link login)](#2-dua-pintu-masuk-link-login)
3. [Cara kerja datanya (wajib dibaca sebelum input apa pun)](#3-cara-kerja-datanya-wajib-dibaca-sebelum-input-apa-pun)
4. [Urutan setup yang benar](#4-urutan-setup-yang-benar)

**Bagian B — Panduan Admin & Guru**
5. [Login admin / guru](#5-login-admin--guru)
6. [Tambah sekolah](#6-tambah-sekolah)
7. [Tambah mata pelajaran](#7-tambah-mata-pelajaran)
8. [Tambah guru](#8-tambah-guru)
9. [Tambah kelas](#9-tambah-kelas)
10. [Tambah siswa](#10-tambah-siswa)
11. [Tambah siswa ke kelas](#11-tambah-siswa-ke-kelas)
12. [Tambah mapel ke kelas (membuat "Mata Pelajaran" yang diajar)](#12-tambah-mapel-ke-kelas-membuat-mata-pelajaran-yang-diajar)
13. [Tambah materi ke mapel](#13-tambah-materi-ke-mapel)
14. [Tambah tugas ke materi](#14-tambah-tugas-ke-materi)
15. [Tambah ujian ke materi](#15-tambah-ujian-ke-materi)
16. [Menilai tugas siswa](#16-menilai-tugas-siswa)
17. [Rekap nilai & pantau progress belajar](#17-rekap-nilai--pantau-progress-belajar)

**Bagian C — Panduan Siswa**
18. [Manual book untuk siswa](#18-manual-book-untuk-siswa)

**Bagian D — Bantuan**
19. [Troubleshooting / Pertanyaan yang sering muncul](#19-troubleshooting--pertanyaan-yang-sering-muncul)
20. [Lampiran: password default, batas file, istilah](#20-lampiran-password-default-batas-file-istilah)

---

# Bagian A — Dasar

## 1. Apa itu LMS ini & siapa memakai apa

LMS ini adalah website tempat guru membagikan **materi**, memberi **tugas** dan **ujian**, lalu menilainya; dan tempat siswa membaca materi serta mengumpulkan tugas.

Ada **3 jenis pengguna**:

| Pengguna | Bisa apa | Masuk dari |
|---|---|---|
| **Admin Sekolah** (super admin) | Semua: kelola sekolah, kelas, mapel, guru, siswa, enroll siswa ke kelas, dan semua menu guru | Panel Guru/Admin |
| **Guru** | Hanya mapel yang dia ajar: buat materi, tugas, ujian, menilai, rekap nilai, pantau progress | Panel Guru/Admin |
| **Siswa** | Lihat materi kelasnya, kumpul tugas, ikut ujian, lihat nilai | Portal Siswa |

> **Penting:** Guru **tidak bisa** menambah siswa, menambah kelas, menambah mata pelajaran baru, atau memasukkan siswa ke kelas. Menu-menu itu hanya muncul untuk Admin Sekolah. Kalau guru butuh itu, minta ke admin.

---

## 2. Dua pintu masuk (link login)

Website ini punya **dua halaman login yang berbeda**. Jangan tertukar — guru tidak bisa login di halaman siswa, dan sebaliknya.

Ganti `https://domain-sekolah.sch.id` dengan alamat website LMS sekolah Anda.

### Pintu 1 — Admin & Guru

```
https://domain-sekolah.sch.id/teacher
```

Masuk pakai **EMAIL + password**.

### Pintu 2 — Siswa

```
https://domain-sekolah.sch.id/student/login
```

Masuk pakai **NISN + password**. **Siswa TIDAK pakai email.**

> Kalau siswa hanya membuka `https://domain-sekolah.sch.id` (tanpa tambahan apa pun), sistem otomatis mengarahkan ke halaman login siswa. Jadi untuk siswa, cukup bagikan alamat utama website-nya saja.

### ❓ "Siswa masuk pakai link yang mana dan email apa?"

Ini pertanyaan paling sering muncul, jadi dijawab tegas:

- **Link siswa:** `/student/login` (atau cukup buka alamat utama website, otomatis diarahkan ke sana).
- **Email siswa:** **tidak ada.** Akun siswa di sistem ini dibuat **tanpa email**. Siswa login dengan:
  - **NISN** → sebagai username (contoh: `0012345001`)
  - **Password** → default-nya **tanggal lahir siswa** dengan format `TAHUN-BULAN-TANGGAL`, contoh siswa lahir 17 Agustus 2010 → passwordnya `2010-08-17`
- Yang pakai **email** untuk login hanyalah **guru dan admin**, dan link-nya `/teacher`.
- Kalau siswa diberi link `/teacher`, dia tidak akan bisa login — dan kalau pun sudah login sebagai siswa lalu membuka `/teacher`, sistem otomatis memantulkannya kembali ke dashboard siswa.

**Satu hal lagi yang wajib diberitahukan ke siswa:** pada login pertama, siswa **dipaksa mengganti password** dulu. Selama password default belum diganti, semua halaman (materi, tugas, ujian) belum bisa dibuka dan siswa akan terus dibawa ke halaman Profil. Itu bukan error — ikuti saja langkah di [bagian siswa](#182-login-pertama-wajib-ganti-password).

---

## 3. Cara kerja datanya (wajib dibaca sebelum input apa pun)

Ini bagian yang paling sering bikin bingung. Bacalah sekali, dan 90% masalah "kok tidak muncul" tidak akan terjadi.

Susunannya bertingkat seperti ini:

```
Sekolah
  └── Kelas  (contoh: "X IPA 1")
        ├── Daftar Siswa          ← siswa harus DIMASUKKAN ke kelas
        └── Mata Pelajaran kelas  ← gabungan Kelas + Mapel + Guru + Semester + Tahun Ajaran
              └── Materi          (contoh: "Bab 1 — Pengenalan")
                    ├── Tugas
                    └── Ujian
```

Tiga hal yang perlu dipahami:

1. **"Mata Pelajaran" di menu Pengajaran bukan daftar nama mapel.** Itu adalah **penugasan mengajar**: *Matematika di kelas X IPA 1, diajar Bu Ani, Semester 1, Tahun Ajaran 2025/2026*. Satu mapel yang sama bisa muncul berkali-kali untuk kelas yang berbeda. Di dalam panduan ini istilah itu disebut **"mapel kelas"**.

2. **Materi tidak ditempel ke "mapel" biasa, tapi ke "mapel kelas".** Jadi materi Matematika kelas X IPA 1 tidak otomatis terlihat oleh kelas X IPA 2 — harus dibuat lagi di mapel kelas X IPA 2.

3. **Siswa hanya melihat materi dari kelas yang dia terdaftar di dalamnya.** Kalau siswa belum dimasukkan ke kelas, dashboard-nya akan kosong melompong walaupun gurunya sudah mengisi banyak materi.

---

## 4. Urutan setup yang benar

Untuk sekolah/kelas baru, lakukan **berurutan**. Kalau diloncat, menu berikutnya akan kosong pilihannya.

| # | Langkah | Siapa | Menu | Panduan |
|---|---|---|---|---|
| 1 | Buat Sekolah | Admin | Master Data → Sekolah | [§6](#6-tambah-sekolah) |
| 2 | Buat Mata Pelajaran | Admin | Master Data → Mata Pelajaran | [§7](#7-tambah-mata-pelajaran) |
| 3 | Buat akun Guru | Admin | Manajemen Pengguna → Guru | [§8](#8-tambah-guru) |
| 4 | Buat Kelas (+pilih wali kelas) | Admin | Master Data → Kelas | [§9](#9-tambah-kelas) |
| 5 | Buat akun Siswa | Admin | Manajemen Pengguna → Siswa | [§10](#10-tambah-siswa) |
| 6 | **Masukkan siswa ke kelas** | Admin | Kelas → buka kelas → Daftar Siswa | [§11](#11-tambah-siswa-ke-kelas) |
| 7 | **Pasang mapel ke kelas + tentukan gurunya** | Admin | Kelas → buka kelas → Mata Pelajaran | [§12](#12-tambah-mapel-ke-kelas-membuat-mata-pelajaran-yang-diajar) |
| 8 | Isi Materi | Guru | Pengajaran → Mata Pelajaran → Buka | [§13](#13-tambah-materi-ke-mapel) |
| 9 | Tambah Tugas / Ujian | Guru | Materi → Kelola Aktivitas | [§14](#14-tambah-tugas-ke-materi) |
| 10 | Bagikan link + NISN + password ke siswa | Admin / Guru | — | [§2](#2-dua-pintu-masuk-link-login) |

Langkah **6 dan 7 adalah yang paling sering terlupa**, dan keduanya adalah penyebab nomor satu "materi sudah diinput tapi siswa tidak melihat apa-apa".

---

# Bagian B — Panduan Admin & Guru

## 5. Login admin / guru

1. Buka `https://domain-sekolah.sch.id/teacher`
2. Isi **Email** dan **Password**, klik **Sign in**
3. Anda masuk ke Dashboard

**Isi menu di sisi kiri** (yang muncul tergantung peran Anda):

- **Dashboard** — ringkasan: statistik kelas, tugas yang menunggu dinilai, ujian terdekat, siswa yang perlu perhatian
- **Pengajaran**
  - **Mata Pelajaran** — daftar mapel kelas yang Anda ajar; pintu masuk ke Materi
  - **Pantau Progress Belajar** — aktivitas belajar siswa
  - **Rekap Nilai** — tabel nilai per kelas-mapel, bisa di-export Excel
- **Manajemen Pengguna** *(hanya admin)* — **Guru**, **Siswa**
- **Master Data** *(hanya admin)* — **Sekolah**, **Mata Pelajaran**, **Kelas**
- **Audit** *(hanya admin)* — **Activity Logs**
- Ikon **lonceng** di kanan atas — notifikasi (mis. ada siswa mengumpulkan tugas)
- Nama Anda di kanan atas → **Profile** untuk ganti nama/password, **Sign out** untuk keluar

**Password guru baru** (dibuat admin) adalah **tanggal lahir dengan format `DDMMYYYY`** — contoh lahir 5 Maret 1990 → `05031990`. Kalau tanggal lahir tidak diisi saat pembuatan akun, passwordnya `teacher123`. **Segera ganti** lewat menu Profile.

---

## 6. Tambah sekolah

Hanya perlu sekali (kecuali sistem dipakai beberapa sekolah).

1. **Master Data → Sekolah**
2. Klik **New Sekolah**
3. Isi data sekolah (nama, dan kolom lain yang tersedia)
4. Pastikan status **aktif** menyala — sekolah yang tidak aktif tidak akan muncul sebagai pilihan saat membuat kelas/siswa
5. **Create**

---

## 7. Tambah mata pelajaran

Di sini Anda hanya mendaftarkan **nama mapel**-nya saja (Matematika, Bahasa Indonesia, …). Menentukan siapa yang mengajar dan di kelas mana dilakukan nanti di [§12](#12-tambah-mapel-ke-kelas-membuat-mata-pelajaran-yang-diajar).

1. **Master Data → Mata Pelajaran**
2. Klik **New Mata Pelajaran**
3. Isi:
   - **Nama Mata Pelajaran** — contoh: `Matematika`
   - **Kode** — terisi otomatis dari nama saat Anda klik di luar kolom nama. Bisa diubah manual, atau klik ikon ⟳ di ujung kolom untuk membuat ulang. Kode **tidak boleh sama** dengan mapel lain.
   - **Deskripsi** — opsional
4. **Create**

Ulangi untuk semua mapel yang diajarkan di sekolah.

---

## 8. Tambah guru

Membuat akun guru sekaligus akun login-nya.

1. **Manajemen Pengguna → Guru**
2. Klik **New Guru**
3. Isi:
   - **Nama Lengkap** *(wajib)*
   - **Email** *(wajib)* — **ini yang dipakai guru untuk login.** Harus unik, belum pernah dipakai.
   - **NIP** / **NIK** — opsional, tapi kalau diisi harus unik
   - **Spesialisasi / Mapel** — opsional, sekadar catatan
   - **No. Telepon** — opsional
   - **Jenis Kelamin** *(wajib)*
   - **Tempat Lahir** — opsional
   - **Tanggal Lahir** — **sangat disarankan diisi**, karena password default dibuat dari tanggal lahir ini
4. **Create**

**Password guru yang baru dibuat:** tanggal lahir format `DDMMYYYY` (lahir 5 Maret 1990 → `05031990`). Tanpa tanggal lahir → `teacher123`.

**Memberi tahu guru:**
> Link: `https://domain-sekolah.sch.id/teacher`
> Email: `guru@sekolah.sch.id`
> Password: `05031990` → tolong segera ganti di menu Profile.

**Lupa password guru?** Di daftar Guru, klik menu titik-tiga pada baris guru tersebut → **Reset Password** → konfirmasi. Password kembali ke `DDMMYYYY` tanggal lahirnya, dan sistem menampilkan passwordnya di notifikasi.

---

## 9. Tambah kelas

1. **Master Data → Kelas**
2. Klik **New Kelas**
3. Isi:
   - **Nama Kelas** *(wajib)* — contoh: `X IPA 1`
   - **Tingkat** *(wajib)* — contoh: `X`
   - **Tahun Ajaran** *(wajib)* — contoh: `2025/2026`
   - **Aktif** — biarkan menyala
   - **Sekolah** *(wajib)* — pilih sekolah
   - **Wali Kelas** *(wajib)* — pilih guru. Guru harus sudah dibuat lebih dulu ([§8](#8-tambah-guru)).
4. **Create**

Setelah kelas jadi, **belum ada siswa dan belum ada mapel** di dalamnya. Dua langkah berikut mengisinya.

---

## 10. Tambah siswa

1. **Manajemen Pengguna → Siswa**
2. Klik **New Siswa**
3. Bagian **Profil Siswa**:
   - **Nama Lengkap** *(wajib)*
   - **NISN** — **ini username login siswa.** Harus unik. **Sangat disarankan diisi**; tanpa NISN siswa tidak bisa login.
   - **Sekolah** *(wajib)*
   - **Kelas** — kolom teks bebas (contoh: `X IPA 1`). ⚠️ **Ini hanya catatan/label, bukan pendaftaran ke kelas.** Mengisi kolom ini **tidak** membuat siswa melihat materi. Pendaftaran sesungguhnya ada di [§11](#11-tambah-siswa-ke-kelas).
   - **Jenis Kelamin** *(wajib)*
   - **Siswa Aktif** — biarkan menyala. Kalau dimatikan, siswa langsung tidak bisa login (dan yang sedang login akan dikeluarkan).
   - **Opt-out Learning Tracking** — biarkan mati, kecuali siswa/orang tua menolak aktivitas belajarnya dicatat
   - **Tempat Lahir** — opsional
   - **Tanggal Lahir** *(wajib)* — dipakai sebagai password default
4. Bagian **Akun Login**:
   - **Password** — **boleh dikosongkan.** Kalau dikosongkan, password otomatis = tanggal lahir format `YYYY-MM-DD`. Isi manual hanya kalau Anda ingin password lain (minimal 6 karakter).
5. **Create**

**Memberi tahu siswa:**
> Link: `https://domain-sekolah.sch.id/student/login`
> NISN: `0012345001`
> Password: `2010-08-17` (tanggal lahir kamu, format tahun-bulan-tanggal)
> Nanti diminta ganti password dulu sebelum bisa membuka materi.

**Lupa password siswa?** Dua cara, keduanya dari **Manajemen Pengguna → Siswa**:
- Menu titik-tiga pada baris siswa → **Reset Password ke Tanggal Lahir** → password kembali ke `YYYY-MM-DD` tanggal lahirnya.
- Buka siswa → **Edit** → tombol **Reset Password** di kanan atas → sistem membuat password acak 8 karakter dan **menampilkannya sekali saja**. Salin segera, tidak bisa dilihat ulang.

> **Jangan membuat akun siswa dua kali.** Kalau NISN sudah ada, sistem akan menolak karena NISN harus unik. Satu siswa = satu akun, lalu masukkan ke kelas.

---

## 11. Tambah siswa ke kelas

**Langkah ini wajib. Tanpa ini, siswa tidak akan melihat mapel maupun materi apa pun.**

1. **Master Data → Kelas**
2. Klik **View** / **Buka** pada kelas yang dituju (mis. `X IPA 1`)
3. Pilih tab **Daftar Siswa**
4. Klik **Tambah Siswa**
5. Pada **Siswa**, cari dan pilih siswanya — bisa dicari dengan **nama** atau **NISN**
6. **Tanggal Masuk** sudah terisi hari ini, boleh diubah
7. Klik **Attach** / simpan
8. Ulangi untuk semua siswa di kelas itu

Siswa yang sudah ada di kelas tersebut tidak akan muncul lagi di daftar pilihan, jadi tidak mungkin dobel.

**Mengeluarkan siswa dari kelas:** di tab Daftar Siswa, menu titik-tiga pada baris siswa → **Keluarkan dari Kelas**. Bisa juga dicentang beberapa sekaligus lalu pilih aksi massal. Ini **tidak** menghapus akun siswanya, hanya mencabut keanggotaan kelas — dan materi kelas itu langsung hilang dari dashboard siswa tersebut.

---

## 12. Tambah mapel ke kelas (membuat "Mata Pelajaran" yang diajar)

**Langkah ini juga wajib.** Di sini Anda menyatakan: *mapel ini, diajar guru ini, di kelas ini, semester ini*. Inilah yang nanti muncul sebagai kartu mapel di dashboard siswa, dan inilah tempat guru menaruh materi.

Ada dua jalan. Hasilnya sama.

### Cara A — dari Kelas (disarankan untuk admin, sekalian isi semua mapel satu kelas)

1. **Master Data → Kelas** → **View** pada kelasnya
2. Pilih tab **Mata Pelajaran**
3. Klik **Tambah Mata Pelajaran**
4. Isi:
   - **Mata Pelajaran** *(wajib)* — dari daftar yang dibuat di [§7](#7-tambah-mata-pelajaran)
   - **Guru Pengajar** *(wajib)* — guru yang akan mengisi materi & menilai
   - **Tahun Ajaran** *(wajib)* — contoh `2025/2026`
   - **Semester** *(wajib)* — Semester 1 / Semester 2
5. **Create**
6. Ulangi untuk semua mapel di kelas itu

### Cara B — dari menu Pengajaran

1. **Pengajaran → Mata Pelajaran**
2. Klik **New Mengajar**
3. Isi **Kelas**, **Mata Pelajaran**, **Guru Pengampu** (otomatis terisi dengan diri Anda kalau Anda guru), **Semester**, **Tahun Ajaran**
4. **Create**

> **Catatan penting soal "Guru Pengajar":** guru hanya bisa melihat dan mengisi mapel kelas yang **namanya tercantum sebagai guru pengampu**. Kalau guru bilang "menu Mata Pelajaran saya kosong", hampir pasti belum ada mapel kelas yang ditugaskan ke dia di langkah ini. Admin tinggal mengisikannya.

---

## 13. Tambah materi ke mapel

Dikerjakan oleh **guru pengampu** (admin juga bisa).

1. **Pengajaran → Mata Pelajaran**
2. Klik **Buka** pada baris mapel kelas yang dituju (mis. *X IPA 1 — Matematika*)
3. Di bagian bawah ada tabel **Materi** → klik **Tambah Materi**
4. Isi **Informasi Materi**:
   - **Judul** *(wajib)* — contoh: `Persamaan Kuadrat`
   - **Topik / Pertemuan** — contoh: `Bab 1 — Pengenalan`
5. Isi **Konten Materi** — boleh salah satu, boleh gabungan ketiganya:
   - **Teks Materi** — editor teks; bisa tebal/miring, daftar, tabel sederhana
   - **Upload File** — bisa beberapa file sekaligus, **maksimal 20 MB per file** (PDF, Word, PPT, gambar, video, dsb.)
   - **Link Eksternal** — satu tautan, harus lengkap diawali `https://` (mis. video YouTube)
6. Isi **Visibility & Jadwal** — **ini bagian yang menentukan siswa bisa lihat atau tidak**:
   - **Publish ke Siswa** — ⚠️ **WAJIB DINYALAKAN.** Kalau mati, materi tidak terlihat siswa sama sekali, walau isinya sudah lengkap dan jadwalnya sudah lewat.
   - **Mulai Tampil** — kosongkan kalau mau langsung tampil begitu dipublish. Kalau diisi tanggal/jam, materi baru muncul di sisi siswa **setelah** waktu itu.
   - **Berhenti Tampil** — kosongkan kalau mau tampil selamanya. Kalau diisi dan sudah lewat, materi **hilang** dari sisi siswa.
7. **Create**

**Mengubah urutan materi:** di tabel Materi, gunakan ikon geser (drag) untuk menyusun ulang. Urutan ini yang dilihat siswa.

**Mengubah / menghapus materi:** menu titik-tiga pada baris materi → **Edit** / **Delete**.

> **Materi tidak mengirim notifikasi.** Saat Anda mempublish materi, siswa **tidak** mendapat notifikasi lonceng. Yang mengirim notifikasi hanya **Tugas** dan **Ujian** yang baru dipublish. Jadi kalau ingin siswa tahu ada materi baru, beritahu di kelas/grup, atau buat tugas terkait materi itu.

---

## 14. Tambah tugas ke materi

### ❓ "Untuk tambah tugas mapel ini di mana?"

Tugas **tidak ditempel langsung ke mapel**, tapi **ke salah satu materi di dalam mapel itu**. Jadi buat materinya dulu (walaupun isinya hanya judul), baru tugasnya. Tidak ada menu "Tugas" di sidebar — itu normal, bukan fitur yang hilang.

**Jalurnya:**

```
Pengajaran → Mata Pelajaran → [Buka] mapel kelasnya
  → tabel Materi → tombol [Kelola Aktivitas] pada materi
    → tab Tugas → [Tambah Tugas]
```

**Langkah detail:**

1. **Pengajaran → Mata Pelajaran**
2. Klik **Buka** pada mapel kelas yang dituju
3. Di tabel **Materi**, cari materi yang mau diberi tugas, klik tombol **Kelola Aktivitas**
4. Halaman materi terbuka. Di bagian **Aktivitas** ada dua tab: **Tugas** dan **Ujian**. Pilih **Tugas**.
5. Klik **Tambah Tugas**
6. Isi **Informasi Tugas**:
   - **Judul Tugas** *(wajib)*
   - **Deskripsi / Soal** — editor teks, tulis perintah/soalnya di sini
   - **Batas Waktu** *(wajib)* — tanggal **dan jam** deadline
   - **Nilai Maksimal** — default `100`
7. **Lampiran Soal** — **File Lampiran (dari Guru)**: upload file soal kalau ada, maksimal 20 MB per file, boleh beberapa
8. **Aturan Pengumpulan Siswa**:
   - **Tipe File yang Diizinkan** — centang format yang boleh dikumpulkan siswa (PDF, Word, Excel, PPT, gambar, ZIP, MP3, MP4, dst.). Sudah ada centangan default; sesuaikan bila perlu.
   - **Maksimal Ukuran File per File (MB)** *(wajib)* — default `10`, maksimal `100`
   - Siswa **selalu** bisa menjawab berupa teks (esai) dan/atau melampirkan file; pengaturan di atas hanya membatasi file-nya.
9. **Visibility & Jadwal**:
   - **Publish ke Siswa** — ⚠️ **WAJIB DINYALAKAN**, kalau tidak siswa tidak melihat tugasnya. Saat pertama kali dinyalakan, **semua siswa di kelas itu otomatis mendapat notifikasi**.
   - **Mulai Tampil** — kosongkan = langsung tampil saat dipublish
   - **Berhenti Tampil** — kosongkan = tampil terus sampai deadline
10. **Create**

**Catatan penting soal deadline:**
- Sistem memakai **deadline tegas**: setelah **Batas Waktu** terlewat, siswa **tidak bisa lagi** mengumpulkan atau mengubah pengumpulannya. Tidak ada pengumpulan terlambat.
- Siswa juga tidak bisa mengubah pengumpulan setelah **Anda memberi nilai**, walaupun deadline belum lewat.
- Sistem mengirim **pengingat deadline otomatis** ke siswa sekitar 24 jam sebelum batas waktu.

**Mengubah urutan tugas:** gunakan ikon geser di tabel Tugas.
**Mengubah/menghapus tugas:** menu titik-tiga pada baris tugas → **Edit** / **Delete**.
**Melihat & menilai pengumpulan:** tombol **Kelola** pada baris tugas → lihat [§16](#16-menilai-tugas-siswa).

---

## 15. Tambah ujian ke materi

Jalurnya sama dengan tugas, hanya tab-nya berbeda.

```
Pengajaran → Mata Pelajaran → [Buka] → tabel Materi → [Kelola Aktivitas] → tab Ujian → [Tambah Ujian]
```

Ada dua **mode ujian**:

- **Online Quiz** — siswa menjawab langsung di website. Soal diisi satu per satu (pilihan ganda, jawaban singkat, esai). Pilihan ganda dan jawaban singkat **dinilai otomatis**; esai menunggu dinilai guru. Ada **durasi** — saat waktu habis, jawaban siswa **otomatis dikumpulkan** sistem.
- **Submission** (unggah jawaban) — guru mengunggah file soal, siswa mengerjakan di luar lalu mengunggah jawabannya.

Yang perlu diperhatikan:

- **Publish ke Siswa** tetap wajib dinyalakan; saat dipublish, siswa dapat notifikasi, dan ada pengingat otomatis sekitar 1 jam sebelum ujian mulai.
- Untuk Online Quiz, jangan lupa **mengisi soal** di tab/relasi **Pertanyaan** — ujian tanpa soal tidak ada gunanya walau sudah dipublish.
- Kalau nilai belum mau diperlihatkan ke siswa, gunakan pengaturan **waktu rilis hasil**. Selama belum waktunya, siswa melihat statusnya "sudah dikumpulkan" tanpa angka nilai.

---

## 16. Menilai tugas siswa

1. Dari **Dashboard**, lihat panel tugas yang menunggu dinilai — atau masuk lewat materi: **Kelola Aktivitas → tab Tugas → Kelola** pada tugas tersebut
2. Di halaman tugas, lihat tabel pengumpulan siswa: **Nama Siswa**, **NISN**, **Waktu Kumpul**, **Ketepatan**, **Status**, **Nilai**
3. Gunakan filter **Status Pengumpulan** / **Sudah Dinilai** untuk menyaring
4. Klik **Beri Nilai** pada baris siswa
5. Anda akan melihat **Esai / Jawaban Teks**, **Tautan Referensi**, dan **Lampiran Siswa** (bisa diunduh)
6. Isi **Nilai** dan **Feedback / Catatan untuk Siswa**
7. Simpan — siswa otomatis mendapat **notifikasi bahwa tugasnya sudah dinilai**, dan nilai + feedback langsung terlihat di halaman tugasnya

Begitu nilai diisi, pengumpulan siswa **terkunci** — siswa tidak bisa mengubahnya lagi.

---

## 17. Rekap nilai & pantau progress belajar

### Rekap Nilai

**Pengajaran → Rekap Nilai**

1. Pilih mapel kelas di kolom pilihan atas
2. Tabel menampilkan semua siswa di baris, semua tugas & ujian di kolom, beserta nilainya
3. Gunakan tombol export untuk mengunduh ke **Excel**

### Pantau Progress Belajar

**Pengajaran → Pantau Progress Belajar**

Menampilkan aktivitas belajar siswa (materi yang sudah dibuka/diunduh, perkiraan durasi belajar) per mapel kelas, bisa dibuka sampai ke detail per siswa dan di-export.

> Siswa yang **Opt-out Learning Tracking**-nya dinyalakan tidak dicatat aktivitasnya, jadi akan tampak kosong di sini. Itu memang disengaja.

### Activity Logs *(admin)*

**Audit → Activity Logs** — catatan kejadian penting: pengumpulan tugas, sesi ujian, reset password, dsb.

---

# Bagian C — Panduan Siswa

## 18. Manual book untuk siswa

Bagian ini bisa dicetak atau dibagikan apa adanya ke siswa.

### 18.1 Masuk ke LMS

1. Buka browser di HP atau laptop (Chrome, Safari, Edge — bebas)
2. Buka alamat:
   ```
   https://domain-sekolah.sch.id/student/login
   ```
   (atau cukup buka `https://domain-sekolah.sch.id`, nanti otomatis diarahkan ke halaman ini)
3. Akan muncul halaman **"Masuk Portal Siswa"**
4. Isi:
   - **NISN** — nomor NISN kamu, contoh `0012345001`
   - **Password** — kalau belum pernah mengganti, passwordnya adalah **tanggal lahir** kamu dengan format `TAHUN-BULAN-TANGGAL`.
     Contoh: lahir **17 Agustus 2010** → tulis `2010-08-17` (pakai tanda minus, bulan dan tanggal dua angka)
   - Ikon mata di kanan kolom password bisa diklik untuk melihat apa yang kamu ketik
5. Klik **Masuk**

**Yang perlu kamu tahu:**
- **Kamu tidak memakai email.** Username kamu adalah NISN.
- Jangan pakai link yang ada kata `teacher` — itu untuk guru, kamu tidak akan bisa masuk di sana.
- Kalau salah password **5 kali**, akun dikunci sementara (sekitar 1 menit). Tunggu, lalu coba lagi dengan hati-hati.
- Pesan **"NISN atau password salah"** bisa berarti: NISN salah, password salah, **atau** akunmu dinonaktifkan. Kalau sudah yakin benar, hubungi guru/admin.

### 18.2 Login pertama: wajib ganti password

Saat pertama kali masuk, kamu akan langsung dibawa ke halaman **Profil Saya** dengan pesan agar mengganti password. **Ini normal, bukan error.** Selama password belum diganti, menu materi dan tugas belum bisa dibuka.

1. Di bagian **Ubah Password**, isi:
   - **Password saat ini** → tanggal lahir kamu (`2010-08-17`)
   - **Password baru** → minimal 8 karakter, jangan pakai tanggal lahir lagi
   - **Konfirmasi password baru** → ulangi password baru yang sama
2. Simpan
3. Setelah berhasil, semua menu terbuka

**Ingat password barumu.** Kalau lupa, kamu tidak bisa meresetnya sendiri — harus minta guru/admin yang mereset.

### 18.3 Halaman utama (Dashboard)

Setelah masuk kamu melihat:

- **Kartu mata pelajaran** kamu — tiap kartu berisi nama mapel, nama guru, semester. Klik kartu untuk membuka.
- Kartu bisa **di-pin** supaya selalu di urutan paling atas
- **Lonceng 🔔** di atas — notifikasi: ada tugas/ujian baru, tugasmu sudah dinilai, pengingat deadline
- **To-Do** — daftar tugas & ujianmu dengan status: *Tersedia sekarang*, *Belum dikumpul*, *Akan datang*, *Terlambat*
- Statistik singkat progres belajarmu

> **Dashboard kosong, tidak ada kartu mapel sama sekali?** Berarti kamu belum dimasukkan ke kelas oleh admin, atau kelasmu belum dipasangkan mapel. Lapor ke guru/admin — ini tidak bisa kamu perbaiki sendiri.

### 18.4 Membuka materi

1. Klik kartu **mata pelajaran** di Dashboard
2. Muncul daftar **materi** yang sudah dibagikan guru, berurutan. Tiap materi menunjukkan ada berapa tugas/ujian di dalamnya.
3. Klik materinya
4. Di halaman materi kamu bisa:
   - Membaca **teks materi**
   - **Mengunduh file** materi (PDF, Word, PPT, dll.) — klik nama file
   - Klik **Buka tautan** kalau guru melampirkan link (video, artikel)
   - Melihat **daftar Tugas** dan **Ujian** dari materi itu di bagian bawah

Kalau tertulis **"Materi belum berisi konten"**, berarti guru baru membuat judulnya dan belum mengisi. Tunggu atau tanyakan ke guru.

> Daftar materi yang muncul hanya yang **sudah dipublish guru** dan **sudah waktunya tampil**. Materi yang guru masih siapkan tidak akan kelihatan — itu normal.

### 18.5 Mengumpulkan tugas

1. Buka **mapel → materi → Tugas** yang dituju
2. Baca **deskripsi/soal** dan unduh **lampiran** dari guru kalau ada
3. Perhatikan **Batas Waktu (deadline)**
4. Isi jawabanmu — tergantung permintaan guru, kamu bisa:
   - Menulis **jawaban teks / esai** di kolom yang tersedia
   - **Melampirkan file** — perhatikan **format yang diizinkan** dan **ukuran maksimal** yang tertulis di halaman itu
   - Menambahkan **tautan** (mis. link Google Drive atau video)
5. Klik **Kumpulkan**
6. Status berubah menjadi **sudah dikumpulkan**, lengkap dengan waktu pengumpulan

**Aturan yang wajib kamu tahu:**
- **Lewat deadline = tidak bisa mengumpulkan lagi.** Sistem menolaknya, tanpa pengecualian. Jangan menunda sampai menit terakhir.
- Selama **belum lewat deadline** dan **belum dinilai guru**, kamu masih bisa **mengedit** pengumpulanmu (ganti teks, tambah/hapus file).
- Setelah **guru memberi nilai**, pengumpulan **terkunci** — tidak bisa diubah lagi.
- Kalau filemu **ditolak**, cek dua hal: formatnya tidak diizinkan, atau ukurannya melebihi batas. Keterangan batasnya tertulis di halaman tugas.

### 18.6 Melihat nilai & feedback

- Di halaman tugas, kalau sudah dinilai akan muncul **nilai** dan **feedback/catatan dari guru**
- Kamu juga mendapat **notifikasi lonceng** saat tugasmu dinilai
- Di halaman **Profil Saya** ada **Progres per Mata Pelajaran**

### 18.7 Mengikuti ujian

Ada dua jenis ujian:

**A. Ujian online (menjawab di website)**
1. Buka **mapel → materi → Ujian**
2. Baca keterangan: jumlah soal, **durasi**, waktu mulai
3. Klik **Mulai** — ⚠️ **begitu diklik, waktu langsung berjalan dan tidak bisa dihentikan**
4. Jawab soal satu per satu. **Jawabanmu tersimpan otomatis** beberapa detik sekali, jadi kalau internet sempat terputus atau browser tertutup, jawaban yang sudah terisi tidak hilang — masuk lagi dan lanjutkan.
5. Klik **Kumpulkan** kalau sudah selesai. Kalau **waktu habis**, sistem **mengumpulkan otomatis**.
6. Soal pilihan ganda dan jawaban singkat dinilai otomatis; soal esai menunggu guru.

**Tips:** pakai koneksi yang stabil, jangan menutup tab, jangan menekan tombol *back* browser saat ujian berjalan.

**B. Ujian unggah jawaban (submission)**
1. Unduh **file soal** dari guru
2. Kerjakan di luar (tulis di buku lalu difoto, atau kerjakan di Word)
3. Kembali ke halaman ujian, **unggah file jawabanmu**, lalu kumpulkan

**Nilai belum muncul setelah ujian?** Guru bisa mengatur agar hasil baru ditampilkan pada waktu tertentu, dan soal esai memang perlu dinilai manual. Tunggu pengumuman guru.

### 18.8 Profil, foto, dan keluar

- Buka **Profil Saya** untuk:
  - **Mengubah password** — kapan saja
  - **Mengganti foto profil** — klik ikon kamera; format PNG/JPG/WEBP, maksimal **2 MB**
  - Melihat **progres belajar** per mapel
- **Keluar:** klik menu akun → **Logout**. Penting kalau memakai HP/komputer bersama.
- Kamu mungkin melihat pemberitahuan bahwa aktivitas belajarmu dicatat untuk keperluan pemantauan — klik **Mengerti** untuk menutupnya.

### 18.9 Kalau ada masalah

| Masalah | Yang harus dilakukan |
|---|---|
| "NISN atau password salah" | Cek NISN. Cek format password tanggal lahir `2010-08-17`. Kalau tetap gagal → minta admin reset password |
| Terus diminta ganti password | Memang wajib di login pertama. Isi password lama (tanggal lahir) + password baru 2x |
| "Terlalu banyak percobaan login" | Tunggu sekitar 1 menit, lalu coba lagi |
| "Akun Anda telah dinonaktifkan" | Hubungi admin sekolah |
| Dashboard kosong, tidak ada mapel | Kamu belum dimasukkan ke kelas → lapor guru/admin |
| Mapel ada, materi kosong | Guru belum mempublish materi → tanya guru |
| Tidak bisa mengumpulkan tugas | Kemungkinan sudah lewat deadline, atau sudah dinilai guru |
| File tidak bisa diunggah | Format tidak diizinkan atau ukuran melebihi batas — cek keterangan di halaman tugas |

---

# Bagian D — Bantuan

## 19. Troubleshooting / Pertanyaan yang sering muncul

### ❓ "Sudah input materi, tapi di siswa tidak muncul. Kenapa?"

Ini keluhan paling sering. Materi hanya muncul di sisi siswa kalau **SEMUA** syarat di bawah terpenuhi. Periksa berurutan — biasanya penyebabnya nomor 1 atau 2.

**Checklist — periksa satu per satu:**

**1. Siswa sudah dimasukkan ke kelas?** ← penyebab paling sering
- **Master Data → Kelas → View** kelasnya → tab **Daftar Siswa**
- Nama siswa harus ada di daftar itu.
- ⚠️ Mengisi kolom **"Kelas"** di form data siswa **tidak cukup** — kolom itu hanya label teks. Pendaftaran yang sah hanya lewat tab **Daftar Siswa** ([§11](#11-tambah-siswa-ke-kelas)).

**2. Kelas itu sudah dipasangkan mapel + guru?**
- **Master Data → Kelas → View** kelasnya → tab **Mata Pelajaran**
- Mapelnya harus terdaftar di sana. Kalau kosong, siswa tidak punya kartu mapel sama sekali ([§12](#12-tambah-mapel-ke-kelas-membuat-mata-pelajaran-yang-diajar)).

**3. Materi ditempel ke mapel kelas yang BENAR?**
- Materi menempel pada kombinasi **kelas + mapel + semester + tahun ajaran** tertentu.
- Sering terjadi: guru mengajar Matematika di X IPA 1 **dan** X IPA 2, lalu materinya hanya dibuat di salah satu. Kelas yang lain tetap kosong — materi tidak otomatis menyebar.
- Cek juga **semester** dan **tahun ajaran** mapel kelas itu, jangan sampai materi masuk ke mapel kelas semester/tahun lain.

**4. Toggle "Publish ke Siswa" sudah menyala?** ← penyebab kedua paling sering
- Buka materinya → bagian **Visibility & Jadwal** → **Publish ke Siswa** harus **ON**.
- Di tabel Materi, kolom **Publish** harus bertanda centang hijau, bukan tanda silang.
- Kalau OFF, materi **tidak terlihat sama sekali** oleh siswa, walaupun isinya sudah lengkap.

**5. "Mulai Tampil" belum lewat?**
- Kalau **Mulai Tampil** diisi tanggal/jam yang masih di masa depan, materi belum muncul — nanti muncul sendiri setelah waktu itu.
- Kalau mau langsung tampil: **kosongkan** kolom ini.
- Cek juga jamnya, bukan hanya tanggalnya.

**6. "Berhenti Tampil" sudah lewat?**
- Kalau **Berhenti Tampil** diisi dan waktunya sudah lewat, materi **hilang** dari sisi siswa.
- Kalau mau tampil terus: **kosongkan** kolom ini.

**7. Siswanya masih aktif?**
- **Manajemen Pengguna → Siswa** → **Siswa Aktif** harus menyala. Siswa nonaktif tidak bisa login sama sekali.

**8. Siswa sudah menyelesaikan ganti password?**
- Siswa yang belum mengganti password default akan terus dibawa ke halaman Profil dan **belum bisa membuka materi**. Dari sisi siswa ini terasa seperti "materinya tidak ada".

**Cara cepat memastikan:** minta satu siswa dari kelas itu login, lalu Dashboard-nya dicek bersama.
- Dashboard kosong tanpa kartu mapel → masalahnya nomor **1 atau 2**
- Kartu mapel ada tapi daftar materi kosong → masalahnya nomor **3, 4, 5, atau 6**

### ❓ Pertanyaan lain

**Guru login, tapi menu Mata Pelajaran kosong.**
Belum ada mapel kelas yang ditugaskan ke guru itu. Admin perlu mengisikan guru tersebut sebagai **Guru Pengajar** di [§12](#12-tambah-mapel-ke-kelas-membuat-mata-pelajaran-yang-diajar). Guru hanya melihat mapel kelas yang namanya tercantum.

**Guru tidak menemukan menu Siswa / Kelas / Mata Pelajaran (Master Data).**
Normal. Menu itu hanya untuk Admin Sekolah.

**Di sidebar tidak ada menu "Materi" atau "Tugas".**
Normal, memang tidak ada. Materi diakses lewat **Pengajaran → Mata Pelajaran → Buka**, dan tugas lewat **Kelola Aktivitas** pada materi.

**Siswa bilang tidak dapat notifikasi padahal materi baru sudah dipublish.**
Mempublish **materi** tidak mengirim notifikasi — hanya **Tugas** dan **Ujian** yang mengirim notifikasi saat pertama kali dipublish. Beritahu siswa lewat jalur lain, atau buat tugas terkait materi itu.

**Tugas sudah dipublish, tapi siswa tidak dapat notifikasi.**
Notifikasi hanya dikirim saat toggle publish **berubah dari OFF ke ON**. Kalau tugas dibuat langsung dengan publish ON, notifikasi tetap terkirim. Tapi kalau sudah pernah ON lalu di-OFF lalu ON lagi, notifikasi dikirim lagi. Kalau notifikasi sama sekali tidak sampai, ada kemungkinan antrian (queue) di server tidak jalan — hubungi pengelola teknis.

**Siswa tidak bisa mengumpulkan tugas padahal tugasnya terlihat.**
Cek **Batas Waktu**. Sistem memakai deadline tegas: lewat deadline = ditolak. Kalau perlu diperpanjang, guru bisa mengedit tugas dan memundurkan **Batas Waktu**-nya.

**Siswa ingin memperbaiki tugas yang sudah dikumpulkan.**
Boleh, selama belum lewat deadline **dan** belum dinilai. Kalau sudah dinilai, pengumpulan terkunci.

**Satu siswa pindah kelas.**
Di kelas lama: **Daftar Siswa → Keluarkan dari Kelas**. Di kelas baru: **Daftar Siswa → Tambah Siswa**. Akun dan NISN-nya tetap sama, jangan buat akun baru.

**NISN ditolak karena sudah ada.**
Siswa itu sudah punya akun. Cari di **Manajemen Pengguna → Siswa**, lalu edit yang sudah ada — jangan buat akun kedua.

**Admin/guru lupa password.**
Admin lain bisa mereset lewat **Manajemen Pengguna → Guru → Reset Password**. Kalau tidak ada admin lain yang bisa masuk, hubungi pengelola teknis server.

**File materi/tugas gagal diunggah.**
Batasnya **20 MB per file** untuk materi dan lampiran soal. Kalau PDF terlalu besar, kompres dulu atau pecah jadi beberapa file. Untuk video, lebih baik unggah ke YouTube/Drive lalu tempel sebagai **Link Eksternal**.

**Ujian online siswa terputus di tengah jalan.**
Jawaban tersimpan otomatis. Siswa masuk lagi dan melanjutkan — sisa waktu tetap berjalan sejak tombol Mulai diklik. Kalau waktunya habis, sistem mengumpulkan otomatis.

---

## 20. Lampiran: password default, batas file, istilah

### Password default

| Siapa | Username | Password default | Format | Contoh |
|---|---|---|---|---|
| **Siswa** | NISN | Tanggal lahir | `YYYY-MM-DD` | lahir 17 Agu 2010 → `2010-08-17` |
| **Guru** | Email | Tanggal lahir | `DDMMYYYY` | lahir 5 Mar 1990 → `05031990` |
| **Guru** (tanpa tanggal lahir) | Email | — | — | `teacher123` |

⚠️ **Perhatikan formatnya berbeda** antara siswa dan guru. Ini sering tertukar.

Siswa **wajib** mengganti password di login pertama. Guru sangat disarankan mengganti lewat menu **Profile**.

### Batas ukuran file

| Keperluan | Batas |
|---|---|
| File materi (guru) | 20 MB per file, boleh beberapa |
| Lampiran soal tugas (guru) | 20 MB per file, boleh beberapa |
| File pengumpulan tugas (siswa) | diatur guru per tugas, default 10 MB per file (maks. 100 MB) |
| Foto profil siswa | 2 MB (PNG/JPG/WEBP) |

### Daftar istilah

| Istilah di layar | Artinya |
|---|---|
| **Mata Pelajaran** (Master Data) | Daftar nama mapel: Matematika, Bahasa Indonesia, … |
| **Mata Pelajaran** (Pengajaran) / **Mengajar** | Penugasan mengajar: mapel + kelas + guru + semester + tahun ajaran. Inilah "mapel kelas" tempat materi ditaruh |
| **Kelas** (Master Data) | Kelas resmi, mis. X IPA 1 — punya wali kelas, daftar siswa, dan daftar mapel |
| **Kelas** (di form data siswa) | Hanya label teks. Tidak mendaftarkan siswa ke kelas mana pun |
| **Daftar Siswa** (di dalam Kelas) | Pendaftaran siswa yang sah ke kelas. Ini yang menentukan siswa melihat materi atau tidak |
| **Materi** | Satu bahan ajar: teks, file, dan/atau link |
| **Kelola Aktivitas** | Tombol pada materi untuk masuk ke Tugas & Ujian materi itu |
| **Publish ke Siswa** | Saklar buka/tutup. OFF = siswa tidak melihat sama sekali |
| **Mulai Tampil / Berhenti Tampil** | Jadwal kemunculan di sisi siswa. Kosong = langsung tampil / tampil selamanya |
| **Batas Waktu (deadline)** | Batas pengumpulan tugas. Lewat batas = ditolak sistem |
| **Online Quiz** | Ujian dijawab langsung di website, ada durasi, sebagian dinilai otomatis |
| **Submission** | Ujian/tugas dengan cara mengunggah file jawaban |
| **To-Do** | Panel daftar tugas & ujian siswa yang belum selesai |
| **Rekap Nilai** | Tabel nilai seluruh siswa per mapel kelas, bisa di-export Excel |

---

*Catatan: tampilan tombol dan nama menu di dokumen ini mengikuti versi aplikasi saat panduan ini dibuat. Kalau ada menu yang namanya berbeda sedikit, alurnya tetap sama.*
