<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AssignmentResource;
use App\Filament\Resources\AssignmentResource\Pages\EditAssignment;
use App\Filament\Resources\AssignmentResource\Pages\ViewAssignment;
use App\Filament\Resources\AssignmentResource\RelationManagers\SubmissionsRelationManager;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

/**
 * Jaring pengaman untuk tampilan jawaban matematika di panel guru: siswa menempel
 * LaTeX tanpa delimiter, dan KaTeX auto-render hanya mengenali $…$ / $$…$$ / \(…\) / \[…\].
 * Kalau placeholder jawaban berhenti memakai App\Support\MathHtml, guru akan kembali
 * melihat LaTeX mentah — test ini yang menangkapnya.
 */
class SubmissionMathRenderingTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    /**
     * Relation manager "Pengumpulan Siswa" tampil di halaman View (/teacher/assignments/{id})
     * maupun Edit, jadi keduanya dikunci.
     *
     * @param  class-string  $pageClass
     */
    #[DataProvider('pageClasses')]
    public function test_teacher_sees_delimited_math_instead_of_raw_latex(string $pageClass): void
    {
        Role::findOrCreate('super_admin', 'web');

        $school = $this->makeSchool();
        $teacher = $this->makeTeacher();
        $classroom = $this->makeClassroom($school, $teacher);
        $cs = $this->makeClassroomSubject($classroom, $this->makeSubject(), $teacher);
        $material = $this->makeMaterial($cs);
        $assignment = $this->makeAssignment($material);
        $student = $this->makeStudent($school, $classroom);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            // Seperti jawaban tempelan siswa lewat Trix: tiap baris jadi <div> sendiri,
            // sehingga blok align terpotong markup dan "&" tersimpan sebagai entity.
            'content' => '<div>Diketahui: \sin \theta = \frac{9}{41} → depan = 9</div>'
                .'<div>\begin{align*}</div>'
                .'<div>\cos \theta &amp;= \frac{40}{41} \\\\[6pt]</div>'
                .'<div>\tan \theta &amp;= \frac{9}{40}</div>'
                .'<div>\end{align*}</div>',
            'submitted_at' => Carbon::now(),
        ]);

        $this->actingAs($teacher->user);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        $component = Livewire::test(SubmissionsRelationManager::class, [
            'ownerRecord' => $assignment,
            'pageClass' => $pageClass,
        ])->mountTableAction('edit', $submission->getKey());

        $html = $component->html();

        // Inline math dibungkus $…$ dan blok align jadi display math $$…$$.
        $this->assertStringContainsString('math-inline', $html);
        $this->assertStringContainsString('math-block', $html);
        $this->assertStringContainsString('$$\begin{align*}', html_entity_decode($html, ENT_QUOTES | ENT_HTML5));

        // Tidak ada lagi LaTeX yang menggantung tanpa delimiter.
        $this->assertStringNotContainsString(
            'Diketahui: \sin \theta',
            html_entity_decode($html, ENT_QUOTES | ENT_HTML5)
        );
    }

    /**
     * Deskripsi/soal buatan guru di halaman View juga harus ter-render,
     * bukan tampil sebagai LaTeX mentah.
     */
    public function test_assignment_description_math_renders_on_view_page(): void
    {
        Role::findOrCreate('super_admin', 'web');

        $school = $this->makeSchool();
        $teacher = $this->makeTeacher();
        $classroom = $this->makeClassroom($school, $teacher);
        $cs = $this->makeClassroomSubject($classroom, $this->makeSubject(), $teacher);
        $material = $this->makeMaterial($cs);
        $assignment = $this->makeAssignment($material, [
            'description' => '<div>Hitung \sin \theta = \frac{9}{41} lalu sederhanakan.</div>',
        ]);

        // Halaman View dijaga Shield; pakai super_admin supaya yang diuji murni
        // rendering infolist-nya, bukan aturan izinnya (itu punya TeacherScopingTest).
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.Str::random(6).'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $admin->assignRole('super_admin');
        // Permission Shield tidak ikut RefreshDatabase, jadi diberikan eksplisit.
        // `view_any_assignment` dipakai Resource::canAccess (route guard),
        // `view_assignment` dipakai ViewRecord::authorizeAccess.
        $admin->givePermissionTo(Permission::findOrCreate('view_any_assignment', 'web'));
        $admin->givePermissionTo(Permission::findOrCreate('view_assignment', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        $html = $this->get(AssignmentResource::getUrl('view', ['record' => $assignment], panel: 'teacher'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('math-inline', $html);
        $this->assertStringNotContainsString(
            'Hitung \sin \theta',
            html_entity_decode($html, ENT_QUOTES | ENT_HTML5)
        );
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function pageClasses(): array
    {
        return [
            'halaman view' => [ViewAssignment::class],
            'halaman edit' => [EditAssignment::class],
        ];
    }
}
