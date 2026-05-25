<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_progress_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->string('trackable_type');
            $table->uuid('trackable_id');
            $table->uuid('classroom_subject_id');
            $table->uuid('session_id');
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('active_seconds')->default(0);
            $table->unsignedInteger('idle_seconds')->default(0);
            $table->string('end_reason')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'trackable_type', 'trackable_id', 'session_id'], 'lps_unique_session');
            $table->index(['student_id', 'classroom_subject_id', 'started_at'], 'lps_student_cls_started_idx');
            $table->index(['trackable_type', 'trackable_id', 'started_at'], 'lps_trackable_started_idx');
            $table->index('last_seen_at', 'lps_last_seen_idx');

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('classroom_subject_id')->references('id')->on('classroom_subjects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_progress_sessions');
    }
};
