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
        $columns = $course ? $this->getDynamicColumns() : [];
    @endphp

    @if ($course)
        <x-filament::section>
            <x-slot name="heading">
                {{ $course->classroom?->name }} · {{ $course->subject?->name }}
            </x-slot>

            <x-slot name="description">
                TA {{ $course->academic_year ?? '—' }} · Semester {{ $course->semester ?? '—' }} ·
                Guru: {{ $course->teacher?->full_name ?? '—' }} ·
                {{ $this->getStudentCount() }} siswa · {{ count($columns) }} item nilai
            </x-slot>

            @if (count($columns) === 0)
                <p class="text-sm italic text-gray-500 dark:text-gray-400">Belum ada tugas atau ujian di course ini.</p>
            @else
                {{ $this->table }}

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Export Excel mencakup <strong>semua</strong> siswa di kelas (tidak terbatas halaman saat ini).
                </p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
