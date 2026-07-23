<?php

namespace Tests\Feature\Filament;

use App\Models\AssignmentSubmission;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\ExamSubmission;
use App\Support\GradeBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

/**
 * Test langsung untuk GradeBook (diekstrak dari GradeRecap Page). Sekarang logika
 * matriks nilai bisa diuji tanpa Filament/Livewire.
 */
class GradeBookTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    public function test_builds_columns_and_matrix_with_scores(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeTeacher();
        $classroom = $this->makeClassroom($school, $teacher);
        $cs = $this->makeClassroomSubject($classroom, $this->makeSubject(), $teacher);
        $material = $this->makeMaterial($cs);

        $assignment = $this->makeAssignment($material, ['title' => 'Tugas 1']);
        $quiz = $this->makeExam($material, ['title' => 'Kuis', 'mode' => 'online_quiz', 'starts_at' => Carbon::now()->subHour()]);
        $submissionExam = $this->makeExam($material, ['title' => 'Proyek', 'mode' => 'submission', 'starts_at' => Carbon::now()->subHour()]);
        ExamQuestion::create([
            'exam_id' => $quiz->id, 'type' => 'multiple_choice', 'question' => 'q',
            'options' => ['A' => '1'], 'correct_answer' => 'A', 'score' => 10, 'order' => 1,
        ]);

        $student = $this->makeStudent($school, $classroom);

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id, 'student_id' => $student->id,
            'score' => 85, 'submitted_at' => Carbon::now(),
        ]);
        ExamSession::create([
            'exam_id' => $quiz->id, 'student_id' => $student->id,
            'started_at' => Carbon::now()->subMinutes(30), 'submitted_at' => Carbon::now()->subMinutes(20),
            'total_score' => 70,
        ]);
        ExamSubmission::create([
            'exam_id' => $submissionExam->id, 'student_id' => $student->id,
            'score' => 90, 'submitted_at' => Carbon::now(),
        ]);

        $book = new GradeBook($cs);

        $columns = $book->columns();
        $this->assertSame(['a_'.$assignment->id, 'e_'.$quiz->id, 'e_'.$submissionExam->id], array_column($columns, 'key'));
        $this->assertSame(['assignment', 'exam_quiz', 'exam_submission'], array_column($columns, 'type'));

        $matrix = $book->matrix();
        $this->assertSame(85.0, $matrix[$student->id]['a_'.$assignment->id]);
        $this->assertSame(70.0, $matrix[$student->id]['e_'.$quiz->id]);
        $this->assertSame(90.0, $matrix[$student->id]['e_'.$submissionExam->id]);
    }

    public function test_unsubmitted_quiz_session_shows_null(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeTeacher();
        $classroom = $this->makeClassroom($school, $teacher);
        $cs = $this->makeClassroomSubject($classroom, $this->makeSubject(), $teacher);
        $material = $this->makeMaterial($cs);
        $quiz = $this->makeExam($material, ['mode' => 'online_quiz', 'starts_at' => Carbon::now()->subHour()]);
        $student = $this->makeStudent($school, $classroom);

        // Session dimulai tapi BELUM submit → skor harus null di matriks.
        ExamSession::create([
            'exam_id' => $quiz->id, 'student_id' => $student->id,
            'started_at' => Carbon::now()->subMinutes(5), 'submitted_at' => null,
            'total_score' => null,
        ]);

        $matrix = (new GradeBook($cs))->matrix();
        $this->assertNull($matrix[$student->id]['e_'.$quiz->id]);
    }
}
