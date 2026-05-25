<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('tracking_disclosure_seen_at')->nullable()->after('password_changed_at');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->boolean('tracking_opt_out')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tracking_disclosure_seen_at');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('tracking_opt_out');
        });
    }
};
