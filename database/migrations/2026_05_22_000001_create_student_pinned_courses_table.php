<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_pinned_courses', function (Blueprint $table) {
            $table->uuid('student_id');
            $table->uuid('classroom_subject_id');
            $table->timestamp('pinned_at')->useCurrent();

            $table->primary(['student_id', 'classroom_subject_id']);
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('classroom_subject_id')->references('id')->on('classroom_subjects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_pinned_courses');
    }
};
