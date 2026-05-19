<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            // Di-set saat siswa edit submission yang sudah ada (NULL untuk first submit).
            // Dipakai oleh activity log: ada/tidaknya nilai = sinyal "Submission diperbarui".
            $table->timestamp('last_edited_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn('last_edited_at');
        });
    }
};
