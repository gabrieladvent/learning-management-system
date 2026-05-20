<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tugas (Assignment) sekarang mengikuti pola ExamSubmission: field link_url
        // terpisah dari `content` Trix, sehingga siswa bisa kasih link referensi
        // tanpa harus menyisipkan di body essay.
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->string('link_url', 2048)->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn('link_url');
        });
    }
};
