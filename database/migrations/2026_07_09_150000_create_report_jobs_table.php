<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50); // 'monthly_attendance', 'monthly_report'
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->integer('total_users')->default(0);
            $table->integer('processed_users')->default(0);
            $table->string('zip_path')->nullable();
            $table->string('zip_name')->nullable();
            $table->integer('month')->nullable();
            $table->integer('year')->nullable();
            $table->text('current_user_name')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_jobs');
    }
};