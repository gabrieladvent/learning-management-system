<?php

namespace Database\Seeders;

use App\Actions\Student\RegisterStudent;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\Enums\GenderEnum;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamSession;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeder untuk LOAD TEST di STAGING saja.
 *
 * Generate 80 siswa (40 per kelas, 2 kelas) + dummy ExamSession + AssignmentSubmission
 * supaya bisa test performance ExamGrader & Filament listing di skala production.
 *
 * JANGAN dijalankan di production. Jalankan via:
 *   php artisan db:seed --class=LoadTestSeeder
 */
class LoadTestSeeder extends Seeder
{
    private const TARGET_PER_CLASS = 40;

    private const NISN_PREFIX = '999';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('LoadTestSeeder NEVER runs in production. Aborting.');

            return;
        }

        $school = School::first();
        if (! $school) {
            $this->command->error('No school. Run base seeder first.');

            return;
        }

        $register = app(RegisterStudent::class);

        $classrooms = Classroom::whereIn('name', ['X IPA 1', 'X IPA 2'])->get();
        if ($classrooms->isEmpty()) {
            $this->command->error('Classrooms X IPA 1 / X IPA 2 belum ada. Run base seeder dulu.');

            return;
        }

        $totalCreated = 0;
        $totalEnrolled = 0;

        foreach ($classrooms as $classroomIndex => $classroom) {
            $existingCount = $classroom->students()->count();
            $need = max(0, self::TARGET_PER_CLASS - $existingCount);

            $this->command->info("Classroom {$classroom->name}: {$existingCount} existing, generate {$need} more...");

            $created = [];
            for ($i = 0; $i < $need; $i++) {
                $idx = $existingCount + $i + 1;
                // NISN: 999 + classroomIndex (1 digit) + idx (zero-padded 6 digit)
                $nisn = self::NISN_PREFIX
                    .($classroomIndex + 1)
                    .str_pad((string) $idx, 6, '0', STR_PAD_LEFT);

                if (Student::where('nisn', $nisn)->exists()) {
                    continue;
                }

                $student = $register->handle([
                    'school_id' => $school->id,
                    'full_name' => "Siswa Test {$classroom->name} #{$idx}",
                    'nisn' => $nisn,
                    'class' => $classroom->name,
                    'gender' => $i % 2 === 0 ? GenderEnum::Male->value : GenderEnum::Female->value,
                    'birth_date' => Carbon::create(2008, 1, 1)->addDays($i)->toDateString(),
                    'place_of_birth' => 'Loadtest City',
                    'is_active' => true,
                ]);

                $created[] = $student;
                $totalCreated++;
            }

            if ($created !== []) {
                $classroom->students()->syncWithoutDetaching(
                    collect($created)->mapWithKeys(fn ($s) => [$s->id => ['enrolled_at' => now()]])->all()
                );
                $totalEnrolled += count($created);
            }
        }

        $this->command->info("Total created: {$totalCreated} students, enrolled: {$totalEnrolled}.");

        $this->seedDummySubmissions($classrooms);
        $this->seedDummyExamSessions($classrooms);

        $this->command->info('Done. Database siap untuk load test.');
    }

    /**
     * @param  Collection<int, Classroom>  $classrooms
     */
    private function seedDummySubmissions(Collection $classrooms): void
    {
        $assignments = Assignment::query()
            ->where('is_published', true)
            ->whereHas('material.classroomSubject.classroom', fn ($q) => $q->whereIn('id', $classrooms->pluck('id')))
            ->limit(5)
            ->get();

        if ($assignments->isEmpty()) {
            $this->command->warn('Tidak ada published assignment untuk seed dummy submission.');

            return;
        }

        $count = 0;
        DB::transaction(function () use ($assignments, $classrooms, &$count) {
            foreach ($assignments as $assignment) {
                $classroomId = $assignment->material?->classroomSubject?->classroom_id;
                if (! $classroomId) {
                    continue;
                }

                $studentIds = $classrooms->firstWhere('id', $classroomId)
                    ?->students()->pluck('students.id')->all() ?? [];

                foreach ($studentIds as $studentId) {
                    if (AssignmentSubmission::withTrashed()
                        ->where('assignment_id', $assignment->id)
                        ->where('student_id', $studentId)
                        ->exists()) {
                        continue;
                    }

                    AssignmentSubmission::create([
                        'assignment_id' => $assignment->id,
                        'student_id' => $studentId,
                        'content' => 'Jawaban dummy load test.',
                        'submitted_at' => now()->subHours(rand(1, 48)),
                    ]);
                    $count++;
                }
            }
        });

        $this->command->info("Dummy submissions created: {$count}.");
    }

    /**
     * @param  Collection<int, Classroom>  $classrooms
     */
    private function seedDummyExamSessions(Collection $classrooms): void
    {
        $exams = Exam::query()
            ->where('is_published', true)
            ->whereHas('material.classroomSubject.classroom', fn ($q) => $q->whereIn('id', $classrooms->pluck('id')))
            ->with('questions')
            ->limit(3)
            ->get();

        if ($exams->isEmpty()) {
            $this->command->warn('Tidak ada published exam untuk seed dummy session.');

            return;
        }

        $count = 0;
        DB::transaction(function () use ($exams, $classrooms, &$count) {
            foreach ($exams as $exam) {
                $classroomId = $exam->material?->classroomSubject?->classroom_id;
                if (! $classroomId) {
                    continue;
                }

                $studentIds = $classrooms->firstWhere('id', $classroomId)
                    ?->students()->pluck('students.id')->all() ?? [];

                foreach ($studentIds as $studentId) {
                    if (ExamSession::where('exam_id', $exam->id)->where('student_id', $studentId)->exists()) {
                        continue;
                    }

                    $session = ExamSession::create([
                        'exam_id' => $exam->id,
                        'student_id' => $studentId,
                        'started_at' => now()->subMinutes(rand(20, 60)),
                        'submitted_at' => now()->subMinutes(rand(1, 19)),
                        'submission_reason' => 'manual',
                    ]);

                    foreach ($exam->questions as $question) {
                        ExamAnswer::create([
                            'exam_session_id' => $session->id,
                            'exam_question_id' => $question->id,
                            'answer' => 'A',
                        ]);
                    }
                    $count++;
                }
            }
        });

        $this->command->info("Dummy exam sessions created: {$count}.");
    }
}
