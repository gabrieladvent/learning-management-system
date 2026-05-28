@php
    use App\Support\LearningProgressMetrics;

    $stats = [
        ['label' => 'Total Materi', 'value' => $totalMaterials, 'icon' => 'heroicon-o-book-open', 'color' => 'text-sky-600 dark:text-sky-400'],
        ['label' => 'Total Tugas', 'value' => $totalAssignments, 'icon' => 'heroicon-o-clipboard-document-list', 'color' => 'text-amber-600 dark:text-amber-400'],
        ['label' => 'Total Ujian', 'value' => $totalExams, 'icon' => 'heroicon-o-academic-cap', 'color' => 'text-violet-600 dark:text-violet-400'],
        ['label' => 'Avg Durasi / Siswa', 'value' => LearningProgressMetrics::formatDuration((int) round($classAvgMaterialSeconds ?? 0)), 'icon' => 'heroicon-o-clock', 'color' => 'text-emerald-600 dark:text-emerald-400'],
    ];
@endphp

<x-filament-panels::page>
    {{-- Konteks ringkas (kelas/mapel/guru sudah di judul; ini detail tambahan) --}}
    <x-filament::section>
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-user" class="h-4 w-4 text-gray-400" />
                <span class="text-gray-500">Guru:</span>
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $record->teacher?->full_name ?? '—' }}</span>
            </div>
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-calendar" class="h-4 w-4 text-gray-400" />
                <span class="text-gray-500">Tahun Ajaran:</span>
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $record->academic_year }} · Semester {{ $record->semester }}</span>
            </div>
        </div>

        <x-filament::grid :default="2" :md="4" class="mt-4 gap-4">
            @foreach ($stats as $stat)
                <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 dark:bg-white/10">
                        <x-filament::icon :icon="$stat['icon']" @class(['h-5 w-5', $stat['color']]) />
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-gray-500">{{ $stat['label'] }}</div>
                        <div class="text-xl font-bold text-gray-950 dark:text-white">{{ $stat['value'] }}</div>
                    </div>
                </div>
            @endforeach
        </x-filament::grid>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Progres Per Siswa</x-slot>
        <x-slot name="description">
            Klik <strong>Lihat Detail</strong> untuk rincian per siswa, atau <strong>Export ke Excel</strong>
            di kanan atas tabel untuk data lengkap (4 sheet + manifest, mode raw / anonim).
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
