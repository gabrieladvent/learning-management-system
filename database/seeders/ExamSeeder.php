<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Material;
use Illuminate\Database\Seeder;

class ExamSeeder extends Seeder
{
    /**
     * Buat dataset ujian yang deterministik & tidak bergantung pada string title.
     *
     * Strategi (per course, material di-sort by `order`):
     *  - Material pertama → Pretest online_quiz (3 soal: MC, short, essay).
     *    Latihan singkat sebelum siswa masuk ke materi inti.
     *  - Material kedua (jika ada) → Posttest online_quiz dengan shuffle aktif —
     *    untuk uji rendering & determinism shuffle.
     *  - Material terakhir → Tugas akhir mode submission (kumpul file/text/link)
     *    dengan window pengumpulan 1 minggu.
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
            $list = $courseMaterials->values();
            $first = $list->first();
            $second = $list->get(1);
            $last = $list->last();

            if ($first) {
                $this->seedPretest($first);
            }

            if ($second && $second->id !== $first?->id) {
                $this->seedPosttest($second);
            }

            if ($last && $last->id !== $first?->id && $last->id !== $second?->id) {
                $this->seedSubmissionExam($last);
            }
        }
    }

    private function seedPretest(Material $material): void
    {
        $exam = Exam::firstOrCreate(
            ['material_id' => $material->id, 'title' => "Pretest: {$material->title}"],
            [
                'description' => '<p>Kuis singkat untuk mengecek pemahaman awal kamu sebelum memulai bab ini. Waktu 10 menit, tiga soal — kerjakan dengan tenang.</p>',
                'mode' => 'online_quiz',
                'starts_at' => now()->subDay(),
                'duration_minutes' => 10,
                'max_score' => 30,
                'shuffle_questions' => false,
                'status' => 'published',
                'is_published' => true,
                'available_from' => now()->subDay(),
            ]
        );

        if ($exam->questions()->count() > 0) {
            return;
        }

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'type' => 'multiple_choice',
            'question' => 'Apa yang menjadi tujuan utama mempelajari materi ini?',
            'options' => [
                'A' => 'Memahami terminologi inti',
                'B' => 'Menghafal seluruh isi buku',
                'C' => 'Menyelesaikan satu contoh soal saja',
                'D' => 'Tidak ada tujuan khusus',
            ],
            'correct_answer' => 'A',
            'score' => 10,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'type' => 'short_answer',
            'question' => 'Sebutkan satu istilah dasar yang paling sering muncul di materi ini.',
            'correct_answer' => 'definisi',
            'score' => 10,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'type' => 'essay',
            'question' => 'Tuliskan satu paragraf singkat (3–5 kalimat) berisi ringkasan apa yang kamu ketahui sebelum membaca materi ini.',
            'score' => 10,
        ]);
    }

    private function seedPosttest(Material $material): void
    {
        $exam = Exam::firstOrCreate(
            ['material_id' => $material->id, 'title' => "Posttest: {$material->title}"],
            [
                'description' => '<p>Setelah belajar materi inti, kerjakan soal pengaplikasian berikut. Urutan soal akan diacak.</p>',
                'mode' => 'online_quiz',
                'starts_at' => now()->subDay(),
                'duration_minutes' => 15,
                'max_score' => 30,
                'shuffle_questions' => true,
                'status' => 'published',
                'is_published' => true,
                'available_from' => now()->subDay(),
            ]
        );

        if ($exam->questions()->count() > 0) {
            return;
        }

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'type' => 'multiple_choice',
            'question' => 'Berapa hasil $2^3 + 4$?',
            'options' => [
                'A' => '10',
                'B' => '12',
                'C' => '8',
                'D' => '14',
            ],
            'correct_answer' => 'B',
            'score' => 10,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'type' => 'short_answer',
            'question' => 'Tuliskan akar dari $\sqrt{144}$.',
            'correct_answer' => '12',
            'score' => 10,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'type' => 'essay',
            'question' => 'Jelaskan langkah-langkah menyelesaikan $x^2 - 5x + 6 = 0$.',
            'score' => 10,
        ]);
    }

    private function seedSubmissionExam(Material $material): void
    {
        Exam::firstOrCreate(
            ['material_id' => $material->id, 'title' => "Tugas Akhir: {$material->title}"],
            [
                'description' => '<p>Sebagai penutup, kumpulkan rangkuman bab dalam bentuk dokumen PDF/DOCX. Boleh dilengkapi dengan tautan referensi pendukung.</p>',
                'mode' => 'submission',
                'starts_at' => now()->subDay(),
                'duration_minutes' => 60 * 24 * 7,
                'max_score' => 100,
                'shuffle_questions' => false,
                'allowed_file_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
                'max_file_size_mb' => 10,
                'status' => 'published',
                'is_published' => true,
                'available_from' => now()->subDay(),
                'available_until' => now()->addWeek(),
            ]
        );
    }
}
