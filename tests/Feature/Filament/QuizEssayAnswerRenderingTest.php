<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ExamResource\Pages\EditExam;
use App\Filament\Resources\ExamResource\RelationManagers\SessionsRelationManager;
use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

/**
 * Jawaban esai pada ujian mode online_quiz disimpan sebagai HTML Trix
 * (ExamAnswer.answer). Sebelum ini dirender dengan e() saja, sehingga guru
 * melihat tag <div> sebagai teks DAN LaTeX-nya tetap mentah.
 */
class QuizEssayAnswerRenderingTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    public function test_teacher_sees_rendered_math_for_quiz_essay_answer(): void
    {
        Role::findOrCreate('super_admin', 'web');

        $school = $this->makeSchool();
        $teacher = $this->makeTeacher();
        $classroom = $this->makeClassroom($school, $teacher);
        $cs = $this->makeClassroomSubject($classroom, $this->makeSubject(), $teacher);
        $material = $this->makeMaterial($cs);
        $exam = $this->makeExam($material, ['starts_at' => Carbon::now()->subHour()]);
        $student = $this->makeStudent($school, $classroom);

        $question = ExamQuestion::create([
            'exam_id' => $exam->id,
            'type' => 'essay',
            'question' => '<p>Hitung nilai sin theta.</p>',
            'score' => 10,
            'order' => 1,
        ]);

        $session = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'started_at' => Carbon::now()->subMinutes(30),
            'submitted_at' => Carbon::now()->subMinutes(5),
        ]);

        ExamAnswer::create([
            'exam_session_id' => $session->id,
            'exam_question_id' => $question->id,
            'answer' => '<div>Jawaban: \sin \theta = \frac{9}{41}</div>',
        ]);

        $this->actingAs($teacher->user);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        $html = Livewire::test(SessionsRelationManager::class, [
            'ownerRecord' => $exam,
            'pageClass' => EditExam::class,
        ])->mountTableAction('viewAnswers', $session->getKey())->html();

        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);

        // Math dibungkus delimiter supaya KaTeX merendernya.
        $this->assertStringContainsString('math-inline', $html);
        $this->assertStringContainsString('$\sin \theta = \frac{9}{41}$', $decoded);

        // Tag HTML Trix tidak lagi bocor sebagai teks ke layar guru.
        $this->assertStringNotContainsString('&lt;div&gt;Jawaban', $html);
    }
}
