<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel token Sanctum.
     *
     * BUKAN salinan migrasi bawaan Sanctum. Bawaannya memakai `morphs()` yang
     * membuat `tokenable_id` jadi unsignedBigInteger, sedangkan seluruh tabel di
     * project ini (termasuk `students`) memakai UUID sebagai PK. Dengan migrasi
     * bawaan, token akan tersimpan tapi tidak akan pernah cocok saat resolusi —
     * gagal diam-diam, bukan error yang kelihatan.
     *
     * Karena itu dipakai `uuidMorphs()`.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            // PK sengaja auto-increment (bukan uuid seperti tabel domain):
            // model PersonalAccessToken bawaan Sanctum tidak memakai HasUuids,
            // jadi PK uuid akan gagal saat insert. Ini tabel framework, bukan
            // tabel domain — sama seperti `media` dari Spatie.
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
