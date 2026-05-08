<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('classroom_subject_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('deadline');
            $table->decimal('max_score', 5, 2)->default(100);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('classroom_subject_id')->references('id')->on('classroom_subjects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
