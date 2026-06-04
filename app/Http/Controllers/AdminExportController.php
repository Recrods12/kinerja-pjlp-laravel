<?php

namespace App\Http\Controllers;

use App\Models\PerformanceEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminExportController extends Controller
{
    public function csv(Request $request)
    {
        $month = Carbon::create(
            (int) $request->query('year', now()->year),
            (int) $request->query('month', now()->month),
            1
        );
        $jobRoles = ['Driver', 'Kebersihan', 'Keamanan', 'Mekanikal Enginer', 'Pelayanan Umum'];
        $selectedRole = $request->query('jabatan');
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->where('role', 'pjlp')
            ->when(in_array($selectedRole, $jobRoles, true), fn ($query) => $query->where('jabatan', $selectedRole))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('jabatan', 'like', "%{$search}%")
                        ->orWhere('unit', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        $entries = PerformanceEntry::query()
            ->with('user')
            ->whereIn('user_id', $users->pluck('id'))
            ->whereBetween('work_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('work_date')
            ->orderBy('user_id')
            ->orderBy('sort_order')
            ->get();

        $fileName = 'kinerja-pjlp-' . $month->format('Y-m') . '.xls';

        return response()->streamDownload(function () use ($entries, $month) {
            echo '<!doctype html><html><head><meta charset="utf-8">';
            echo '<style>
                table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 12px; }
                th { background: #dff6e8; font-weight: bold; text-align: center; }
                th, td { border: 1px solid #333; padding: 6px; vertical-align: top; }
                .center { text-align: center; }
                .wrap { mso-number-format:"\@"; white-space: normal; }
                .title { font-size: 16px; font-weight: bold; border: 0; padding: 8px 0; }
            </style>';
            echo '</head><body>';
            echo '<table>';
            echo '<tr><td class="title" colspan="8">Rekap Kinerja PJLP Bulan ' . e($month->translatedFormat('F Y')) . '</td></tr>';
            echo '<tr>';
            foreach (['Nama', 'NIP PJLP', 'Jabatan', 'Tanggal', 'Hari', 'Jam Kerja', 'Uraian Tugas', 'Keterangan'] as $heading) {
                echo '<th>' . e($heading) . '</th>';
            }
            echo '</tr>';

            foreach ($entries as $entry) {
                echo '<tr>';
                echo '<td>' . e($entry->user->name) . '</td>';
                echo '<td class="center">' . e($entry->user->nip) . '</td>';
                echo '<td>' . e($entry->user->jabatan) . '</td>';
                echo '<td class="center">' . e($entry->work_date->translatedFormat('d F Y')) . '</td>';
                echo '<td class="center">' . e($entry->work_date->translatedFormat('l')) . '</td>';
                echo '<td class="center">' . e($entry->work_time) . '</td>';
                echo '<td class="wrap">' . e($entry->task) . '</td>';
                echo '<td>' . e($entry->note) . '</td>';
                echo '</tr>';
            }

            echo '</table></body></html>';
        }, $fileName, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }
}
