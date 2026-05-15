<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('exam_id');
            $table->uuid('student_id');
            $table->text('content')->nullable();
            $table->string('link_url')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['exam_id', 'student_id']);
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_submissions');
    }
};
