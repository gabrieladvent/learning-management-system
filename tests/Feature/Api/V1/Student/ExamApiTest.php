<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\ExamSubmission;
use App\Models\Material;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class ExamApiTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('student', 'web');
        Storage::fake('local');
    }

    /**
     * @return array{student:Student, material:Material, exam:Exam, token:string}
     */
    private function scaffold(array $examOverrides = []): array
    {
        $ctx = $this->scaffoldStudentWithMaterial();

        $user = User::create([
            'name' => $ctx['student']->full_name,
            'email' => null,
            'password' => bcrypt('rahasia-banget'),
            'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('student');
        $ctx['student']->forceFill(['user_id' => $user->id])->save();

        return [
            'student' => $ctx['student'],
            'material' => $ctx['material'],
            // starts_at dimundurkan supaya ujian bisa langsung dimulai.
            'exam' => $this->makeExam($ctx['material'], array_merge([
                'starts_at' => now()->subMinutes(5),
                'duration_minutes' => 60,
            ], $examOverrides)),
            'token' => $ctx['student']->createToken('test-device')->plainTextToken,
        ];
    }

    private function addQuestion(Exam $exam, array $overrides = []): ExamQuestion
    {
        return ExamQuestion::create(array_merge([
            'exam_id' => $exam->id,
            'type' => 'multiple_choice',
            'question' => 'Ibu kota Indonesia?',
            'options' => ['Jakarta', 'Bandung', 'Surabaya', 'Medan'],
            'correct_answer' => 'Jakarta',
            'score' => 10,
            'order' => 1,
        ], $overrides));
    }

    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function openExamSession(array $ctx): string
    {
        return $this->withToken($ctx['token'])
            ->postJson("/api/v1/materials/{$ctx['material']->id}/exams/{$ctx['exam']->id}/start")
            ->assertOk()
            ->json('response_data.session_id');
    }

    public function test_exam_detail_returns_mode_and_timing(): void
    {
        $ctx = $this->scaffold();

        $this->withToken($ctx['token'])
            ->getJson("/api/v1/materials/{$ctx['material']->id}/exams/{$ctx['exam']->id}")
            ->assertOk()
            ->assertJsonPath('response_code', 'success')
            ->assertJsonPath('response_data.exam.id', $ctx['exam']->id)
            ->assertJsonPath('response_data.exam.mode', 'online_quiz')
            ->assertJsonStructure([
                'response_data' => ['course', 'material', 'exam' => ['id', 'mode', 'starts_at', 'duration_minutes']],
            ]);
    }

    public function test_starting_twice_reuses_the_same_session_without_extra_time(): void
    {
        // Kalau started_at ter-reset, siswa dapat waktu tambahan gratis
        // hanya dengan menekan "mulai" lagi.
        $ctx = $this->scaffold();
        $this->addQuestion($ctx['exam']);

        $first = $this->openExamSession($ctx);
        $startedAt = ExamSession::findOrFail($first)->started_at;

        $this->travel(10)->minutes();
        $this->forgetGuards();

        $second = $this->openExamSession($ctx);

        $this->assertSame($first, $second, 'Sesi harus sama.');
        $this->assertSame(1, ExamSession::count());
        $this->assertEquals(
            $startedAt->toIso8601String(),
            ExamSession::findOrFail($first)->started_at->toIso8601String(),
            'started_at TIDAK boleh di-reset.',
        );
    }

    public function test_session_payload_carries_expiry_and_server_time(): void
    {
        $ctx = $this->scaffold(['duration_minutes' => 45]);
        $this->addQuestion($ctx['exam']);
        $sessionId = $this->openExamSession($ctx);
        $this->forgetGuards();

        $data = $this->withToken($ctx['token'])
            ->getJson("/api/v1/exams/sessions/{$sessionId}")
            ->assertOk()
            ->json('response_data');

        $this->assertNotNull($data['session']['expires_at'], 'Klien butuh expires_at untuk timer.');
        $this->assertNotNull($data['server_time'], 'Klien butuh server_time sebagai acuan, bukan jam perangkat.');

        $remaining = strtotime($data['session']['expires_at']) - strtotime($data['server_time']);
        $this->assertEqualsWithDelta(45 * 60, $remaining, 60);
    }

    public function test_correct_answer_never_appears_in_any_student_payload(): void
    {
        // Menyaring di UI tidak ada gunanya — payload tetap terbaca lewat proxy.
        // Kunci jawaban harus tidak pernah dikirim, bahkan setelah hasil dirilis.
        $ctx = $this->scaffold(['results_released_at' => now()->subDay()]);
        $this->addQuestion($ctx['exam'], ['correct_answer' => 'JAWABAN-RAHASIA']);
        $sessionId = $this->openExamSession($ctx);

        $urls = [
            "/api/v1/materials/{$ctx['material']->id}/exams/{$ctx['exam']->id}",
            "/api/v1/exams/sessions/{$sessionId}",
            "/api/v1/exams/sessions/{$sessionId}/result",
        ];

        foreach ($urls as $url) {
            $this->forgetGuards();
            $body = $this->withToken($ctx['token'])->getJson($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('JAWABAN-RAHASIA', $body, "Kunci jawaban bocor di {$url}");
            $this->assertStringNotContainsString('correct_answer', $body, "Field correct_answer muncul di {$url}");
        }
    }

    public function test_answer_is_saved_and_returned_on_reload(): void
    {
        $ctx = $this->scaffold();
        $question = $this->addQuestion($ctx['exam']);
        $sessionId = $this->openExamSession($ctx);
        $this->forgetGuards();

        $this->withToken($ctx['token'])->postJson("/api/v1/exams/sessions/{$sessionId}/answer", [
            'exam_question_id' => $question->id,
            'answer' => 'Jakarta',
        ])
            ->assertOk()
            ->assertJsonPath('response_code', 'success')
            ->assertJsonPath('response_data.exam_question_id', $question->id);

        $this->forgetGuards();

        $this->withToken($ctx['token'])
            ->getJson("/api/v1/exams/sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath("response_data.answers.{$question->id}", 'Jakarta');
    }

    public function test_answering_the_same_question_twice_keeps_the_latest(): void
    {
        $ctx = $this->scaffold();
        $question = $this->addQuestion($ctx['exam']);
        $sessionId = $this->openExamSession($ctx);

        foreach (['Bandung', 'Jakarta'] as $answer) {
            $this->forgetGuards();
            $this->withToken($ctx['token'])->postJson("/api/v1/exams/sessions/{$sessionId}/answer", [
                'exam_question_id' => $question->id,
                'answer' => $answer,
            ])->assertOk();
        }

        $this->forgetGuards();
        $this->withToken($ctx['token'])
            ->getJson("/api/v1/exams/sessions/{$sessionId}")
            ->assertJsonPath("response_data.answers.{$question->id}", 'Jakarta');
    }

    public function test_submitting_twice_stays_successful(): void
    {
        // Klien mengulang kiriman saat respons hilang di jaringan buruk.
        // Pengulangan TIDAK boleh dibalas error untuk ujian yang sudah aman
        // terkumpul — dan waktu submit pertama tidak boleh bergeser.
        $ctx = $this->scaffold();
        $this->addQuestion($ctx['exam']);
        $sessionId = $this->openExamSession($ctx);
        $this->forgetGuards();

        $first = $this->withToken($ctx['token'])
            ->postJson("/api/v1/exams/sessions/{$sessionId}/submit")
            ->assertOk()
            ->json('response_data.submitted_at');

        $this->travel(2)->minutes();
        $this->forgetGuards();

        $this->withToken($ctx['token'])
            ->postJson("/api/v1/exams/sessions/{$sessionId}/submit")
            ->assertOk()
            ->assertJsonPath('response_code', 'success')
            ->assertJsonPath('response_data.submitted_at', $first);
    }

    public function test_results_are_withheld_until_released(): void
    {
        $ctx = $this->scaffold(['results_released_at' => now()->addWeek()]);
        $this->addQuestion($ctx['exam']);
        $sessionId = $this->openExamSession($ctx);
        $this->forgetGuards();
        $this->withToken($ctx['token'])->postJson("/api/v1/exams/sessions/{$sessionId}/submit")->assertOk();
        $this->forgetGuards();

        $this->withToken($ctx['token'])
            ->getJson("/api/v1/exams/sessions/{$sessionId}/result")
            ->assertOk()
            ->assertJsonPath('response_data.session.results_released', false)
            ->assertJsonPath('response_data.session.total_score', null);
    }

    public function test_cannot_open_another_students_session(): void
    {
        $ctx = $this->scaffold();
        $this->addQuestion($ctx['exam']);
        $sessionId = $this->openExamSession($ctx);

        $other = $this->scaffold();
        $this->forgetGuards();

        $this->withToken($other['token'])
            ->getJson("/api/v1/exams/sessions/{$sessionId}")
            ->assertStatus(404)
            ->assertJsonPath('response_code', 'not_found');
    }

    public function test_question_file_download_requires_owning_the_session(): void
    {
        $ctx = $this->scaffold();
        $question = $this->addQuestion($ctx['exam']);
        $media = $question->addMedia(UploadedFile::fake()->create('soal.pdf', 12))->toMediaCollection('question_files');
        $sessionId = $this->openExamSession($ctx);

        $this->forgetGuards();
        $this->withToken($ctx['token'])
            ->get("/api/v1/exams/sessions/{$sessionId}/questions/{$media->uuid}/download")
            ->assertOk()
            ->assertDownload('soal.pdf');

        $other = $this->scaffold();
        $this->forgetGuards();
        $this->withToken($other['token'])
            ->getJson("/api/v1/exams/sessions/{$sessionId}/questions/{$media->uuid}/download")
            ->assertStatus(404);
    }

    public function test_submission_mode_submit_is_idempotent(): void
    {
        $ctx = $this->scaffold(['mode' => 'submission']);
        $url = "/api/v1/materials/{$ctx['material']->id}/exams/{$ctx['exam']->id}/submit-submission";
        $key = (string) Str::uuid();

        $this->withToken($ctx['token'])->postJson($url, [
            'idempotency_key' => $key,
            'content' => 'Jawaban ujian',
        ])
            ->assertOk()
            ->assertJsonPath('response_data.submission.content', 'Jawaban ujian');

        $this->forgetGuards();

        $this->withToken($ctx['token'])->postJson($url, [
            'idempotency_key' => $key,
            'content' => 'Jawaban ujian',
        ])->assertOk();

        $this->assertSame(1, ExamSubmission::count());
    }

    public function test_submission_mode_requires_idempotency_key(): void
    {
        $ctx = $this->scaffold(['mode' => 'submission']);

        $this->withToken($ctx['token'])
            ->postJson("/api/v1/materials/{$ctx['material']->id}/exams/{$ctx['exam']->id}/submit-submission", [
                'content' => 'Tanpa kunci',
            ])
            ->assertStatus(422)
            ->assertJsonPath('response_code', 'validation_failed');

        $this->assertSame(0, ExamSubmission::count());
    }

    public function test_exam_endpoints_require_authentication(): void
    {
        $ctx = $this->scaffold();

        $this->getJson("/api/v1/materials/{$ctx['material']->id}/exams/{$ctx['exam']->id}")
            ->assertStatus(401)
            ->assertJsonPath('response_code', 'unauthenticated');
    }
}
