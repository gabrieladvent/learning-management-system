<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 4 carry-over: timeline submission tugas sekarang di-derive dari activity_log
        // (spatie/laravel-activitylog). Kolom last_edited_at tidak diperlukan lagi.
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn('last_edited_at');
        });

        // Sejajarkan dengan AssignmentSubmission supaya state "graded" punya timestamp eksplisit.
        Schema::table('exam_submissions', function (Blueprint $table) {
            $table->timestamp('graded_at')->nullable()->after('feedback');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->timestamp('last_edited_at')->nullable()->after('submitted_at');
        });

        Schema::table('exam_submissions', function (Blueprint $table) {
            $table->dropColumn('graded_at');
        });
    }
};
