<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('security_team', ['A', 'B', 'C'])->nullable()->after('jabatan');
            $table->date('security_cycle_start_date')->nullable()->after('security_team');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['security_team', 'security_cycle_start_date']);
        });
    }
};
