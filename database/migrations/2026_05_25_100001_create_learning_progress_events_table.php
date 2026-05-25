<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_progress_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('student_id');
            $table->string('trackable_type');
            $table->uuid('trackable_id');
            $table->uuid('classroom_subject_id');
            $table->uuid('session_id');
            $table->string('event');
            $table->timestamp('occurred_at');
            $table->timestamp('received_at', 3);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'trackable_type', 'trackable_id', 'occurred_at'], 'lpe_student_trackable_occurred_idx');
            $table->index(['classroom_subject_id', 'occurred_at'], 'lpe_cls_subject_occurred_idx');
            $table->index('session_id', 'lpe_session_idx');
            $table->index('received_at', 'lpe_received_idx');

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('classroom_subject_id')->references('id')->on('classroom_subjects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_progress_events');
    }
};
