# Filament Teacher Dashboard — Perubahan per Phase

Catatan apa yang perlu disesuaikan di sisi panel Filament guru (`/teacher`)
sebagai konsekuensi implementasi Phase 1–4 student dashboard.

Tujuan: orang yang lanjut kerjakan sisi guru tahu (1) apa yang **sudah aman**,
(2) bug yang **harus diperbaiki sekarang**, (3) gap UX yang **berpengaruh ke
workflow grading**, dan (4) backlog Phase 5 yang masih jadi rencana.

Konvensi:
- 🔴 **CRITICAL** — bug yang membuat fitur tidak jalan / data tidak konsisten. Wajib fix sekarang.
- 🟠 **HIGH** — fitur student-side sudah jalan, tapi Filament-side belum lengkap. Guru tidak bisa selesaikan workflow inti (grading).
- 🟡 **MEDIUM** — quality-of-life improvement. Tidak blocking, tapi cukup mengganggu.
- 🟢 **LOW / Phase 5** — backlog roadmap, belum direncanakan untuk dikerjakan sekarang.
- ✅ **DONE** — sudah ada, tidak perlu diubah.

---

## 0. Ringkasan Cross-Cutting

Perubahan yang menyentuh seluruh panel Filament, bukan resource tertentu.

### ✅ Cross-guard middleware

| File | Fungsi |
|------|--------|
| [app/Http/Middleware/TeacherPanelAuthenticate.php](../app/Http/Middleware/TeacherPanelAuthenticate.php) | Override `Filament\Http\Middleware\Authenticate`. Saat user adalah siswa (guard `student`), redirect ke `student.dashboard` daripada `/teacher/login`. |
| [app/Http/Middleware/RedirectIfWrongGuard.php](../app/Http/Middleware/RedirectIfWrongGuard.php) | Web group: kalau user web (guru/admin) buka `/student/*`, redirect ke `/teacher`. |
| [app/Providers/Filament/TeacherPanelProvider.php](../app/Providers/Filament/TeacherPanelProvider.php) | `authMiddleware([TeacherPanelAuthenticate::class])`. |

**Tidak ada perubahan UX guru** — middleware ini hanya intercept kasus salah panel.

### ✅ Activitylog auto-causer

| File | Fungsi |
|------|--------|
| [app/Providers/AppServiceProvider.php](../app/Providers/AppServiceProvider.php) | `CauserResolver::resolveUsing()` pick up `Auth::guard('web')` (guru) atau `Auth::guard('student')`. |

**Implikasi**: tiap kali guru update nilai/feedback di Filament, kolom
`activity_log.causer_id = users.id` (guru) terisi otomatis. Student-side
timeline bisa bedakan "Kamu update" vs "Dinilai oleh guru".

### ✅ Schema baru (student-side changes yang nyentuh DB guru)

| Model | Perubahan | Impact untuk Filament |
|-------|-----------|-----------------------|
| `assignment_submissions` | `last_edited_at` di-drop · `graded_at` di-add · `link_url` di-add | Form Edit nilai tidak perlu sentuh `last_edited_at`. `graded_at` auto. **`link_url` perlu di-display di view submission** (lihat §3). |
| `exam_submissions` | `graded_at` di-add (auto-set saat score diisi) | `graded_at` auto. |
| `activity_log` | Tabel baru (UUID morphs via `nullableUuidMorphs`) | Bisa dipakai untuk timeline read-only (lihat §6). |

---

## 1. 🔴 CRITICAL — Bug yang membuat fitur tidak jalan

### 1.1 Format `options` MC tidak konsisten antara Filament dan student-side

**Status**: BUG. Soal Multiple Choice yang dibuat lewat Filament akan **render salah** di student-side (opsi muncul sebagai `[object Object]`).

**Root cause**:

| Sumber | Format JSON tersimpan di `exam_questions.options` |
|--------|----------------------------------------------------|
| `ExamSeeder` (saat ini) | `{"A":"Memahami...","B":"Menghafal..."}` — associative array |
| Filament `QuestionsRelationManager` Repeater | `[{"label":"A","text":"Memahami..."}, {"label":"B","text":"..."}]` — list of objects |

Verified via DB query (Output `--- options format ---` di smoke test).

Student-side `normalizeOptions()` di
[resources/js/Pages/Exam/ExamTake.tsx](../resources/js/Pages/Exam/ExamTake.tsx) cuma handle dua kasus:
```ts
if (Array.isArray(raw)) {
    return raw.map((text, i) => ({ key: String.fromCharCode(65 + i), text }));
    // Kalau text adalah object {label, text} → React render "[object Object]"
}
if (raw && typeof raw === 'object') {
    return Object.entries(raw).map(([key, text]) => ({ key, text }));
}
```

**Solusi (pilih salah satu — saya rekomendasi A)**:

#### Option A — Transform di Filament saat save & load (recommended)

Edit [app/Filament/Resources/ExamResource/RelationManagers/QuestionsRelationManager.php](../app/Filament/Resources/ExamResource/RelationManagers/QuestionsRelationManager.php):

```php
Repeater::make('options')
    ->label('Opsi Jawaban')
    ->schema([
        TextInput::make('label')->required()->maxLength(5)->columnSpan(1),
        RichEditor::make('text')->required()->toolbarButtons($richEditorButtons)->columnSpan(3),
    ])
    ->columns(4)
    // ↓ TAMBAHKAN: konversi assoc → list saat load
    ->formatStateUsing(function ($state) {
        if (! is_array($state) || empty($state)) {
            return [
                ['label' => 'A', 'text' => null],
                ['label' => 'B', 'text' => null],
                ['label' => 'C', 'text' => null],
                ['label' => 'D', 'text' => null],
            ];
        }
        // Already list-of-objects? pass through.
        if (isset($state[0]['label'])) {
            return $state;
        }
        // Assoc ['A' => 'foo'] → [['label'=>'A','text'=>'foo'], ...]
        return collect($state)->map(fn ($text, $label) => [
            'label' => $label,
            'text' => $text,
        ])->values()->all();
    })
    // ↓ TAMBAHKAN: konversi list → assoc saat save
    ->dehydrateStateUsing(function ($state) {
        return collect($state ?? [])
            ->filter(fn ($row) => filled($row['label'] ?? null))
            ->mapWithKeys(fn ($row) => [$row['label'] => $row['text'] ?? ''])
            ->all();
    })
    // ...sisa config sama
```

#### Option B — Frontend handle dual format

Edit `normalizeOptions` di ExamTake.tsx untuk handle `[{label, text}]`:

```ts
function normalizeOptions(raw: ExamQuestionItem['options']): { key: string; text: string }[] {
    if (Array.isArray(raw)) {
        // Filament format: [{label, text}]
        if (raw.length > 0 && typeof raw[0] === 'object' && 'label' in (raw[0] as object)) {
            return (raw as Array<{ label: string; text: string }>).map((r) => ({
                key: r.label,
                text: r.text,
            }));
        }
        // Plain string list (legacy / seeder)
        return raw.map((text, i) => ({ key: String.fromCharCode(65 + i), text: text as string }));
    }
    if (raw && typeof raw === 'object') {
        return Object.entries(raw as Record<string, string>).map(([key, text]) => ({ key, text }));
    }
    return [];
}
```

**Rekomendasi**: Option A — sumber kebenaran storage tetap satu format
(associative array `{label: text}`), tidak ada keputusan parsing di FE.

**Testing checklist**:
- [ ] Buat soal MC baru lewat Filament dengan 4 opsi (A–D)
- [ ] Cek DB: `select options from exam_questions where ...` → harus `{"A":"...","B":"..."}`
- [ ] Buka soal di student dashboard — opsi tampil sebagai teks, bukan `[object Object]`
- [ ] Edit soal di Filament — opsi sebelumnya muncul dengan label & text yang benar
- [ ] Pilih opsi B → submit → auto-grade benar bila B = correct_answer

---

### 1.2 (Tidak ada CRITICAL lain ditemukan saat audit ini)

---

## 2. 🟠 HIGH — Grading workflow yang belum lengkap

### 2.1 `AssignmentSubmissionsRelationManager`: guru tidak bisa lihat submission siswa

**File**: [app/Filament/Resources/AssignmentResource/RelationManagers/SubmissionsRelationManager.php](../app/Filament/Resources/AssignmentResource/RelationManagers/SubmissionsRelationManager.php)

**Masalah**:
- Form `EditAction` hanya menampilkan field `score` + `feedback`
- Guru **tidak bisa lihat** dari modal Beri Nilai:
  - `content` (jawaban siswa, sekarang HTML dari Trix)
  - `link_url` (kolom baru di Phase 4 carry-over)
  - File lampiran siswa (`submission_files` Spatie media)
- Workflow saat ini: guru harus menebak nilai tanpa lihat jawaban, atau buka tab lain ke student dashboard.

**Fix lengkap** — replace `form()` method:

```php
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Support\HtmlString;

public function form(Form $form): Form
{
    return $form->schema([
        Section::make('Jawaban Siswa')
            ->icon('heroicon-o-document-text')
            ->schema([
                Placeholder::make('submitted_at_view')
                    ->label('Dikumpulkan')
                    ->content(fn ($record) => $record?->submitted_at
                        ? $record->submitted_at->translatedFormat('l, d F Y · H:i')
                        : '—'),

                Placeholder::make('content_view')
                    ->label('Esai / Jawaban Teks')
                    ->content(fn ($record) => new HtmlString(
                        '<div class="prose dark:prose-invert max-w-none">'
                        .($record?->content ?: '<em class="text-gray-500">Tidak ada teks</em>')
                        .'</div>'
                    ))
                    ->columnSpanFull(),

                Placeholder::make('link_view')
                    ->label('Tautan Referensi')
                    ->content(fn ($record) => $record?->link_url
                        ? new HtmlString(
                            '<a href="'.e($record->link_url).'" target="_blank" rel="noopener" '
                            .'class="text-primary-600 hover:text-primary-700 underline break-all">'
                            .e($record->link_url).'</a>'
                        )
                        : '—')
                    ->visible(fn ($record) => filled($record?->link_url))
                    ->columnSpanFull(),

                SpatieMediaLibraryFileUpload::make('submission_files')
                    ->collection('submission_files')
                    ->label('Lampiran Siswa')
                    ->disabled()
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull()
                    ->visible(fn ($record) => $record?->getMedia('submission_files')->isNotEmpty()),
            ])
            ->collapsible(),

        Section::make('Penilaian')
            ->icon('heroicon-o-star')
            ->schema([
                TextInput::make('score')
                    ->label('Nilai')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(fn () => $this->getOwnerRecord()->max_score)
                    ->suffix(fn () => '/ '.$this->getOwnerRecord()->max_score),

                Textarea::make('feedback')
                    ->label('Feedback / Catatan untuk Siswa')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(2),
    ]);
}
```

Sekaligus, tambah `ViewAction` (read-only modal) di samping `EditAction`:

```php
use Filament\Tables\Actions\ViewAction;

->actions([
    ViewAction::make()->label('Lihat Detail'),
    ActionGroup::make([
        EditAction::make()->label('Beri Nilai'),
    ]),
]);
```

Lalu tambahkan `infolist()` di RelationManager — pakai struktur sama dengan
form di atas tapi pakai `Filament\Infolists\Components\Section` & `TextEntry`.

**Testing checklist**:
- [ ] Klik "Beri Nilai" pada submission siswa → modal tampilkan content/link/file
- [ ] Input score di luar range max_score → validation error
- [ ] Save score → `submissions.score` & `graded_at` (auto) terisi
- [ ] Cek `activity_log`: ada row dengan `causer_type=User::class`, `event=updated`
- [ ] Siswa buka assignment detail → timeline tampil "Dinilai oleh guru"
- [ ] File yang di-upload siswa bisa di-download dari modal guru

---

### 2.2 `ExamSubmissionsRelationManager`: gap identik (mode submission)

**File**: [app/Filament/Resources/ExamResource/RelationManagers/SubmissionsRelationManager.php](../app/Filament/Resources/ExamResource/RelationManagers/SubmissionsRelationManager.php)

**Masalah**: persis sama dengan §2.1 — form Beri Nilai hanya `score` + `feedback`.

**Fix**: copy snippet dari §2.1, ganti relationship `submission_files` collection
tetap pakai nama yang sama (model `ExamSubmission` juga pakai collection
`submission_files`).

```php
// Hanya 1 perbedaan dari §2.1: tetap visible-on-submission-mode guard
public static function canViewForRecord($ownerRecord, string $pageClass): bool
{
    return $ownerRecord->mode === ExamModeEnum::Submission;
}
```

**Testing checklist**: sama dengan §2.1, plus:
- [ ] Verify isolasi: `submission_files` di AssignmentSubmission vs ExamSubmission
  tidak tercampur (Spatie media library isolate via `model_type`).

---

### 2.3 `QuestionsRelationManager`: tidak ada kolom "kunci jawaban" di tabel

**File**: [app/Filament/Resources/ExamResource/RelationManagers/QuestionsRelationManager.php](../app/Filament/Resources/ExamResource/RelationManagers/QuestionsRelationManager.php)

**Masalah**: di list soal, guru tidak bisa cepat verifikasi `correct_answer`
tanpa klik Edit. Untuk soal MC dengan 20+ items, ini bikin proofreading lama.

**Fix** — tambah kolom:

```php
TextColumn::make('correct_answer')
    ->label('Kunci')
    ->placeholder('—')
    ->badge()
    ->color(fn ($record) => $record->type === QuestionTypeEnum::Essay ? 'gray' : 'success')
    ->formatStateUsing(fn ($state, $record) => $record->type === QuestionTypeEnum::Essay
        ? 'Manual'
        : ($state ?? '—'))
    ->toggleable(),
```

---

### 2.4 Tidak ada cara recompute total_score saat `correct_answer` diubah

**Masalah**: setelah siswa submit, kalau guru sadar kunci MC salah dan edit
soal, `ExamGrader::grade()` **tidak otomatis re-run**. Siswa lihat nilai lama.

**Fix Option A** — observer di model `ExamQuestion`:

```php
// app/Models/ExamQuestion.php
protected static function booted(): void
{
    static::saved(function (self $question) {
        if (! $question->wasChanged('correct_answer')) {
            return;
        }
        $exam = $question->exam;
        $grader = app(\App\Services\ExamGrader::class);
        $exam->sessions()
            ->whereNotNull('submitted_at')
            ->get()
            ->each(fn ($s) => $grader->grade($s));
    });
}
```

**Fix Option B** (manual trigger) — tambah action di QuestionsRelationManager:

```php
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

->headerActions([
    CreateAction::make()->label('Tambah Soal'),
    Action::make('regrade')
        ->label('Hitung Ulang Nilai Semua Siswa')
        ->icon('heroicon-o-arrow-path')
        ->color('warning')
        ->requiresConfirmation()
        ->modalDescription('Auto-grader akan menghitung ulang skor MC + Short Answer untuk semua sesi yang sudah submitted.')
        ->action(function () {
            $exam = $this->getOwnerRecord();
            $grader = app(\App\Services\ExamGrader::class);
            $count = 0;
            $exam->sessions()->whereNotNull('submitted_at')->each(function ($s) use ($grader, &$count) {
                $grader->grade($s);
                $count++;
            });
            Notification::make()
                ->title("$count sesi dihitung ulang")
                ->success()
                ->send();
        }),
]),
```

**Rekomendasi**: Option A untuk reliability (auto), tapi observer bisa
mengundang surprise. Option B + dokumentasi internal lebih predictable.

**Testing checklist**:
- [ ] Buat exam dengan 3 soal, siswa submit dapat skor X
- [ ] Edit `correct_answer` soal MC → save
- [ ] (Option A) Cek `exam_sessions.total_score` terupdate otomatis
- [ ] (Option B) Klik "Hitung Ulang" → notif sukses + skor terupdate

---

## 3. 🟡 MEDIUM — Quality of life

### 3.1 KaTeX di RichEditor preview Filament

**File**: [app/Providers/Filament/TeacherPanelProvider.php:60-63](../app/Providers/Filament/TeacherPanelProvider.php)

Sudah ada render hook:
```php
->renderHook(
    PanelsRenderHook::BODY_END,
    fn (): string => Blade::render('<x-katex-init />'),
)
```

**Action**:
1. Verify file `resources/views/components/katex-init.blade.php` exists & inject KaTeX CSS + auto-render script
2. Test: buat material/soal dengan `$x^2 + 1$` di RichEditor → preview di Infolist render formula

Kalau component belum ada, tambah:

```blade
{{-- resources/views/components/katex-init.blade.php --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"
    onload="renderMathInElement(document.body, {
        delimiters: [
            {left: '$$', right: '$$', display: true},
            {left: '$', right: '$', display: false},
            {left: '\\(', right: '\\)', display: false},
            {left: '\\[', right: '\\]', display: true}
        ],
        throwOnError: false
    });">
</script>
```

(Untuk produksi, ganti CDN dengan asset lokal/Vite supaya konsisten dengan
student-side yang sudah pakai npm `katex`.)

---

### 3.2 StudentResource: tombol "Reset Password" & kolom "Login Terakhir"

**File**: [app/Filament/Resources/StudentResource.php](../app/Filament/Resources/StudentResource.php)

**Masalah**: siswa lupa password, guru/admin harus edit manual & isi password.

**Fix** — tambah header action:

```php
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Carbon\Carbon;

->actions([
    ViewAction::make(),
    ActionGroup::make([
        EditAction::make(),
        Action::make('resetPassword')
            ->label('Reset Password ke Tanggal Lahir')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(fn ($record) => "Password siswa {$record->full_name} akan di-reset ke tanggal lahir ({$record->birth_date->format('Y-m-d')}).")
            ->action(function ($record) {
                if (! $record->user) {
                    Notification::make()->title('Siswa belum punya akun User terkait')->danger()->send();
                    return;
                }
                $record->user->forceFill([
                    'password' => bcrypt($record->birth_date->format('Y-m-d')),
                ])->save();
                Notification::make()->title('Password berhasil di-reset')->success()->send();
            }),
        DeleteAction::make(),
    ]),
]);
```

Plus tambah kolom "Login Terakhir":

```php
TextColumn::make('user.last_login_at')
    ->label('Login Terakhir')
    ->since()
    ->placeholder('Belum pernah')
    ->toggleable(isToggledHiddenByDefault: true),
```

---

### 3.3 MaterialResource: tidak ada cara reorder materi

**File**: [app/Filament/Resources/MaterialResource.php:231-258](../app/Filament/Resources/MaterialResource.php)

**Masalah**: Material punya kolom `order` (auto-increment via boot hook), tapi
table tidak `reorderable('order')`. Guru tidak bisa drag-reorder materi di
dalam course tanpa edit DB manual.

**Fix** — tambah di method `table()`:

```php
->defaultSort('order')
->reorderable('order')
->columns([
    // tambahkan di awal:
    TextColumn::make('order')
        ->label('No')
        ->alignCenter()
        ->width(60),
    // ... existing columns
]);
```

Karena Material punya parent `classroomSubject`, reordering di top-level table
mungkin tidak ideal — bisa lintas-course. Lebih baik **pindahkan reordering
ke `MaterialsRelationManager`** di `CourseResource` (yang scope-nya per course):

```php
// app/Filament/Resources/CourseResource/RelationManagers/MaterialsRelationManager.php
->defaultSort('order')
->reorderable('order')
```

---

### 3.4 Material/Assignment/Exam Resource: form `order` tersembunyi

**Masalah**: kolom `order` auto-increment lewat `creating` hook di model.
Tapi kalau guru ingin insert material di posisi tengah, tidak ada UI untuk
override `order`. Saat ini opsi mereka cuma drag-reorder (kalau §3.3 sudah
dikerjakan).

**Fix** (opsional) — expose order di form sebagai numeric input dengan helper:

```php
TextInput::make('order')
    ->label('Urutan')
    ->numeric()
    ->minValue(1)
    ->helperText('Kosongkan untuk auto-increment di akhir.')
    ->dehydrated(fn ($state) => filled($state)),
```

---

### 3.5 `submissions_count` di table tidak ada

**Masalah**: di AssignmentResource & ExamResource table, tidak ada kolom yang
menampilkan jumlah submission masuk → guru harus klik View untuk tahu.

**Fix** — tambah ke table columns:

```php
// AssignmentResource::table()
TextColumn::make('submissions_count')
    ->label('Pengumpulan')
    ->counts('submissions')
    ->badge()
    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
    ->toggleable(),
```

Sama untuk ExamResource. Untuk Exam mode `online_quiz`, ganti dengan
`sessions_count`:

```php
TextColumn::make('sessions_count')
    ->label('Sesi')
    ->counts('sessions')
    ->visible(fn ($record) => $record->mode === ExamModeEnum::OnlineQuiz),
```

---

### 3.6 Filter: deadline upcoming / overdue di AssignmentResource

**Masalah**: tidak ada cara cepat melihat "tugas yang deadline-nya minggu ini"
atau "tugas yang sudah overdue".

**Fix** — tambah filter di `table()`:

```php
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;

->filters([
    Filter::make('overdue')
        ->label('Sudah Lewat Deadline')
        ->query(fn ($query) => $query->where('deadline', '<', now())),

    Filter::make('this_week')
        ->label('Deadline Minggu Ini')
        ->query(fn ($query) => $query->whereBetween('deadline', [now()->startOfWeek(), now()->endOfWeek()])),

    Filter::make('not_published')
        ->label('Belum Dipublish')
        ->query(fn ($query) => $query->where('is_published', false)),
]);
```

---

## 4. 🟡 MEDIUM — Display gap untuk Phase 5 schema baru

### 4.1 `link_url` siswa belum tampil di Infolist guru

Sudah dijelaskan di §2.1 — fix-nya termasuk `link_view` Placeholder.

Tapi juga perlu di view "list submission" supaya guru bisa preview tanpa
buka modal:

```php
// SubmissionsRelationManager::table()
TextColumn::make('link_url')
    ->label('Link')
    ->limit(30)
    ->url(fn ($state) => $state, true) // open in new tab
    ->placeholder('—')
    ->toggleable(),
```

---

### 4.2 `graded_at` tidak tampil di mana-mana

Setelah Phase 4 carry-over, `assignment_submissions.graded_at` & `exam_submissions.graded_at`
sudah auto-set. Tapi tidak ada kolom/badge yang menunjukkan kapan dinilai.

**Fix** — di SubmissionsRelationManager table columns:

```php
TextColumn::make('graded_at')
    ->label('Dinilai')
    ->dateTime('d M Y, H:i')
    ->placeholder('Belum dinilai')
    ->since()
    ->tooltip(fn ($record) => $record->graded_at?->format('d F Y H:i'))
    ->toggleable(),
```

---

## 5. 🟡 MEDIUM — Activity Log Viewer di Filament

Setelah migrasi ke `spatie/laravel-activitylog`, semua perubahan ke
`AssignmentSubmission` / `ExamSession` / `ExamSubmission` ter-log dengan
causer. Tapi belum ada viewer di Filament.

**Use case guru**:
- "Submission Andi tiba-tiba berubah skornya dari 80 jadi 70 — siapa yang ubah?"
- Compliance / audit trail.

**Fix** — tambah Infolist section di Edit/View action:

```php
use Filament\Forms\Components\Placeholder;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\HtmlString;

Section::make('Riwayat Aktivitas')
    ->icon('heroicon-o-clock')
    ->collapsed()
    ->schema([
        Placeholder::make('activity_log')
            ->label('')
            ->content(function ($record) {
                $activities = Activity::query()
                    ->where('subject_type', $record->getMorphClass())
                    ->where('subject_id', $record->getKey())
                    ->with('causer')
                    ->latest()
                    ->limit(20)
                    ->get();

                if ($activities->isEmpty()) {
                    return new HtmlString('<em class="text-gray-500">Belum ada aktivitas.</em>');
                }

                $html = '<ul class="space-y-2">';
                foreach ($activities as $a) {
                    $when = $a->created_at->translatedFormat('d M Y · H:i');
                    $who = $a->causer ? e($a->causer->name ?? $a->causer->full_name ?? 'Anonim') : 'Sistem';
                    $changes = $a->attribute_changes?->get('attributes') ?? [];
                    $changeList = collect($changes)->map(fn ($v, $k) => "$k → ".(is_scalar($v) ? $v : json_encode($v)))->implode(', ');
                    $html .= '<li class="flex gap-3 p-2 border rounded-md dark:border-gray-700">';
                    $html .= '<div class="flex-1"><div class="text-sm font-medium">'.ucfirst($a->event ?? '—').' oleh '.$who.'</div>';
                    $html .= '<div class="text-xs text-gray-500">'.e($changeList ?: '—').'</div></div>';
                    $html .= '<div class="text-xs text-gray-400">'.$when.'</div>';
                    $html .= '</li>';
                }
                $html .= '</ul>';

                return new HtmlString($html);
            }),
    ]);
```

Tambahkan ke section akhir form Edit AssignmentSubmissions & ExamSubmissions.

---

## 6. 🟢 Phase 5 — Backlog roadmap

Item-item berikut **sudah ada di roadmap [docs/07-student-dashboard-roadmap.md](07-student-dashboard-roadmap.md)
Phase 5** tapi belum dikerjakan. Saat ini bisa di-skip.

### 6.1 Dashboard Widgets (`/teacher` root)

Halaman dashboard Filament masih default (cuma `AccountWidget` + `FilamentInfoWidget`).

| Widget | File yang dibuat | Konten |
|--------|------------------|--------|
| `ClassroomStatsWidget` | `app/Filament/Widgets/ClassroomStatsWidget.php` | Total kelas yang diampu (scope per teacher) |
| `StudentStatsWidget` | `app/Filament/Widgets/StudentStatsWidget.php` | Total siswa di semua kelas guru |
| `PendingGradingWidget` | `app/Filament/Widgets/PendingGradingWidget.php` | Sub_missions yang `score IS NULL` (assignment + exam mode submission), session online_quiz dengan essay belum dinilai |
| `UpcomingExamWidget` | `app/Filament/Widgets/UpcomingExamWidget.php` | Tabel ujian minggu ini (`starts_at` antara now & +7d) |

Daftarkan ke panel di [TeacherPanelProvider.php](../app/Providers/Filament/TeacherPanelProvider.php):

```php
->widgets([
    Widgets\AccountWidget::class,
    \App\Filament\Widgets\ClassroomStatsWidget::class,
    \App\Filament\Widgets\StudentStatsWidget::class,
    \App\Filament\Widgets\PendingGradingWidget::class,
    \App\Filament\Widgets\UpcomingExamWidget::class,
]);
```

### 6.2 GradeResource (Rekap Nilai)

Custom Filament page (bukan resource CRUD), path `/teacher/grades`:

```php
// app/Filament/Pages/GradeRecap.php
namespace App\Filament\Pages;

use Filament\Pages\Page;

class GradeRecap extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $navigationGroup = 'Pengajaran';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Rekap Nilai';
    protected static string $view = 'filament.pages.grade-recap';
    // ... filter classroom + subject, render tabel dinamis
}
```

Spec UI:
- Filter: Select Classroom + Select Subject (cascading)
- Tabel: row = siswa, kolom dinamis = semua tugas + ujian di course itu
- Cell value = skor atau "—"; inline edit (PATCH ke `AssignmentSubmission::score` / `ExamSession::total_score` / `ExamSubmission::score`)
- Tombol Export Excel pakai [`maatwebsite/excel`](../composer.json) (sudah terinstal)

### 6.3 Notifications

Built-in Filament notifications + Laravel events:

```php
// app/Events/AssignmentSubmitted.php — sudah ada? cek
event(new AssignmentSubmitted($submission));

// app/Listeners/NotifyTeacherOfSubmission.php
Notification::make()
    ->title("Tugas baru dikumpulkan: {$submission->assignment->title}")
    ->body("Siswa: {$submission->student->full_name}")
    ->actions([
        Action::make('view')->url(AssignmentResource::getUrl('view', ['record' => $submission->assignment_id])),
    ])
    ->sendToDatabase($submission->assignment->material->classroomSubject->teacher->user);
```

Trigger di:
- `SubmitStudentAssignment::handle()` → `event(new AssignmentSubmitted($submission))`
- `SubmitExamSession::handle()` → `event(new ExamSessionSubmitted($session))`
- `SubmitExamSubmission::handle()` → `event(new ExamSubmissionReceived($submission))`

### 6.4 Policies via Filament Shield

Policies sudah ada di [app/Policies/](../app/Policies/) (generated by Shield).
Tapi belum ada verifikasi mereka di-wire ke `getEloquentQuery()` /
`canViewAny()` / dll secara konsisten.

**Aksi**: jalankan `php artisan shield:generate --all` untuk regenerate
policies + roles + permissions setelah ada model baru
(`AssignmentSubmission`, `ExamSession`, `ExamSubmission`, `ExamAnswer`).

---

## 7. Daftar Prioritas + Effort Estimate

| Prioritas | Item | Phase | Effort | File utama |
|-----------|------|-------|--------|------------|
| 🔴 P0 | §1.1 Fix format `options` MC (Filament ↔ student-side) | 4 | 30 min | `QuestionsRelationManager.php` |
| 🟠 P1 | §2.1 Enrich AssignmentSubmissionsRelationManager (view content/link/file saat grading) | 3 | 1 hr | `AssignmentResource/RelationManagers/SubmissionsRelationManager.php` |
| 🟠 P1 | §2.2 Enrich ExamSubmissionsRelationManager | 4 | 30 min (copy §2.1) | `ExamResource/RelationManagers/SubmissionsRelationManager.php` |
| 🟠 P1 | §2.4 Auto/manual regrade saat `correct_answer` berubah | 4 | 1 hr | `ExamQuestion.php` or `QuestionsRelationManager.php` |
| 🟡 P2 | §2.3 Kolom `correct_answer` di tabel soal | 4 | 5 min | `QuestionsRelationManager.php` |
| 🟡 P2 | §3.1 KaTeX render hook verifikasi | 2 | 30 min | `resources/views/components/katex-init.blade.php` |
| 🟡 P2 | §3.2 Reset password + login terakhir di StudentResource | 1 | 30 min | `StudentResource.php` |
| 🟡 P2 | §3.3 Reorder material (drag-and-drop) | 2 | 20 min | `CourseResource/RelationManagers/MaterialsRelationManager.php` |
| 🟡 P2 | §3.5 Submissions/sessions count column | 3+4 | 15 min | `AssignmentResource.php`, `ExamResource.php` |
| 🟡 P2 | §3.6 Filter deadline (overdue / this week) | 3 | 15 min | `AssignmentResource.php` |
| 🟡 P2 | §4.1 link_url column in submissions list | 3 | 10 min | `SubmissionsRelationManager.php` |
| 🟡 P2 | §4.2 graded_at column | 3+4 | 10 min | `SubmissionsRelationManager.php` (2x) |
| 🟡 P2 | §5 Activity log viewer | 3+4 carry-over | 1 hr | `SubmissionsRelationManager.php` |
| 🟢 P3 | §6.1 Dashboard widgets | 5 | 2–3 hr | `app/Filament/Widgets/*` |
| 🟢 P3 | §6.2 GradeResource (Rekap Nilai + Export Excel) | 5 | 3–4 hr | `app/Filament/Pages/GradeRecap.php` |
| 🟢 P3 | §6.3 Notifications | 5 | 1–2 hr | `app/Events/*`, `app/Listeners/*` |
| 🟢 P3 | §6.4 Policies via Shield regen | 5 | 30 min | `php artisan shield:generate --all` |

**Total estimasi P0 + P1**: ~3.5 jam.
**Total estimasi P2 (semua medium)**: ~4 jam.
**Total estimasi P3 (Phase 5 backlog)**: ~7–10 jam.

---

## 8. Urutan Pengerjaan yang Direkomendasikan

Karena banyak item saling melengkapi, urutan ini meminimalisir context switch:

### Sprint A (3.5 jam — fix bug + grading workflow)
1. **§1.1** Fix format `options` MC ← bug
2. **§2.4** Regrade saat `correct_answer` berubah ← langsung sesudah §1.1 karena masih di file yang sama
3. **§2.3** Kolom `correct_answer` di table soal ← masih di QuestionsRelationManager
4. **§2.1** Enrich AssignmentSubmissionsRelationManager ← grading lengkap
5. **§4.1** + **§4.2** Tambah link_url & graded_at columns ke list submission
6. **§2.2** Enrich ExamSubmissionsRelationManager ← copy paste dari §2.1
7. Smoke test: buat exam MC + submit + grade → cek end-to-end OK

### Sprint B (~2 jam — UX polish)
1. **§3.3** Reorder material
2. **§3.5** Submissions/sessions count
3. **§3.6** Filter deadline
4. **§3.2** Reset password + login terakhir
5. **§3.1** Verifikasi KaTeX render

### Sprint C (~1 jam — audit)
1. **§5** Activity log viewer

### Sprint D (Phase 5 — dikerjakan terpisah, ~7–10 jam)
1. **§6.4** Shield regen (jadi prerequisite untuk policies di widget/page baru)
2. **§6.1** Widgets
3. **§6.3** Notifications
4. **§6.2** GradeResource (paling kompleks, taruh terakhir)

---

## 9. Testing & Acceptance Criteria

Setelah Sprint A + B selesai, end-to-end test berikut **harus pass**:

### Skenario 1 — MC Auto-grade Flow
1. Guru: buat Exam mode `online_quiz` → tambah 3 soal MC via Filament
2. Siswa: kerjakan ujian → pilih opsi B di semua soal → submit
3. Cek student dashboard: opsi muncul sebagai teks (bukan `[object Object]`)
4. Cek hasil ujian: skor = jumlah soal × bobot dimana B = correct_answer

### Skenario 2 — Assignment Grading Flow
1. Siswa: submit tugas dengan content HTML + link + file (.pdf)
2. Guru: buka AssignmentResource → klik submission → klik "Beri Nilai"
3. Modal tampilkan:
   - Content sebagai HTML (formatted, bukan raw `<p>`)
   - Tautan referensi sebagai link clickable (new tab)
   - File PDF dengan tombol Download
4. Input score = 80, feedback = "Good"
5. Save → `assignment_submissions.graded_at` auto-set, `score` = 80
6. Siswa refresh assignment detail → status `graded`, timeline ada "Dinilai oleh guru"
7. Cek `activity_log`: ada row `event=updated`, `causer_id=` user guru

### Skenario 3 — Exam Submission Grading Flow
Sama dengan Skenario 2 tapi via ExamResource > SubmissionsRelationManager.

### Skenario 4 — Regrade after Correct Answer Change
1. Buat MC soal: opsi A–D, kunci = B
2. Siswa submit pilih B → dapat skor full
3. Guru sadar kunci salah, edit jadi A → save
4. (Option A) Auto trigger regrade
   (Option B) Klik "Hitung Ulang Nilai Semua Siswa"
5. Cek: siswa yang pilih B sekarang dapat skor 0; siswa yang pilih A dapat full

### Skenario 5 — Cross-guard
1. Siswa login → akses `/teacher` → redirect ke `student.dashboard`
2. Siswa login → akses `/teacher/students` → redirect ke `student.dashboard`
3. Guru login → akses `/student/dashboard` → redirect ke `/teacher`
4. Guest → akses `/teacher` → redirect ke `/teacher/login`
5. Guest → akses `/` → redirect ke `student.login`

---

## 10. Risiko & Catatan

| Risiko | Mitigasi |
|--------|----------|
| Migration format `options` lama → baru bisa break exam yang sudah pernah dibuat lewat Filament (jika ada) | Cek dulu: `select id, options from exam_questions where options::text like '[%';` → kalau ada, jalankan migration konversi assoc. Saat ini hanya data seeder yang format-nya `{A:..}`, jadi tidak ada konversi diperlukan. |
| RichEditor → Trix → KaTeX render: tag HTML guru bisa tidak compatible | DOMPurify config di student-side sudah strip tag berbahaya. Tag standar (h1, p, ul, code) tetap render. Math `$...$` di-handle KaTeX after sanitize. |
| Filament asset cache tidak ke-refresh setelah perubahan render hook | `php artisan filament:upgrade` + `php artisan optimize:clear` setiap kali ubah PanelProvider |
| Bulk-grading (Filament default tidak support) | Phase 5 GradeResource akan handle ini lewat inline edit |
| Soft-delete submission lalu siswa submit ulang | Sudah ditangani di `SubmitStudentAssignment` + `SubmitExamSubmission` dengan `withTrashed()->restore()` pattern. Guru tidak perlu khawatir. |

---

## Lampiran A — Snippet Lengkap §2.1 (siap copy-paste)

`app/Filament/Resources/AssignmentResource/RelationManagers/SubmissionsRelationManager.php` setelah enrichment:

```php
<?php

namespace App\Filament\Resources\AssignmentResource\RelationManagers;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';
    protected static ?string $title = 'Pengumpulan Siswa';
    protected static ?string $modelLabel = 'Pengumpulan';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Jawaban Siswa')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Placeholder::make('submitted_at_view')
                        ->label('Dikumpulkan')
                        ->content(fn ($record) => $record?->submitted_at
                            ? $record->submitted_at->translatedFormat('l, d F Y · H:i')
                            : '—'),

                    Placeholder::make('content_view')
                        ->label('Esai / Jawaban Teks')
                        ->content(fn ($record) => new HtmlString(
                            '<div class="prose dark:prose-invert max-w-none">'
                            .($record?->content ?: '<em class="text-gray-500">Tidak ada teks</em>')
                            .'</div>'
                        ))
                        ->columnSpanFull(),

                    Placeholder::make('link_view')
                        ->label('Tautan Referensi')
                        ->content(fn ($record) => $record?->link_url
                            ? new HtmlString(
                                '<a href="'.e($record->link_url).'" target="_blank" rel="noopener" '
                                .'class="text-primary-600 hover:text-primary-700 underline break-all">'
                                .e($record->link_url).'</a>'
                            )
                            : '—')
                        ->visible(fn ($record) => filled($record?->link_url))
                        ->columnSpanFull(),

                    SpatieMediaLibraryFileUpload::make('submission_files')
                        ->collection('submission_files')
                        ->label('Lampiran Siswa')
                        ->disabled()
                        ->downloadable()
                        ->openable()
                        ->columnSpanFull()
                        ->visible(fn ($record) => $record?->getMedia('submission_files')->isNotEmpty()),
                ])
                ->collapsible(),

            Section::make('Penilaian')
                ->icon('heroicon-o-star')
                ->schema([
                    TextInput::make('score')
                        ->label('Nilai')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(fn () => $this->getOwnerRecord()->max_score)
                        ->suffix(fn () => '/ '.$this->getOwnerRecord()->max_score),

                    Textarea::make('feedback')
                        ->label('Feedback / Catatan untuk Siswa')
                        ->rows(4)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('student.full_name')
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('student.nisn')
                    ->label('NISN')
                    ->toggleable(),

                TextColumn::make('submitted_at')
                    ->label('Waktu Kumpul')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Belum mengumpulkan')
                    ->sortable(),

                IconColumn::make('submitted_at')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->getStateUsing(fn ($record) => filled($record->submitted_at)),

                TextColumn::make('link_url')
                    ->label('Link')
                    ->limit(30)
                    ->url(fn ($state) => $state, true)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('score')
                    ->label('Nilai')
                    ->alignCenter()
                    ->placeholder('—'),

                TextColumn::make('graded_at')
                    ->label('Dinilai')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Belum dinilai')
                    ->since()
                    ->tooltip(fn ($record) => $record->graded_at?->format('d F Y H:i'))
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('submitted_at')
                    ->label('Status Pengumpulan')
                    ->nullable()
                    ->trueLabel('Sudah mengumpulkan')
                    ->falseLabel('Belum mengumpulkan'),

                TernaryFilter::make('score')
                    ->label('Sudah Dinilai')
                    ->nullable()
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('score'),
                        false: fn ($query) => $query->whereNull('score'),
                    ),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()->label('Beri Nilai'),
                ]),
            ]);
    }
}
```

---

## Lampiran B — Glossary

| Istilah | Penjelasan |
|---------|------------|
| Guard `student` | Custom Laravel auth guard untuk siswa (NISN + password) |
| Guard `web` | Default Laravel guard untuk guru/admin |
| `ClassroomSubject` | Pivot: Classroom + Subject + Teacher + Semester + Year. Dipakai sebagai "Course". |
| `Material` | Blok pembelajaran milik ClassroomSubject. Punya content, files, link, plus child Assignments & Exams. |
| `Assignment` | Tugas milik Material. Submission via essay + file + link (now). |
| `Exam` mode `online_quiz` | Soal interaktif, timer, auto-grade MC + short answer. |
| `Exam` mode `submission` | Mirip Assignment — kumpul text + file + link. |
| `ExamSession` | Instance siswa mengerjakan exam online_quiz. Punya started_at, submitted_at, total_score. |
| `ExamAnswer` | Jawaban siswa per soal di session. Punya answer, score, feedback. |
| `ExamSubmission` | Submission siswa untuk exam mode `submission` (bukan ExamSession). |
| Spatie Activitylog | Audit trail untuk perubahan model — dipakai untuk timeline read-only di student dashboard & Filament. |
| CauserResolver | Configurable resolver di Activitylog yang menentukan siapa "actor" tiap log. Di-config untuk multi-guard. |
