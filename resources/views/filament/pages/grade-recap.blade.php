<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filter</x-slot>
        <x-slot name="description">Pilih kombinasi kelas + mata pelajaran untuk menampilkan rekap nilai siswa.</x-slot>

        <x-filament-panels::form>
            {{ $this->form }}
        </x-filament-panels::form>
    </x-filament::section>

    @php
        $course = $this->getCourse();
        $students = $course ? $this->getStudents() : collect();
        $columns = $course ? $this->getDynamicColumns() : [];
        $grades = $course ? $this->getGradeMatrix() : [];
    @endphp

    @if (! $course)
        <x-filament::section>
            <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5" />
                <span>Belum ada course terpilih. Pilih dulu di filter di atas.</span>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">
                {{ $course->classroom?->name }} · {{ $course->subject?->name }}
            </x-slot>

            <x-slot name="description">
                TA {{ $course->academic_year ?? '—' }} · Semester {{ $course->semester ?? '—' }} ·
                Guru: {{ $course->teacher?->full_name ?? '—' }} ·
                {{ $students->count() }} siswa · {{ count($columns) }} item nilai
            </x-slot>

            @if (count($columns) === 0)
                <p class="text-sm italic text-gray-500 dark:text-gray-400">Belum ada tugas atau ujian di course ini.</p>
            @elseif ($students->isEmpty())
                <p class="text-sm italic text-gray-500 dark:text-gray-400">Belum ada siswa terdaftar di kelas ini.</p>
            @else
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm border-collapse">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 border-b border-gray-200 dark:border-white/10 whitespace-nowrap">No</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 border-b border-gray-200 dark:border-white/10 whitespace-nowrap">NISN</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 border-b border-gray-200 dark:border-white/10 whitespace-nowrap min-w-[200px]">Nama Siswa</th>
                                @foreach ($columns as $col)
                                    <th class="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-200 border-b border-l border-gray-200 dark:border-white/10 min-w-[140px]">
                                        <div class="font-medium truncate max-w-[180px] mx-auto" title="{{ $col['label'] }}">
                                            {{ $col['label'] }}
                                        </div>
                                        <div class="text-[11px] font-normal text-gray-500 dark:text-gray-400 mt-0.5">
                                            max {{ rtrim(rtrim(number_format($col['max_score'], 2, '.', ''), '0'), '.') }}
                                            @if ($col['type'] === 'exam_quiz')
                                                · quiz
                                            @elseif ($col['type'] === 'exam_submission')
                                                · ujian
                                            @endif
                                        </div>
                                    </th>
                                @endforeach
                                <th class="px-3 py-2 text-center font-semibold border-b border-l border-gray-200 dark:border-white/10 bg-primary-50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-300 whitespace-nowrap">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($students as $idx => $student)
                                @php
                                    $total = 0.0;
                                    $hasScore = false;
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $idx + 1 }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400 font-mono text-xs">{{ $student->nisn ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100 font-medium whitespace-nowrap">{{ $student->full_name }}</td>
                                    @foreach ($columns as $col)
                                        @php
                                            $score = $grades[$student->id][$col['key']] ?? null;
                                            if ($score !== null) {
                                                $total += $score;
                                                $hasScore = true;
                                            }
                                        @endphp
                                        <td class="px-3 py-2 text-center border-l border-gray-100 dark:border-white/5">
                                            @if ($score === null)
                                                <span class="text-gray-400 dark:text-gray-600">—</span>
                                            @else
                                                <span class="text-gray-900 dark:text-gray-100">
                                                    {{ rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.') }}
                                                </span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2 text-center border-l border-gray-100 dark:border-white/5 bg-primary-50/50 dark:bg-primary-500/5 font-semibold">
                                        @if ($hasScore)
                                            <span class="text-primary-700 dark:text-primary-300">
                                                {{ rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.') }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-600">—</span>
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
</x-filament-panels::page>
