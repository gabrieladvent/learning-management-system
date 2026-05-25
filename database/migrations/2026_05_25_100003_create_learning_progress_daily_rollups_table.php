<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_progress_daily_rollups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('classroom_subject_id');
            $table->date('date');
            $table->unsignedInteger('material_seconds')->default(0);
            $table->unsignedInteger('assignment_seconds')->default(0);
            $table->unsignedInteger('exam_seconds')->default(0);
            $table->unsignedInteger('materials_opened')->default(0);
            $table->unsignedInteger('assignments_worked')->default(0);
            $table->unsignedInteger('exams_attempted')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'classroom_subject_id', 'date'], 'lpdr_unique_day');
            $table->index(['classroom_subject_id', 'date'], 'lpdr_cls_date_idx');

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('classroom_subject_id')->references('id')->on('classroom_subjects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_progress_daily_rollups');
    }
};
