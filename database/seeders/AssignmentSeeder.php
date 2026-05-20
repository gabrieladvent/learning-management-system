<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Material;
use Illuminate\Database\Seeder;

class AssignmentSeeder extends Seeder
{
    /**
     * Buat dataset tugas yang deterministik & tidak bergantung pada string title.
     *
     * Strategi:
     *  - Iterate material per course (sort by `order`).
     *  - Material pertama (`order = 1`) per course → tugas dengan deadline lampau
     *    untuk demo state "overdue".
     *  - Material order 2+ → tugas default (deadline 7 hari ke depan).
     *  - Material yang sudah berisi exam saja (lewat `MaterialSeeder` punya
     *    `link_url` tanpa konten) tetap dapat satu tugas — biar Material Detail
     *    page selalu punya minimal satu aktivitas.
     */
    public function run(): void
    {
        $materials = Material::query()
            ->where('is_published', true)
            ->whereHas('classroomSubject')
            ->orderBy('classroom_subject_id')
            ->orderBy('order')
            ->get()
            ->groupBy('classroom_subject_id');

        foreach ($materials as $courseMaterials) {
            foreach ($courseMaterials as $idx => $material) {
                $isFirst = $idx === 0;

                Assignment::firstOrCreate(
                    ['material_id' => $material->id, 'title' => "Latihan: {$material->title}"],
                    [
                        'description' => $this->essayDescription(),
                        'deadline' => $isFirst ? now()->subDays(2) : now()->addDays(7),
                        'max_score' => $isFirst ? 50 : 100,
                        'allowed_file_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
                        'max_file_size_mb' => 10,
                        'is_published' => true,
                        'available_from' => now()->subWeek(),
                    ]
                );

                // Material order 2 (kalau ada) → tugas kedua untuk demo skenario
                // "siswa punya 2 tugas di material yang sama".
                if ($idx === 1) {
                    Assignment::firstOrCreate(
                        ['material_id' => $material->id, 'title' => "Soal Aplikasi: {$material->title}"],
                        [
                            'description' => $this->mathDescription(),
                            'deadline' => now()->addDays(3),
                            'max_score' => 100,
                            'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                            'max_file_size_mb' => 5,
                            'is_published' => true,
                            'available_from' => now()->subDay(),
                        ]
                    );
                }
            }
        }
    }

    private function essayDescription(): string
    {
        return '<p>Kerjakan latihan berikut berdasarkan materi yang sudah dibaca. Tuliskan jawabanmu di kolom esai, atau lampirkan file PDF/DOCX jika perlu.</p>'
            .'<ol><li>Sebutkan minimal 3 poin penting dari materi.</li>'
            .'<li>Berikan satu contoh penerapan di kehidupan sehari-hari.</li>'
            .'<li>Apa pertanyaan yang masih kamu miliki tentang materi ini?</li></ol>';
    }

    private function mathDescription(): string
    {
        return '<p>Selesaikan persamaan kuadrat berikut dan jelaskan langkah-langkahnya:</p>'
            .'<ul><li>$x^2 - 7x + 12 = 0$</li><li>$2x^2 + 5x - 3 = 0$</li></ul>'
            .'<p>Tunjukkan langkah lengkap di kolom esai.</p>';
    }
}
