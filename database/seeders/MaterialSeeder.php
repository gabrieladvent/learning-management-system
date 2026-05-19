<?php

namespace Database\Seeders;

use App\Models\ClassroomSubject;
use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        $courses = ClassroomSubject::with('subject')->get();

        foreach ($courses as $course) {
            $subjectName = $course->subject?->name ?? 'Mata Pelajaran';

            Material::firstOrCreate(
                ['classroom_subject_id' => $course->id, 'title' => "Pengantar {$subjectName}"],
                [
                    'topic' => 'Bab 1 — Dasar',
                    'description' => "Materi pembuka untuk mata pelajaran {$subjectName}.",
                    'content' => $this->intro($subjectName),
                    'is_published' => true,
                    'available_from' => now()->subDay(),
                ]
            );

            Material::firstOrCreate(
                ['classroom_subject_id' => $course->id, 'title' => 'Contoh Rumus & Persamaan'],
                [
                    'topic' => 'Bab 2 — Rumus',
                    'description' => 'Materi singkat yang berisi rumus matematika untuk uji render KaTeX.',
                    'content' => $this->withMath(),
                    'is_published' => true,
                    'available_from' => now()->subDay(),
                ]
            );

            Material::firstOrCreate(
                ['classroom_subject_id' => $course->id, 'title' => 'Sumber Belajar Tambahan'],
                [
                    'topic' => 'Referensi',
                    'description' => 'Tautan eksternal untuk memperdalam materi.',
                    'link_url' => 'https://id.khanacademy.org',
                    'is_published' => true,
                    'available_from' => now()->subDay(),
                ]
            );

            Material::firstOrCreate(
                ['classroom_subject_id' => $course->id, 'title' => 'Draf Materi (Belum Terbit)'],
                [
                    'topic' => 'Draf',
                    'description' => 'Materi yang belum dipublikasikan — tidak boleh terlihat siswa.',
                    'content' => '<p>Draft only.</p>',
                    'is_published' => false,
                ]
            );
        }
    }

    private function intro(string $subjectName): string
    {
        return <<<HTML
<h2>Selamat datang di {$subjectName}</h2>
<p>Pada bab ini kita akan mempelajari konsep dasar yang menjadi fondasi untuk materi berikutnya.</p>
<ul>
    <li>Memahami terminologi inti.</li>
    <li>Mengenal contoh penerapan dalam kehidupan sehari-hari.</li>
    <li>Latihan ringan untuk pemanasan.</li>
</ul>
<p><strong>Tujuan pembelajaran:</strong> setelah membaca materi ini kamu akan mampu menjelaskan konsep awal dan memberikan satu contoh penerapan.</p>
HTML;
    }

    private function withMath(): string
    {
        return <<<'HTML'
<h2>Rumus Penting</h2>
<p>Persamaan kuadrat berbentuk \(ax^2 + bx + c = 0\) dapat diselesaikan dengan rumus:</p>
<p>$$x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}$$</p>
<p>Sebagai contoh, untuk persamaan $x^2 - 5x + 6 = 0$ kita dapatkan akar-akarnya $x_1 = 2$ dan $x_2 = 3$.</p>
<h3>Teorema Pythagoras</h3>
<p>Pada segitiga siku-siku berlaku $a^2 + b^2 = c^2$ di mana $c$ adalah sisi miring.</p>
HTML;
    }
}
