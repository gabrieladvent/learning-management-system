<?php

namespace Tests\Feature\Student;

use App\Actions\Student\RegisterStudent;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

class GuardedMediaDownloadTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('student', 'web');
        Storage::fake(config('media-library.disk_name'));
    }

    private function makeActiveStudent($school, $classroom, string $nisn): Student
    {
        $student = app(RegisterStudent::class)->handle([
            'full_name' => 'S '.$nisn, 'school_id' => $school->id, 'nisn' => $nisn,
            'gender' => 'male', 'birth_date' => '2008-01-01', 'is_active' => true,
        ]);
        $student->user->update(['password_changed_at' => now()]); // lewati gate ganti-password
        $classroom->students()->attach($student->id, ['enrolled_at' => now()]);

        return $student;
    }

    public function test_owner_can_download_but_other_student_cannot(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeTeacher();
        $subject = $this->makeSubject();
        $classroom = $this->makeClassroom($school, $teacher);
        $cs = $this->makeClassroomSubject($classroom, $subject, $teacher);
        $material = $this->makeMaterial($cs);
        $assignment = $this->makeAssignment($material);

        $owner = $this->makeActiveStudent($school, $classroom, '1111111111');
        $other = $this->makeActiveStudent($school, $classroom, '2222222222');

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $owner->id,
            'content' => 'jawaban',
            'submitted_at' => now(),
        ]);
        $media = $submission->addMedia(UploadedFile::fake()->create('jawaban.pdf', 12))
            ->toMediaCollection('submission_files');

        $params = [
            'material' => $material->id,
            'assignment' => $assignment->id,
            'media' => $media->uuid ?? $media->id,
        ];

        // Pemilik → boleh
        $this->actingAs($owner, 'student')
            ->get(route('student.assignments.submission-files.download', $params))
            ->assertOk();

        // Siswa lain → 404 (tidak punya submission ini)
        $this->actingAs($other, 'student')
            ->get(route('student.assignments.submission-files.download', $params))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeTeacher();
        $classroom = $this->makeClassroom($school, $teacher);
        $cs = $this->makeClassroomSubject($classroom, $this->makeSubject(), $teacher);
        $material = $this->makeMaterial($cs);
        $assignment = $this->makeAssignment($material);

        $this->get(route('student.assignments.submission-files.download', [
            'material' => $material->id,
            'assignment' => $assignment->id,
            'media' => 'whatever',
        ]))->assertRedirect(route('student.login'));
    }
}
