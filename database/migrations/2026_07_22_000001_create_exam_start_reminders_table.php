<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log reminder "ujian akan dimulai" per (exam, student).
 *
 * Mencegah SendExamStartReminders mengirim reminder yang sama berulang kali:
 * job berjalan tiap 15 menit dengan window `now..now+1h`, jadi tanpa log ini
 * satu siswa bisa dapat reminder yang sama sampai ~4×. Unique key menjamin
 * satu reminder per siswa per ujian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_start_reminders', function (Blueprint $table) {
            $table->id();
            $table->uuid('exam_id');
            $table->uuid('student_id');
            $table->timestamp('reminded_at')->useCurrent();

            $table->unique(['exam_id', 'student_id']);
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_start_reminders');
    }
};
