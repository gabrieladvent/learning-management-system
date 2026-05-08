<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('classroom_subject_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('text');
            $table->longText('content')->nullable();
            $table->string('topic')->nullable();
            $table->integer('order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('classroom_subject_id')->references('id')->on('classroom_subjects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
