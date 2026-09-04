<?php

namespace Tests\Feature\Student;

use App\Models\Assignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesProgressFixtures;
use Tests\TestCase;

/**
 * Mengunci sifat keamanan migrasi `accepts_late_submission`.
 *
 * Kolomnya default `true` supaya tugas baru menerima keterlambatan, tapi baris
 * yang sudah ada di-backfill `false`. Kalau backfill itu hilang, semua tugas
 * lama — termasuk yang deadline-nya sudah lewat berminggu-minggu — mendadak
 * terbuka lagi dan pengumpulan yang masuk ikut ke penilaian guru. Perubahan
 * senyap pada nilai siswa, tanpa error apa pun.
 */
class LateSubmissionBackfillTest extends TestCase
{
    use CreatesProgressFixtures;
    use RefreshDatabase;

    /** Ditarget lewat --path, bukan --step, supaya tidak salah sasaran saat ada migrasi baru. */
    private const MIGRATION = 'database/migrations/2026_09_04_000002_add_accepts_late_submission_to_assignments_table.php';

    public function test_existing_assignments_keep_rejecting_late_submissions(): void
    {
        Artisan::call('migrate:rollback', ['--path' => self::MIGRATION]);

        $this->assertFalse(
            Schema::hasColumn('assignments', 'accepts_late_submission'),
            'Rollback harus menghapus kolomnya — kalau tidak, test ini tidak menguji apa pun.',
        );

        $ctx = $this->scaffoldStudentWithMaterial();

        // Insert MENTAH: model selalu menulis kolom baru, sedangkan di titik ini
        // kolomnya memang belum ada — persis seperti baris lama di produksi.
        $legacyId = (string) Str::uuid();
        DB::table('assignments')->insert([
            'id' => $legacyId,
            'material_id' => $ctx['material']->id,
            'title' => 'Tugas Lama',
            'deadline' => now()->subWeek(),
            'max_score' => 100,
            'order' => 1,
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('migrate', ['--path' => self::MIGRATION]);

        $this->assertFalse(
            (bool) Assignment::findOrFail($legacyId)->accepts_late_submission,
            'Tugas lama TIDAK boleh mendadak menerima keterlambatan.',
        );

        $this->assertTrue(
            (bool) $this->makeAssignment($ctx['material'])->refresh()->accepts_late_submission,
            'Tugas baru harus menerima keterlambatan.',
        );
    }
}
