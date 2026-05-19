<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->timestamp('graded_at')->nullable()->after('feedback');
        });

        // Backfill: kalau sudah ada score sebelumnya, anggap ter-grade pada saat updated_at terakhir.
        DB::table('assignment_submissions')
            ->whereNotNull('score')
            ->update(['graded_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn('graded_at');
        });
    }
};
