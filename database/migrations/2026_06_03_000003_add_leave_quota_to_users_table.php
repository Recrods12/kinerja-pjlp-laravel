<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('annual_leave_quota')->default(12)->after('address');
            $table->unsignedTinyInteger('annual_leave_remaining')->default(12)->after('annual_leave_quota');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['annual_leave_quota', 'annual_leave_remaining']);
        });
    }
};
