<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Material;
use Illuminate\Database\Seeder;

class AssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $materials = Material::where('is_published', true)
            ->whereHas('classroomSubject')
            ->get();

        foreach ($materials as $material) {
            // Skip material yang sengaja jadi "blok ujian" — tidak perlu tugas
            if (str_contains(strtolower($material->title), 'sumber belajar')) {
                continue;
            }

            Assignment::firstOrCreate(
                ['material_id' => $material->id, 'title' => "Latihan: {$material->title}"],
                [
                    'description' => '<p>Kerjakan latihan berikut berdasarkan materi yang sudah dibaca. Tuliskan jawabanmu di kolom esai, atau lampirkan file PDF/DOCX jika perlu.</p><ol><li>Sebutkan minimal 3 poin penting dari materi.</li><li>Berikan satu contoh penerapan di kehidupan sehari-hari.</li><li>Apa pertanyaan yang masih kamu miliki tentang materi ini?</li></ol>',
                    'deadline' => now()->addDays(7),
                    'max_score' => 100,
                    'allowed_file_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
                    'max_file_size_mb' => 10,
                    'is_published' => true,
                    'available_from' => now()->subDay(),
                ]
            );

            // Tugas kedua khusus untuk material berisi rumus, supaya bisa di-test scenario "submission essay panjang"
            if (str_contains(strtolower($material->title), 'rumus')) {
                Assignment::firstOrCreate(
                    ['material_id' => $material->id, 'title' => 'Soal Aplikasi Rumus'],
                    [
                        'description' => '<p>Selesaikan persamaan kuadrat berikut dan jelaskan langkah-langkahnya:</p><ul><li>$x^2 - 7x + 12 = 0$</li><li>$2x^2 + 5x - 3 = 0$</li></ul><p>Tunjukkan langkah lengkap di kolom esai.</p>',
                        'deadline' => now()->addDays(3),
                        'max_score' => 100,
                        'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                        'max_file_size_mb' => 5,
                        'is_published' => true,
                        'available_from' => now()->subDay(),
                    ]
                );
            }

            // Tugas terlambat (deadline lewat) — untuk uji state "overdue"
            if (str_contains(strtolower($material->title), 'pengantar')) {
                Assignment::firstOrCreate(
                    ['material_id' => $material->id, 'title' => 'Kuis Pendahuluan (sudah lewat)'],
                    [
                        'description' => '<p>Tugas singkat untuk menguji pemahaman awal. Tugas ini sudah lewat deadline — digunakan untuk demo tampilan "terlambat".</p>',
                        'deadline' => now()->subDays(2),
                        'max_score' => 50,
                        'allowed_file_types' => ['pdf', 'doc', 'docx'],
                        'max_file_size_mb' => 5,
                        'is_published' => true,
                        'available_from' => now()->subWeek(),
                    ]
                );
            }
        }
    }
}
