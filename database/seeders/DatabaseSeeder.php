<?php

namespace Database\Seeders;

use App\Models\PerformanceEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $pjlp = User::updateOrCreate([
            'username' => 'pjlp',
        ], [
            'name' => 'D PJLP',
            'email' => 'pjlp@example.test',
            'role' => 'pjlp',
            'nip' => null,
            'jabatan' => 'PJLP',
            'unit' => 'Dinas Tenaga Kerja, Transmigrasi dan Energi Provinsi DKI Jakarta',
            'phone' => '08123456789',
            'address' => 'Jakarta',
            'password' => Hash::make('pjlp123'),
        ]);

        User::updateOrCreate([
            'username' => 'admin',
        ], [
            'name' => 'Admin Sekretariat',
            'email' => 'admin@example.test',
            'role' => 'admin',
            'jabatan' => 'Admin',
            'unit' => 'Dinas Tenaga Kerja, Transmigrasi dan Energi Provinsi DKI Jakarta',
            'password' => Hash::make('admin123'),
        ]);

        PerformanceEntry::updateOrCreate([
            'user_id' => $pjlp->id,
            'work_date' => now()->toDateString(),
            'sort_order' => 1,
        ], [
            'work_time' => '08.00',
            'task' => 'Apel pagi dan pengecekan agenda kerja harian.',
            'note' => 'Selesai',
        ]);

        PerformanceEntry::updateOrCreate([
            'user_id' => $pjlp->id,
            'work_date' => now()->toDateString(),
            'sort_order' => 2,
        ], [
            'work_time' => '09.00',
            'task' => 'Membantu administrasi surat masuk dan arsip dokumen.',
            'note' => 'Selesai',
        ]);
    }
}
