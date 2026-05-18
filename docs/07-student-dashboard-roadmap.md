# Student Dashboard — Roadmap

Rencana implementasi dashboard siswa setelah dashboard guru (Filament) selesai. Dibagi 5 fase yang masing-masing bisa dites end-to-end sebelum lanjut.

## Stack Frontend Siswa

| Layer | Pilihan | Catatan |
|-------|---------|---------|
| Framework | **Inertia + React** | Library sudah ter-install (`inertiajs/inertia-laravel`, ziggy) |
| Bahasa | **TypeScript** | Sudah ada `tsconfig.json` di project |
| Styling | **Tailwind CSS** | Utility-first, no custom CSS kecuali sangat perlu |
| Animasi | **Framer Motion** | Page transition, hover, list stagger, micro-interaction |
| Routes | `/student/*` | Diputuskan: prefix `/student/*`, root `/` redirect ke `/student/dashboard` (atau `/student/login`) |
| Auth | Custom guard `student` | Login: NIS + tanggal lahir (bukan email/password) |

## Prinsip Desain UI

Semua halaman siswa harus mengikuti prinsip ini — **minimalis, jelas, elegan**:

- **Layout lapang.** Banyak whitespace, hierarki tipografi jelas (judul vs body vs meta).
- **Palet warna terbatas.** Satu warna primer (`sky` / `indigo`), netral (`slate`/`zinc`), 2–3 warna semantik (success/warn/danger). Hindari gradient yang ramai.
- **Komponen ringan.** Card tipis (border `border-slate-200`, `rounded-2xl`, shadow halus `shadow-sm`). Tidak ada border tebal atau drop-shadow heavy.
- **Tipografi.** Font `Figtree` (sudah loaded). Heading `font-semibold tracking-tight`, body `text-slate-700`, meta `text-slate-500 text-sm`.
- **Ikon konsisten.** Pakai satu library — `lucide-react` (ringan, modern). Ukuran default `w-5 h-5`.
- **State jelas.** Empty state, loading skeleton, error — semua punya treatment yang sama (icon + 1 baris pesan + CTA opsional).
- **Animasi tidak mengganggu.** Durasi pendek (150–300ms), easing halus (`ease-out`). Animasi adalah feedback, bukan dekorasi.

### Pola Framer Motion yang Dipakai

| Pola | Kapan | Contoh |
|------|-------|--------|
| **Page transition** | Setiap kali halaman Inertia berganti | `fade + slight slide up` (10px → 0) — 250ms |
| **Stagger list** | List card course/materi/tugas | `staggerChildren: 0.05` + fade-in per item |
| **Hover lift** | Card interaktif | `whileHover={{ y: -2, scale: 1.01 }}` — 200ms |
| **Tap feedback** | Button primer | `whileTap={{ scale: 0.97 }}` |
| **Modal/Sheet** | Login error toast, konfirmasi | `AnimatePresence` + fade + scale 0.95 → 1 |
| **Countdown pulse** | Timer ujian < 1 menit | Subtle scale pulse pada angka detik |

Helper umum simpan di `resources/js/lib/motion.ts` — variant `fadeUp`, `staggerContainer`, `cardHover`. Komponen tinggal `<motion.div variants={fadeUp} />` — tidak perlu duplicate config.

## Asumsi Penting

- **Siswa tidak punya akun di tabel `users`** — `students.user_id` nullable, login pakai NIS + birth_date saja
- **Visibility filter wajib** — siswa hanya melihat material/assignment/exam yang `is_published=true` DAN sekarang ada dalam range `available_from`–`available_until`
- **Scoping wajib** — siswa hanya melihat course di mana dia ter-enroll (`classroom_students` table)

---

## Phase 1 — Foundation (Setup + Auth)

**Tujuan:** Siswa bisa login dan lihat skeleton dashboard.

### Deliverables

1. **Verify Inertia + React setup**
   - Cek `vite.config.js`, root layout, dan dependencies React/TS
   - Pastikan `@inertiajs/react` ter-install
   - Setup ziggy untuk route name di JS

2. **Student auth guard**
   - File: `config/auth.php` — tambah guard `student` (provider model `Student`)
   - File: `app/Http/Middleware/AuthenticateStudent.php` — middleware
   - Login form: input NIS + birth_date (`d/m/Y` format)
   - Logic: cari student where `nisn = X` AND `birth_date = parsed date` AND `is_active = true`

3. **Routes & controllers**
   - File: `routes/web.php` — student routes group dengan middleware `auth:student`
   - File: `app/Http/Controllers/Student/AuthController.php` — login/logout
   - File: `app/Http/Controllers/Student/DashboardController.php` — landing page

4. **Layout & UI** (Tailwind + Framer Motion) — lihat section "Struktur Folder Frontend"
   - `resources/js/Pages/Auth/StudentLogin.tsx` — login form di-center, card tipis `max-w-md`
   - `resources/js/Pages/Dashboard/Dashboard.tsx` — landing page dengan hero + grid course
   - `resources/js/Layouts/StudentLayout.tsx` — topbar minimalis (logo kiri, nama siswa + logout kanan)
   - `resources/js/Components/Dashboard/{CourseCard,HeroGreeting}.tsx` — komponen khusus dashboard
   - `resources/js/Components/Dashboard/subjects.ts` — peta mapel → icon + warna (co-located)
   - `resources/js/Components/Dashboard/dashboard.type.ts` — `Course`, `DashboardPageProps`
   - `resources/js/Components/EmptyState.tsx` — empty state generic
   - `resources/js/lib/motion.ts` — motion variants (`fadeUp`, `staggerContainer`, `cardHover`, `pageTransition`)
   - `resources/js/lib/toast.tsx` + `useFlashToast.ts` — sonner wrapper + auto-toast dari flash session

5. **Dashboard isi:**
   - **Greeting**: "Halo, [Nama]" — fade-in dari atas, font besar `text-2xl font-semibold`
   - **Subtitle**: kelas + tahun ajaran, `text-slate-500`
   - **Grid course**: 1 kolom mobile, 2 kolom md, 3 kolom lg — staggered fade-in
   - **Card course**: nama mapel (heading), nama kelas + guru (meta), badge semester. Hover: lift 2px + border `border-sky-300`
   - **Empty state**: ikon buku + "Belum ada course terdaftar"

### Acuan Visual (ASCII)

```
┌───────────────────────────────────────────────────┐
│  📚 LMS                       Ahmad Fauzi  Logout │  ← topbar, 64px, border-b tipis
├───────────────────────────────────────────────────┤
│                                                   │
│   Halo, Ahmad                                     │  ← 2xl semibold
│   X IPA 1 • Tahun Ajaran 2025/2026                │  ← sm slate-500
│                                                   │
│   ┌─────────────┐ ┌─────────────┐ ┌────────────┐ │
│   │ Matematika  │ │ Fisika      │ │ Kimia      │ │  ← cards, rounded-2xl,
│   │ Pak Budi    │ │ Bu Sari     │ │ Pak Dedi   │ │     border-slate-200,
│   │ Semester 1  │ │ Semester 1  │ │ Semester 1 │ │     shadow-sm
│   └─────────────┘ └─────────────┘ └────────────┘ │
│                                                   │
└───────────────────────────────────────────────────┘
```

### Acceptance Criteria

- ✅ Siswa login dengan NIS `0012345001` + tanggal `15/01/2008` → masuk dashboard
- ✅ Siswa tidak bisa akses panel Filament (`/teacher/*`)
- ✅ Siswa lihat 3 course Matematika (dari seeder X IPA 1)
- ✅ Logout berfungsi

### Estimasi

**1–2 jam** (mostly setup, sedikit logic).

---

## Phase 2 — Pembelajaran (View Materi)

**Tujuan:** Siswa bisa lihat materi pembelajaran.

### Deliverables

1. **Course detail page**
   - File: `resources/js/Pages/Student/CourseDetail.tsx`
   - Tampilan: info course (kelas, mapel, guru, semester)
   - Tab/section: Materi, Tugas, Ujian (Tugas & Ujian di Phase 3 & 4)

2. **List materi**
   - Sorted by `order` ascending
   - Filter visibility:
     ```php
     where('is_published', true)
     ->where(fn($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
     ->where(fn($q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
     ```
   - Tampilan card per materi: judul, topik, tanggal publish

3. **Material detail page**
   - File: `resources/js/Pages/Student/MaterialDetail.tsx`
   - Render konten:
     - **Text** (HTML dari RichEditor) — pakai `dangerouslySetInnerHTML` + sanitize
     - **File** — daftar file dengan link download (URL dari `getMedia('material_files')->getUrl()`)
     - **Link** — embed atau "Buka link" button
   - Render KaTeX (sama seperti di guru) untuk formula matematika

4. **Render Math di React**
   - Install `katex` + `react-katex` (atau pakai CDN seperti di Filament)
   - Component `<MathContent html={content} />` — parse HTML + render `$...$` jadi math

### Acceptance Criteria

- ✅ Klik course → masuk halaman detail
- ✅ List materi muncul, tersortir, hanya yang published & dalam range
- ✅ Klik materi → konten text/file/link tampil
- ✅ Materi yang berisi `$x^2$` muncul sebagai x² (KaTeX render)

### Estimasi

**3–4 jam**.

---

## Phase 3 — Submission (Tugas)

**Tujuan:** Siswa bisa kerjakan dan submit tugas.

### Deliverables

1. **List tugas di course detail**
   - Per tugas: judul, deadline, status submit (Belum / Sudah / Sudah dinilai)
   - Badge warna: hijau (selesai), kuning (belum, deadline jauh), merah (lewat deadline)

2. **Assignment detail page**
   - File: `resources/js/Pages/Student/AssignmentDetail.tsx`
   - Tampilan: deskripsi soal, deadline, max score, lampiran guru
   - Form submission:
     - Textarea untuk essay
     - FileUpload (multi) — validasi `allowed_file_types` + `max_file_size_mb` dari assignment
     - TextInput URL untuk link
     - Tombol Submit / Edit Submission (boleh edit selama belum deadline)

3. **Submission logic**
   - File: `app/Http/Controllers/Student/AssignmentController.php`
   - Method `submit()`:
     - Validasi siswa enrolled di classroom course
     - Validasi belum lewat deadline (atau allow late?)
     - Create/update `AssignmentSubmission`
     - Attach files via Spatie Media Library (collection `submission_files`)
     - Set `submitted_at = now()`

4. **View nilai & feedback**
   - Setelah dinilai guru, tampil score + feedback di halaman assignment
   - History submission (kalau pernah edit)

### Acceptance Criteria

- ✅ Siswa lihat list tugas dengan status benar
- ✅ Submit essay + file + link → semua tersimpan
- ✅ File ekstensi di luar `allowed_file_types` → ditolak
- ✅ File > `max_file_size_mb` → ditolak
- ✅ Setelah guru kasih nilai → siswa lihat score & feedback
- ✅ Lewat deadline → tombol submit disabled

### Estimasi

**4–5 jam**.

---

## Phase 4 — Ujian (Paling Kompleks)

**Tujuan:** Siswa bisa kerjakan ujian dengan dua mode.

### Deliverables Mode `online_quiz`

1. **Exam start screen**
   - File: `resources/js/Pages/Student/ExamStart.tsx`
   - Info: judul, deskripsi, durasi, jumlah soal, max score
   - Tombol "Mulai Ujian" → create `ExamSession`, set `started_at = now()`
   - Kalau sudah pernah mulai → resume dari soal terakhir

2. **Exam taking page** (fokus utama)
   - File: `resources/js/Pages/Student/ExamTake.tsx`
   - Layout:
     ```
     ┌──────────────────────────────────────┐
     │  Judul Ujian       ⏱  45:23 remaining│
     ├──────────────────────────────────────┤
     │  Soal 5 dari 20                      │
     │                                      │
     │  Pertanyaan (dengan math render)     │
     │                                      │
     │  ○ A. Opsi 1                         │
     │  ● B. Opsi 2 (selected)              │
     │  ○ C. Opsi 3                         │
     │  ○ D. Opsi 4                         │
     │                                      │
     │  [◀ Sebelumnya]  [Tandai]  [Berikut▶]│
     ├──────────────────────────────────────┤
     │  Navigasi: 1 2 3 4 [5] 6 ... 20      │
     │  [ Selesaikan Ujian ]                │
     └──────────────────────────────────────┘
     ```
   - Timer countdown dari `started_at + duration_minutes` (server-side time)
   - Auto-save jawaban tiap kali pilih/ketik (debounced, 1 detik)
   - Auto-submit saat timer habis
   - Soal shuffle order kalau `exam.shuffle_questions = true` — simpan urutan di session

3. **Auto-grading saat submit**
   - File: `app/Services/ExamGrader.php`
   - Method `grade(ExamSession $session)`:
     - Iterate semua `exam_answers`
     - Untuk `multiple_choice`: cocokkan `answer === question.correct_answer` → score = full / 0
     - Untuk `short_answer`: case-insensitive trim match → score = full / 0
     - Untuk `essay`: skip (score tetap null, manual grade by guru)
     - Update `exam_session.total_score = sum(scores)`
     - Set `submitted_at = now()`

4. **Result page**
   - File: `resources/js/Pages/Student/ExamResult.tsx`
   - Tampilan total skor (atau "Menunggu penilaian guru" kalau ada essay)
   - Tidak tampilkan jawaban benar (anti-cheat) — kecuali ada setting `show_answers_after`

### Deliverables Mode `submission`

1. **Mirip Assignment** — siswa kumpul teks/file/link sebagai jawaban
2. File: `resources/js/Pages/Student/ExamSubmissionForm.tsx`
3. Method `submit()`:
   - Create/update `ExamSubmission`
   - Attach files (`submission_files`)
   - Set `submitted_at = now()`
4. Validasi:
   - Mengumpul harus dalam window `starts_at` sampai `starts_at + duration_minutes`
   - Atau lebih longgar — sampai `available_until`?

### Acceptance Criteria

**Online Quiz:**
- ✅ Siswa klik mulai → session ter-create dengan `started_at`
- ✅ Timer berjalan, sync dengan server time
- ✅ Pindah-pindah soal jawaban tersimpan
- ✅ Auto-submit saat timer habis
- ✅ Multiple choice + short answer auto-graded
- ✅ Essay tetap null, menunggu guru
- ✅ Refresh page → resume di tempat terakhir

**Submission:**
- ✅ Siswa submit text/file/link → tersimpan
- ✅ Aturan file (tipe + size) divalidasi

### Estimasi

**8–12 jam** (engine ujian online_quiz adalah bagian terbesar).

---

## Phase 5 — Polish Guru (Akhir)

**Tujuan:** Lengkapi sisi guru setelah student dashboard jalan.

### Deliverables

1. **Dashboard Widgets** — di halaman utama Filament `/teacher`
   - `ClassroomStatsWidget` — total kelas yang diampu
   - `StudentStatsWidget` — total siswa di semua kelas
   - `PendingGradingWidget` — jumlah submission/session yang belum dinilai
   - `UpcomingExamWidget` — ujian yang akan datang minggu ini

2. **GradeResource (Rekap Nilai)**
   - File: `app/Filament/Resources/GradeResource.php`
   - Bukan CRUD biasa — custom page
   - Filter: pilih classroom + subject
   - Tabel: per siswa, kolom dinamis (semua tugas + ujian di course itu)
   - Cell value: skor atau "—"
   - Inline edit cell untuk override nilai
   - Tombol Export Excel (pakai `maatwebsite/excel`)

3. **Notifications (optional)**
   - Filament built-in notifications
   - Listener event:
     - `AssignmentSubmitted` — notif ke guru course
     - `ExamSubmitted` — notif ke guru course
   - Bell icon di topbar Filament

4. **Policies (security hardening)**
   - File: `app/Policies/ClassroomPolicy.php`, dll
   - Untuk audit/compliance, formal authorization rules

### Estimasi

**4–6 jam**.

---

## Total Estimasi

| Phase | Estimasi |
|-------|----------|
| 1 — Foundation | 1–2 jam |
| 2 — Pembelajaran | 3–4 jam |
| 3 — Submission Tugas | 4–5 jam |
| 4 — Ujian | 8–12 jam |
| 5 — Polish Guru | 4–6 jam |
| **Total** | **20–29 jam** |

---

## Risiko & Mitigasi

| Risiko | Mitigasi |
|--------|----------|
| Timer ujian bisa di-bypass via JS console | Validasi waktu di server saat submit; hitung `now() - started_at > duration_minutes + buffer` → reject |
| Siswa refresh page saat ujian | Auto-save jawaban tiap perubahan, resume saat reload |
| File upload besar (siswa upload video?) | Validasi `max_file_size_mb` di server (bukan cuma frontend) |
| Race condition saat submit serentak | Pakai DB transaction + unique constraint `(exam_id, student_id)` di sessions |
| Math formula tidak render di mobile siswa | Test KaTeX di mobile breakpoint; fallback raw LaTeX |
| Siswa lupa NISN | Provide "Lupa NISN?" — kontak guru/admin (no self-reset karena tidak ada email) |

---

## Struktur Folder Frontend

Ikut convention Laravel Breeze persis — top-level folder by-type (`Pages/`, `Components/`, `Layouts/`, `lib/`, `types/`). Fitur di-group sebagai **subfolder dalam `Components/`** ketika punya banyak komponen sendiri. Tidak ada `features/`, tidak ada `shared/`.

### Layout

```
resources/js/
├── app.tsx                            ← Inertia bootstrap (resolver default)
├── bootstrap.ts
├── Pages/                             ← Inertia page (entry points)
│   ├── Auth/
│   │   └── StudentLogin.tsx           ← Phase 1
│   ├── Dashboard/
│   │   └── Dashboard.tsx              ← Phase 1
│   ├── Course/
│   │   └── CourseDetail.tsx           ← Phase 2
│   ├── Material/
│   │   └── MaterialDetail.tsx         ← Phase 2
│   ├── Assignment/
│   │   └── AssignmentDetail.tsx       ← Phase 3
│   └── Exam/                          ← Phase 4
│       ├── ExamStart.tsx
│       ├── ExamTake.tsx
│       ├── ExamResult.tsx
│       └── ExamSubmissionForm.tsx
├── Components/                        ← komponen reusable
│   ├── EmptyState.tsx                 ← cross-cutting (top-level)
│   ├── MathContent.tsx                ← Phase 2 (cross-cutting)
│   ├── FileCard.tsx                   ← Phase 2 (cross-cutting)
│   ├── CountdownTimer.tsx             ← Phase 4 (cross-cutting)
│   ├── Dashboard/                     ← Phase 1 (komponen + helper + types khusus dashboard)
│   │   ├── CourseCard.tsx
│   │   ├── HeroGreeting.tsx
│   │   ├── subjects.ts                (peta MTK/FIS/dll → icon + warna)
│   │   ├── dashboard.type.ts          (Course, DashboardMeta, DashboardPageProps)
│   │   └── index.ts                   (barrel re-export)
│   ├── Assignment/                    ← Phase 3
│   │   ├── SubmissionForm.tsx
│   │   ├── FilePicker.tsx
│   │   ├── assignment.type.ts
│   │   └── index.ts
│   └── Exam/                          ← Phase 4
│       ├── QuestionNavigator.tsx
│       ├── ExamTimer.tsx
│       ├── useExamTimer.ts            (hook, co-located)
│       ├── exam.type.ts
│       └── index.ts
├── Layouts/
│   ├── StudentLayout.tsx              ← yang kita pakai
│   ├── AuthenticatedLayout.tsx        (breeze, legacy)
│   ├── GuestLayout.tsx                (breeze, legacy)
│   └── index.ts                       (barrel — export StudentLayout)
├── lib/                               ← framework/utility code (cross-cutting)
│   ├── motion.ts                      (fadeUp, stagger, pageTransition)
│   ├── toast.tsx                      (sonner wrapper + AppToaster)
│   ├── useFlashToast.ts               (auto-toast dari Inertia flash)
│   ├── api.ts                         (axios setup) — Phase 3+
│   └── index.ts                       (barrel)
└── types/                             ← global TS types
    ├── index.d.ts                     (PageProps, FlashMessages, User, Student)
    ├── global.d.ts                    (window.axios, ziggy route)
    └── vite-env.d.ts
```

### Aturan utama

1. **Pages = entry points Inertia, di `Pages/<Feature>/<Page>.tsx`.** Subdir biasanya match nama feature (mis. `Pages/Dashboard/Dashboard.tsx`).
2. **Komponen reusable di `Components/`.** Kalau cross-cutting (mis. `EmptyState`) → langsung top-level. Kalau khusus fitur → masuk subfolder PascalCase (mis. `Components/Dashboard/`).
3. **Co-located helpers & types** di samping komponen yang pakai (mis. `Components/Dashboard/subjects.ts` + `dashboard.type.ts`). Tidak ada `Helpers/` atau `Types/` per-fitur.
4. **`lib/`** untuk utilitas cross-cutting yang **bukan komponen** (Framer Motion variants, sonner wrapper, axios setup, hooks generik). Lowercase, mengikuti breeze.
5. **`types/`** hanya untuk **global types** (mis. `PageProps`, `FlashMessages`). Type khusus fitur ditaruh di samping komponennya.
6. **Buat subfolder hanya saat ada file pertama.** Jangan pre-create kosong.
7. **Barrel `index.ts`** di setiap feature-folder (`Components/Dashboard/index.ts`) supaya konsumen tinggal `import { CourseCard, Course } from '@/Components/Dashboard'`.
8. **Naming:**
   - Folder top-level: ikut breeze — `Pages/`, `Components/`, `Layouts/` (PascalCase); `lib/`, `types/` (lowercase)
   - Feature subfolder dalam `Components/`: **PascalCase** match nama Inertia page → `Dashboard/`, `Assignment/`, `Exam/`
   - File komponen: **PascalCase.tsx** → `CourseCard.tsx`
   - File types: `<feature>.type.ts` → `dashboard.type.ts`
   - File helpers/hooks: **camelCase.ts** → `subjects.ts`, `useExamTimer.ts`

### Inertia Page Resolver

Default Inertia — nama page langsung resolve ke `Pages/<name>.tsx`:

```php
return Inertia::render('Auth/StudentLogin');               // → Pages/Auth/StudentLogin.tsx
return Inertia::render('Dashboard/Dashboard');             // → Pages/Dashboard/Dashboard.tsx
return Inertia::render('Assignment/AssignmentDetail', []); // → Pages/Assignment/AssignmentDetail.tsx
```

### Pola Import (pakai barrel)

```tsx
// ✅ baik — barrel import per folder
import { CourseCard, HeroGreeting, DashboardPageProps } from '@/Components/Dashboard';
import { EmptyState } from '@/Components';
import { StudentLayout } from '@/Layouts';
import { staggerContainer, toast, useFlashToast } from '@/lib';
import { PageProps } from '@/types';

// ❌ hindari — deep import bikin refactor lebih ribet
import CourseCard from '@/Components/Dashboard/CourseCard';
import { toast } from '@/lib/toast';
```

### Kapan Bikin Subfolder Baru di `Components/`?

Bikin folder fitur (mis. `Components/Course/`) kalau:
- Punya **2+ komponen** khusus fitur itu (1 komponen → langsung top-level Components/, tidak perlu subfolder)
- Punya helper/type khusus yang co-located dengan komponennya

Kalau cuma 1 komponen kecil tanpa helper/type khusus → letakkan di top-level `Components/` saja.

## Dependency Tambahan untuk Student Dashboard

| Package | Untuk | Fase |
|---------|-------|------|
| `framer-motion` | Semua animasi (page transition, list stagger, hover, modal) | Phase 1 |
| `lucide-react` | Icon set (book, user, clock, chevron, dll) | Phase 1 |
| `katex` + `react-katex` | Render math di materi & soal ujian | Phase 2 |
| `clsx` (opsional) | Conditional className lebih ringkas | Phase 1 |

Install di Phase 1:
```bash
npm install framer-motion lucide-react clsx
```

---

## Next Step

Mulai **Phase 1**:
1. Cek state Inertia/React/Vite saat ini
2. Setup student auth guard
3. Buat halaman login NIS + tanggal lahir
4. Skeleton dashboard berisi list course
