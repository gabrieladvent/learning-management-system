@php
    use App\Support\LearningProgressMetrics;

    $tabs = [
        'identitas'   => ['label' => 'Identitas', 'icon' => 'heroicon-o-identification'],
        'material'    => ['label' => 'Material', 'icon' => 'heroicon-o-book-open'],
        'tugas'       => ['label' => 'Tugas', 'icon' => 'heroicon-o-clipboard-document-list'],
        'ujian'       => ['label' => 'Ujian', 'icon' => 'heroicon-o-academic-cap'],
        'penelitian'  => ['label' => 'Data Penelitian', 'icon' => 'heroicon-o-beaker'],
    ];

    $riskStatus = $research['risk_status'] ?? 'aman';
    $riskColor = LearningProgressMetrics::riskBadgeColor($riskStatus);
    $riskLabel = LearningProgressMetrics::riskLabel($riskStatus);

    $statusBadge = function (string $status): array {
        return match ($status) {
            'dinilai'             => ['Dinilai', 'success'],
            'submitted'           => ['Menunggu Nilai', 'info'],
            'overdue'             => ['Terlambat / Belum', 'danger'],
            'sedang_mengerjakan'  => ['Sedang Mengerjakan', 'warning'],
            default               => ['Belum Dikerjakan', 'gray'],
        };
    };
@endphp

<x-filament-panels::page>
    {{-- Header ringkas siswa --}}
    <x-filament::section>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="text-xl font-bold text-gray-950 dark:text-white">
                    {{ $studentRecord->full_name }}
                </div>
                <div class="mt-1 text-sm text-gray-500">
                    NISN {{ $studentRecord->nisn ?? '—' }} · {{ $courseRecord->classroom?->name }} ·
                    {{ $courseRecord->subject?->name }}
                </div>
            </div>
            <x-filament::badge :color="$riskColor" size="lg">
                Status: {{ $riskLabel }}
            </x-filament::badge>
        </div>
    </x-filament::section>

    {{-- Tab nav --}}
    <x-filament::tabs>
        @foreach ($tabs as $key => $tab)
            <x-filament::tabs.item
                :active="$activeTab === $key"
                :icon="$tab['icon']"
                wire:click="setTab('{{ $key }}')"
            >
                {{ $tab['label'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{-- ============ TAB: IDENTITAS ============ --}}
    @if ($activeTab === 'identitas')
        @php
            $identity = [
                ['Nama Lengkap', $studentRecord->full_name],
                ['NISN', $studentRecord->nisn ?? '—'],
                ['Kelas', $courseRecord->classroom?->name ?? '—'],
                ['Mata Pelajaran', $courseRecord->subject?->name ?? '—'],
                ['Guru Pengampu', $courseRecord->teacher?->full_name ?? '—'],
                ['Tahun Ajaran', ($courseRecord->academic_year ?? '—') . ' · Sem ' . $courseRecord->semester],
            ];
            $summary = [
                ['Total Durasi Material', LearningProgressMetrics::formatDuration((int) ($research['material_active_seconds_total'] ?? 0)), 'heroicon-o-clock', 'text-sky-600 dark:text-sky-400'],
                ['Material Selesai', round(($research['material_completion_rate'] ?? 0) * 100) . '%', 'heroicon-o-check-circle', 'text-emerald-600 dark:text-emerald-400'],
                ['Engagement Harian', LearningProgressMetrics::formatDuration((int) ($research['daily_engagement_seconds'] ?? 0)), 'heroicon-o-fire', 'text-amber-600 dark:text-amber-400'],
            ];
        @endphp

        <x-filament::section heading="Identitas">
            <x-filament::grid :default="1" :sm="2" :md="3" class="gap-x-8 gap-y-4">
                @foreach ($identity as [$label, $value])
                    <div class="flex flex-col gap-0.5 border-b border-gray-100 pb-3 dark:border-white/5">
                        <span class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $value }}</span>
                    </div>
                @endforeach
            </x-filament::grid>
        </x-filament::section>

        <x-filament::section heading="Ringkasan Belajar">
            <x-filament::grid :default="1" :md="3" class="gap-4">
                @foreach ($summary as [$label, $value, $icon, $color])
                    <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 dark:bg-white/10">
                            <x-filament::icon :icon="$icon" @class(['h-5 w-5', $color]) />
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-xs font-medium text-gray-500">{{ $label }}</div>
                            <div class="text-xl font-bold text-gray-950 dark:text-white">{{ $value }}</div>
                        </div>
                    </div>
                @endforeach
            </x-filament::grid>
        </x-filament::section>
    @endif

    {{-- ============ TAB: MATERIAL ============ --}}
    @if ($activeTab === 'material')
        <x-filament::section heading="Durasi Akses Material">
            <x-slot name="description">
                Durasi = total waktu aktif siswa di halaman material. "Selesai" dihitung sesuai aturan
                per tipe material (teks: rasio waktu baca, file: download, link: durasi minimal).
            </x-slot>

            @if (count($materials) === 0)
                <p class="text-sm text-gray-500">Belum ada material terbit di mapel ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700">
                                <th class="px-3 py-2 font-medium">Judul</th>
                                <th class="px-3 py-2 font-medium">Tipe</th>
                                <th class="px-3 py-2 font-medium text-center">Durasi Akses</th>
                                <th class="px-3 py-2 font-medium">Terakhir Akses</th>
                                <th class="px-3 py-2 font-medium text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($materials as $m)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $m['title'] }}</div>
                                        @if ($m['topic'])
                                            <div class="text-xs text-gray-500">{{ $m['topic'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <x-filament::badge color="gray" size="sm">{{ ucfirst($m['type']) }}</x-filament::badge>
                                    </td>
                                    <td class="px-3 py-2 text-center">{{ LearningProgressMetrics::formatDuration((int) $m['duration_seconds']) }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                        {{ $m['last_accessed'] ? \Illuminate\Support\Carbon::parse($m['last_accessed'])->setTimezone('Asia/Jakarta')->format('d M Y H:i') : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @if ($m['completed'] && $m['completion_basis'] === 'download')
                                            <x-filament::badge color="success" size="sm"
                                                icon="heroicon-o-arrow-down-tray"
                                                tooltip="Dianggap selesai karena file sudah diunduh. Pembacaan offline tidak terukur — durasi di halaman bisa 0.">
                                                Selesai · diunduh
                                            </x-filament::badge>
                                        @elseif ($m['completed'])
                                            <x-filament::badge color="success" size="sm"
                                                tooltip="Selesai berdasarkan durasi baca yang memenuhi ambang.">
                                                Selesai
                                            </x-filament::badge>
                                        @else
                                            <x-filament::badge color="gray" size="sm">Belum</x-filament::badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- ============ TAB: TUGAS ============ --}}
    @if ($activeTab === 'tugas')
        <x-filament::section heading="Progres Pengerjaan Tugas">
            @if (count($assignments) === 0)
                <p class="text-sm text-gray-500">Belum ada tugas terbit di mapel ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700">
                                <th class="px-3 py-2 font-medium">Judul</th>
                                <th class="px-3 py-2 font-medium">Deadline</th>
                                <th class="px-3 py-2 font-medium">Dikumpulkan</th>
                                <th class="px-3 py-2 font-medium text-center">Nilai</th>
                                <th class="px-3 py-2 font-medium text-center">Waktu di Halaman</th>
                                <th class="px-3 py-2 font-medium text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assignments as $a)
                                @php [$label, $color] = $statusBadge($a['status']); @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $a['title'] }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $a['deadline'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                        {{ $a['submitted_at'] ?? '—' }}
                                        @if ($a['is_late'])
                                            <x-filament::badge color="warning" size="sm">Terlambat</x-filament::badge>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        {{ $a['score'] !== null ? rtrim(rtrim(number_format($a['score'], 2), '0'), '.') . ' / ' . rtrim(rtrim(number_format($a['max_score'] ?? 0, 2), '0'), '.') : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-center">{{ LearningProgressMetrics::formatDuration((int) $a['time_on_page_seconds']) }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <x-filament::badge :color="$color" size="sm">{{ $label }}</x-filament::badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- ============ TAB: UJIAN ============ --}}
    @if ($activeTab === 'ujian')
        <x-filament::section heading="Progres Pengerjaan Ujian">
            <x-slot name="description">
                "Durasi Mengerjakan" = waktu antara mulai dan submit ujian. "Waktu di Detail" = waktu di
                halaman detail ujian (sebelum mulai / sesudah submit), terpisah dari waktu mengerjakan.
            </x-slot>

            @if (count($exams) === 0)
                <p class="text-sm text-gray-500">Belum ada ujian terbit di mapel ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700">
                                <th class="px-3 py-2 font-medium">Judul</th>
                                <th class="px-3 py-2 font-medium">Mulai</th>
                                <th class="px-3 py-2 font-medium">Submit</th>
                                <th class="px-3 py-2 font-medium text-center">Durasi Mengerjakan</th>
                                <th class="px-3 py-2 font-medium text-center">Waktu di Detail</th>
                                <th class="px-3 py-2 font-medium text-center">Nilai</th>
                                <th class="px-3 py-2 font-medium text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($exams as $e)
                                @php [$label, $color] = $statusBadge($e['status']); @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $e['title'] }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $e['started_at'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $e['submitted_at'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-center">{{ $e['duration_seconds'] > 0 ? LearningProgressMetrics::formatDuration((int) $e['duration_seconds']) : '—' }}</td>
                                    <td class="px-3 py-2 text-center">{{ LearningProgressMetrics::formatDuration((int) $e['detail_seconds']) }}</td>
                                    <td class="px-3 py-2 text-center">
                                        {{ $e['score'] !== null ? rtrim(rtrim(number_format($e['score'], 2), '0'), '.') . ' / ' . rtrim(rtrim(number_format($e['max_score'] ?? 0, 2), '0'), '.') : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <x-filament::badge :color="$color" size="sm">{{ $label }}</x-filament::badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- ============ TAB: DATA PENELITIAN ============ --}}
    @if ($activeTab === 'penelitian')
        <x-filament::section heading="Variabel Data Penelitian">
            <x-slot name="description">
                Definisi operasional sesuai dokumen riset (§13.2). Semua nilai derived dari tabel
                learning_progress, assignment_submissions, dan exam_sessions.
            </x-slot>

            @php
                $vars = [
                    ['material_active_seconds_total', 'Total detik aktif di material', $research['material_active_seconds_total'] ?? 0, 'detik'],
                    ['material_completion_rate', 'Rasio material selesai', $research['material_completion_rate'] ?? 0, 'rasio 0–1'],
                    ['assignment_submit_rate', 'Rasio tugas dikumpulkan', $research['assignment_submit_rate'] ?? 0, 'rasio 0–1'],
                    ['assignment_late_rate', 'Rasio tugas terlambat', $research['assignment_late_rate'] ?? 0, 'rasio 0–1'],
                    ['exam_attempt_rate', 'Rasio ujian dikerjakan', $research['exam_attempt_rate'] ?? 0, 'rasio 0–1'],
                    ['exam_avg_score', 'Rata-rata nilai ujian', $research['exam_avg_score'] ?? null, 'skala max_score'],
                    ['assignment_avg_score', 'Rata-rata nilai tugas', $research['assignment_avg_score'] ?? null, 'skala max_score'],
                    ['daily_engagement_seconds', 'Engagement harian (rata-rata)', $research['daily_engagement_seconds'] ?? 0, 'detik/hari'],
                    ['risk_status', 'Status risiko', $riskLabel, 'nominal'],
                ];
            @endphp

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700">
                            <th class="px-3 py-2 font-medium">Variabel</th>
                            <th class="px-3 py-2 font-medium">Definisi</th>
                            <th class="px-3 py-2 font-medium text-center">Nilai</th>
                            <th class="px-3 py-2 font-medium">Satuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vars as [$key, $def, $value, $unit])
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2 font-mono text-xs text-gray-900 dark:text-gray-100">{{ $key }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $def }}</td>
                                <td class="px-3 py-2 text-center font-semibold">{{ $value === null ? '—' : $value }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $unit }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-4 text-xs text-gray-500">
                Untuk export lengkap (4 sheet + manifest, mode raw/anonim), gunakan tombol
                <strong>Export ke Excel</strong> di halaman list siswa (Level 2).
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
