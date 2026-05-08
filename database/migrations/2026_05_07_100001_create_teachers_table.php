<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('full_name');
            $table->string('nip')->nullable()->unique();
            $table->string('specialization')->nullable();
            $table->string('phone')->nullable();
            $table->string('nik')->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('gender');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
