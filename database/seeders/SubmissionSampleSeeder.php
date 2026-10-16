<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use Illuminate\Database\Seeder;

/**
 * Sample pengumpulan siswa untuk memverifikasi render jawaban di panel guru
 * (App\Support\MathHtml). TIDAK dipanggil DatabaseSeeder — jalankan manual:
 *
 *   php artisan db:seed --class=SubmissionSampleSeeder
 *
 * Isinya sengaja campur supaya bisa dibandingkan langsung di satu halaman:
 *  - Matematika : LaTeX tanpa delimiter (kasus yang dulu tampil mentah),
 *                 jawaban pilihan ganda biasa, dan LaTeX yang sudah ber-$…$.
 *  - Mapel lain : esai prosa yang mengandung karakter "mirip math"
 *                 (persen, backslash path, tanda >, underscore) — harus tetap
 *                 tampil apa adanya, tidak boleh ikut dirender sebagai rumus.
 */
class SubmissionSampleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFor('Matematika', [
            'Ahmad Fauzi' => $this->mathLatexAnswer(),
            'Siti Nurhaliza' => $this->multipleChoiceAnswer(),
            'Budi Pratama' => $this->mathDelimitedAnswer(),
        ]);

        $this->seedFor('Bahasa Indonesia', [
            'Ahmad Fauzi' => $this->indonesianEssayAnswer(),
            'Siti Nurhaliza' => $this->proseWithTrickyCharacters(),
        ]);

        $this->seedFor('Bahasa Inggris', [
            'Ahmad Fauzi' => $this->englishEssayAnswer(),
        ]);
    }

    /**
     * Tempelkan jawaban ke tugas "Soal Aplikasi" milik mapel tertentu
     * (ambil yang pertama, deadline-nya masih ke depan).
     *
     * @param  array<string, string>  $answers  nama siswa => konten jawaban
     */
    private function seedFor(string $subjectName, array $answers): void
    {
        $assignment = Assignment::query()
            ->whereHas('material.classroomSubject.subject', fn ($q) => $q->where('name', $subjectName))
            ->where('title', 'like', 'Soal Aplikasi:%')
            ->orderBy('created_at')
            ->first();

        if (! $assignment) {
            $this->command?->warn("Lewat: tugas untuk mapel {$subjectName} tidak ditemukan.");

            return;
        }

        $classroom = $assignment->material?->classroomSubject?->classroom;

        foreach ($answers as $studentName => $content) {
            $student = Student::query()
                ->where('full_name', $studentName)
                ->when(
                    $classroom,
                    fn ($q) => $q->whereHas('classrooms', fn ($c) => $c->whereKey($classroom->getKey()))
                )
                ->first();

            if (! $student) {
                $this->command?->warn("Lewat: siswa {$studentName} tidak ditemukan.");

                continue;
            }

            $submittedAt = now()->subHours(random_int(1, 48));

            AssignmentSubmission::updateOrCreate(
                ['assignment_id' => $assignment->id, 'student_id' => $student->id],
                [
                    'content' => $content,
                    'submitted_at' => $submittedAt,
                    'is_late' => $assignment->deadline
                        ? $submittedAt->greaterThan($assignment->deadline)
                        : false,
                ],
            );
        }

        $this->command?->info(
            "{$subjectName}: ".count($answers)." pengumpulan → /teacher/assignments/{$assignment->id}/edit"
        );
    }

    /**
     * Kasus utama: siswa menempel LaTeX tanpa delimiter apa pun, dan Trix
     * memecah tiap baris jadi <div> sendiri (jadi blok align terpotong markup).
     */
    private function mathLatexAnswer(): string
    {
        $lines = [
            'Soal 1',
            '',
            'Diketahui sisi samping = 20 cm dan hipotenusa = 29 cm.',
            '',
            'a) Panjang sisi depan — gunakan Teorema Pythagoras:',
            '',
            '\begin{align*}',
            '\text{depan} &= \sqrt{29^2 - 20^2} \\\\',
            '&= \sqrt{841 - 400} \\\\',
            '&= \sqrt{441} = \boldsymbol{21 \text{ cm}}',
            '\end{align*}',
            '',
            'b) Keenam perbandingan trigonometri:',
            '',
            '\begin{align*}',
            '\sin \theta &= \frac{\text{depan}}{\text{miring}} = \boldsymbol{\frac{21}{29}} \\\\[6pt]',
            '\cos \theta &= \frac{\text{samping}}{\text{miring}} = \boldsymbol{\frac{20}{29}} \\\\[6pt]',
            '\tan \theta &= \frac{\text{depan}}{\text{samping}} = \boldsymbol{\frac{21}{20}} \\\\[6pt]',
            '\cot \theta &= \frac{\text{samping}}{\text{depan}} = \boldsymbol{\frac{20}{21}}',
            '\end{align*}',
            '',
            'Soal 2',
            '',
            'Diketahui: \sin \theta = \frac{9}{41} → depan = 9, miring = 41',
            '',
            '- Sisi samping = \sqrt{41^2 - 9^2} = \sqrt{1681 - 81} = \sqrt{1600} = \boldsymbol{40}',
            '',
            'Jadi \cos \theta = \frac{40}{41} \approx 0,976 dan \tan \theta = \frac{9}{40}.',
            '',
            '✅ \cos \theta lebih besar, karena \frac{40}{41} > \frac{9}{41}. Pembilang 40 lebih besar dari 9, penyebut sama.',
        ];

        return $this->asTrixHtml($lines);
    }

    /** Jawaban pilihan ganda — tidak ada math, harus tampil apa adanya. */
    private function multipleChoiceAnswer(): string
    {
        return $this->asTrixHtml(['1. C', '2. C', '3. B', '4. D', '5. B', '6. C', '7. B', '8. D']);
    }

    /** Siswa yang memakai delimiter $…$ dari tombol "Editor Formula". */
    private function mathDelimitedAnswer(): string
    {
        return $this->asTrixHtml([
            'Rumus ABC: $x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}$',
            '',
            'Untuk $x^2 - 5x + 6 = 0$ diperoleh $x_1 = 3$ dan $x_2 = 2$.',
        ]);
    }

    /** Esai Bahasa Indonesia dengan markup Trix biasa (bold + list). */
    private function indonesianEssayAnswer(): string
    {
        return '<div>Menurut saya, kutipan tidak langsung lebih cocok untuk teks berita karena '
            .'<strong>lebih padat</strong> dan tidak memerlukan tanda petik.</div>'
            .'<div><br></div>'
            .'<ul><li>Kalimat aktif: "Petani memanen padi."</li>'
            .'<li>Kalimat pasif: "Padi dipanen petani."</li></ul>'
            .'<div><br></div>'
            .'<div>Sumber: Kemdikbud (2023), hlm. 45&ndash;47.</div>';
    }

    /**
     * Prosa yang penuh karakter "mirip math" — jaring pengaman false positive:
     * persen, path backslash, tanda >, underscore, dan satuan.
     */
    private function proseWithTrickyCharacters(): string
    {
        return $this->asTrixHtml([
            'Draf esai saya sudah 80% selesai, tinggal bagian simpulan.',
            '',
            'File-nya ada di C:\Users\ahmad\Documents\tugas_bahasa.docx (sudah saya lampirkan juga).',
            '',
            'Perbandingan jumlah kata: bab 1 > bab 2, jadi saya ringkas lagi.',
            '',
            'Nama berkas pakai format laporan_akhir_v2 biar rapi.',
        ]);
    }

    private function englishEssayAnswer(): string
    {
        return $this->asTrixHtml([
            'My holiday was great. I went to Bali with my family for 5 days.',
            '',
            'We spent about 60% of the time at the beach, and the rest visiting temples.',
            '',
            'I learned that "simple past" is used for finished actions in the past.',
        ]);
    }

    /**
     * Bungkus tiap baris jadi <div> seperti output editor Trix milik siswa,
     * termasuk escaping karakter HTML (& → &amp;) yang jadi sumber masalah
     * saat blok align dipindai.
     *
     * @param  array<int, string>  $lines
     */
    private function asTrixHtml(array $lines): string
    {
        return implode('', array_map(
            fn (string $line) => $line === ''
                ? '<div><br></div>'
                : '<div>'.htmlspecialchars($line, ENT_QUOTES, 'UTF-8').'</div>',
            $lines,
        ));
    }
}
