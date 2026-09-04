<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sakelar per-tugas: boleh dikumpulkan setelah deadline atau tidak.
     *
     * Default kolom `true` supaya tugas BARU menerima keterlambatan (lalu
     * ditandai `is_late`) — perilaku yang diinginkan ke depan.
     *
     * Baris yang SUDAH ADA sengaja di-backfill `false`. Kalau ikut `true`,
     * begitu deploy jalan semua tugas lama — termasuk yang deadline-nya sudah
     * lewat berminggu-minggu — mendadak terbuka lagi, dan pengumpulan yang
     * masuk ikut ke penilaian guru. Itu perubahan diam-diam pada nilai siswa.
     * Guru bisa menyalakannya sendiri per tugas kalau memang mau.
     */
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('accepts_late_submission')->default(true)->after('deadline');
        });

        DB::table('assignments')->update(['accepts_late_submission' => false]);
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('accepts_late_submission');
        });
    }
};
