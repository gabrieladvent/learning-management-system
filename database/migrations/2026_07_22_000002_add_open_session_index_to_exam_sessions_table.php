<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk scan "open session" yang dijalankan tiap 2 menit oleh
 * exam:auto-submit-expired: WHERE submitted_at IS NULL AND started_at IS NOT NULL.
 * Tanpa index ini, query full-scan tabel exam_sessions tiap cron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->index(['submitted_at', 'started_at'], 'exam_sessions_open_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropIndex('exam_sessions_open_idx');
        });
    }
};
