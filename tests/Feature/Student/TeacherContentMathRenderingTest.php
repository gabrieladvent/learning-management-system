<?php

namespace Tests\Feature\Student;

use App\Actions\Student\GetStudentAssignment;
use App\Actions\Student\GetStudentExamSession;
use App\Actions\Student\GetStudentMaterial;
use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

/**
 * Konten buatan GURU (deskripsi tugas, isi materi, teks soal) juga sering berisi
 * LaTeX tanpa delimiter. Payload ke halaman siswa harus sudah dinormalisasi,
 * karena MathContent.tsx cuma mengenali $…$ / $$…$$ / \(…\) / \[…\].
 */
class TeacherContentMathRenderingTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    public function test_assignment_description_math_is_delimited(): void
    {
        [$school, $classroom, $cs] = $this->courseTree();
        $material = $this->makeMaterial($cs);
        $assignment = $this->makeAssignment($material, [
            'description' => '<div>Hitung \sin \theta = \frac{9}{41} lalu sederhanakan.</div>',
        ]);
        $student = $this->makeStudent($school, $classroom);

        $payload = app(GetStudentAssignment::class)->handle($student, $material->id, $assignment->id);

        $this->assertStringContainsString('math-inline', $payload['assignment']['description']);
        $this->assertStringContainsString('$\sin \theta = \frac{9}{41}$', $payload['assignment']['description']);
    }

    public function test_material_content_math_is_delimited(): void
    {
        [$school, $classroom, $cs] = $this->courseTree();
        $material = $this->makeMaterial($cs, [
            'content' => '<div>Rumus luas: A = \pi r^2</div>',
        ]);
        $student = $this->makeStudent($school, $classroom);

        $payload = app(GetStudentMaterial::class)->handle($student, $cs->id, $material->id);

        $this->assertStringContainsString('math-inline', $payload['material']['content']);
    }

    public function test_quiz_question_and_options_math_is_delimited(): void
    {
        [$school, $classroom, $cs] = $this->courseTree();
        $material = $this->makeMaterial($cs);
        $exam = $this->makeExam($material, [
            'mode' => 'online_quiz',
            'starts_at' => Carbon::now()->subHour(),
            'status' => 'published',
        ]);
        $student = $this->makeStudent($school, $classroom);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'type' => 'multiple_choice',
            'question' => '<div>Berapa nilai \cos \theta jika \sin \theta = \frac{3}{5}?</div>',
            'options' => ['A' => '\frac{4}{5}', 'B' => '\frac{3}{4}'],
            'correct_answer' => 'A',
            'score' => 10,
            'order' => 1,
        ]);

        $session = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'started_at' => Carbon::now()->subMinutes(5),
        ]);

        $payload = app(GetStudentExamSession::class)->handle($student, $session->id);
        $question = $payload['questions'][0];

        $this->assertStringContainsString('math-inline', $question['question']);
        $this->assertStringContainsString('math-inline', $question['options']['A']);
        $this->assertStringContainsString('$\frac{4}{5}$', $question['options']['A']);
    }

    public function test_teacher_feedback_keeps_line_breaks_and_renders_math(): void
    {
        [$school, $classroom, $cs] = $this->courseTree();
        $material = $this->makeMaterial($cs);
        $assignment = $this->makeAssignment($material);
        $student = $this->makeStudent($school, $classroom);

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'content' => '<div>jawaban saya</div>',
            'submitted_at' => Carbon::now(),
            'score' => 80,
            'feedback' => "Nomor 2 kurang tepat.\nSeharusnya \\frac{1}{2}, bukan 2.",
        ]);

        $payload = app(GetStudentAssignment::class)->handle($student, $material->id, $assignment->id);

        $this->assertStringContainsString('<br>', $payload['submission']['feedback_html']);
        $this->assertStringContainsString('math-inline', $payload['submission']['feedback_html']);
    }

    /**
     * Jawaban mentah WAJIB tetap utuh: ini yang dimuat ke editor Trix saat siswa
     * mengedit. Kalau ikut dinormalisasi, tanda $ akan tertanam ke jawaban siswa.
     */
    public function test_raw_submission_content_is_untouched_for_the_editor(): void
    {
        [$school, $classroom, $cs] = $this->courseTree();
        $material = $this->makeMaterial($cs);
        $assignment = $this->makeAssignment($material);
        $student = $this->makeStudent($school, $classroom);

        $raw = '<div>Jawaban: \sin \theta = \frac{9}{41}</div>';

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'content' => $raw,
            'submitted_at' => Carbon::now(),
        ]);

        $payload = app(GetStudentAssignment::class)->handle($student, $material->id, $assignment->id);

        $this->assertSame($raw, $payload['submission']['content']);
        $this->assertStringContainsString('math-inline', $payload['submission']['content_html']);
    }

    /**
     * @return array{0: School, 1: Classroom, 2: ClassroomSubject}
     */
    private function courseTree(): array
    {
        $school = $this->makeSchool();
        $teacher = $this->makeTeacher();
        $classroom = $this->makeClassroom($school, $teacher);
        $cs = $this->makeClassroomSubject($classroom, $this->makeSubject(), $teacher);

        return [$school, $classroom, $cs];
    }
}
