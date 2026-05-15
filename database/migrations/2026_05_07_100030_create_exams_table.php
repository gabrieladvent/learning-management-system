<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('mode')->default('online_quiz');
            $table->dateTime('starts_at');
            $table->integer('duration_minutes');
            $table->decimal('max_score', 5, 2)->default(100);
            $table->boolean('shuffle_questions')->default(false);
            $table->json('allowed_file_types')->nullable();
            $table->integer('max_file_size_mb')->default(10);
            $table->string('status')->default('draft');
            $table->integer('order')->default(0);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('material_id')->references('id')->on('materials')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
