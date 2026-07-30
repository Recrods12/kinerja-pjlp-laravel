<?php

namespace App\Jobs;

use App\Models\AttendanceRecord;
use App\Models\Export;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ExportMonthlyAttendance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Export $export,
        public int $month,
        public int $year,
    ) {}

    public function handle(): void
    {
        $this->export->update(['status' => 'processing']);

        try {
            $month = Carbon::create($this->year, $this->month, 1)->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $users = User::query()
                ->select('id', 'name', 'nip', 'nik', 'jabatan')
                ->where('role', 'pjlp')
                ->orderBy('name')
                ->get();

            $allRecords = AttendanceRecord::query()
                ->select('id', 'user_id', 'work_date', 'type', 'recorded_at', 'latitude', 'longitude', 'address', 'note')
                ->whereDate('work_date', '>=', $month)
                ->whereDate('work_date', '<=', $monthEnd)
                ->get()
                ->groupBy(fn ($r) => $r->user_id . '|' . $r->work_date->toDateString());

            $allLeaves = LeaveRequest::query()
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->whereDate('start_date', '<=', $monthEnd)
                ->whereDate('end_date', '>=', $month)
                ->get(['user_id', 'start_date', 'end_date']);

            $leaveDates = [];
            foreach ($allLeaves as $leave) {
                $period = CarbonPeriod::create($leave->start_date, $leave->end_date);
                foreach ($period as $d) {
                    $leaveDates[$leave->user_id][$d->toDateString()] = true;
                }
            }
            unset($allLeaves);

            $days = [];
            for ($d = 1; $d <= $month->daysInMonth; $d++) {
                $date = Carbon::create($this->year, $this->month, $d);
                if (!$date->isWeekend()) {
                    $days[] = $date;
                }
            }

            $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
            $monthLabel = $monthNames[$month->month] . ' ' . $month->year;
            $fileName = 'rekap-absensi-bulanan-' . $month->format('Y-m') . '.xls';
            $storagePath = 'exports/' . $this->export->id . '-' . $fileName;

            $out = '<!doctype html><html><head><meta charset="utf-8">';
            $out .= '<style>
                table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 12px; }
                th { background: #dff6e8; font-weight: bold; text-align: center; }
                th, td { border: 1px solid #333; padding: 4px 5px; vertical-align: top; }
                .center { text-align: center; }
                .start { mso-number-format:"\@"; white-space: normal; }
                .title { font-size: 14px; font-weight: bold; border: 0; padding: 6px 0; }
            </style></head><body>';
            $out .= '<table>';
            $out .= '<tr><td class="title" colspan="12">Rekap Absensi Bulanan PJLP ' . e($monthLabel) . '</td></tr>';
            $out .= '<tr><th>No</th><th>Nama Pegawai</th><th>NIP PJLP</th><th>NIK</th><th>Jabatan / Bidang</th><th>Tanggal</th><th>Absen Awal</th><th>Absen Akhir</th><th>Dinas Luar</th><th>Status</th><th>Lokasi Terakhir</th><th>Catatan / Tujuan</th></tr>';

            $index = 0;
            foreach ($users as $user) {
                foreach ($days as $day) {
                    $index++;
                    $dateStr = $day->toDateString();
                    $key = $user->id . '|' . $dateStr;
                    $dayRecords = isset($allRecords[$key]) ? $allRecords[$key]->keyBy('type') : collect();
                    $isLeave = isset($leaveDates[$user->id][$dateStr]);

                    $start = $dayRecords->get(AttendanceRecord::TYPE_START);
                    $end = $dayRecords->get(AttendanceRecord::TYPE_END);
                    $field = $dayRecords->get(AttendanceRecord::TYPE_FIELD);
                    $latest = $field ?: ($end ?: $start);

                    $statusLabel = match (true) {
                        $isLeave => 'Izin / Sakit',
                        (bool) $field => 'Dinas Luar',
                        (bool) $end => 'Hadir',
                        (bool) $start => 'Belum Lengkap',
                        default => 'Alfa',
                    };

                    $note = $field?->note ?: ($latest?->note ?: '-');
                    $location = $latest
                        ? ($latest->address ?: ($latest->latitude ? 'Lat ' . $latest->latitude . ', Lng ' . $latest->longitude : '-'))
                        : '-';

                    $out .= '<tr>';
                    $out .= '<td class="center">' . $index . '</td>';
                    $out .= '<td>' . e($user->name) . '</td>';
                    $out .= '<td class="center start">' . e($user->nip ?: '-') . '</td>';
                    $out .= '<td class="center start">' . e($user->nik ?: '-') . '</td>';
                    $out .= '<td>' . e($user->jabatan ?: 'PJLP') . '</td>';
                    $out .= '<td class="center">' . e($day->translatedFormat('l, d F Y')) . '</td>';
                    $out .= '<td class="center">' . ($start ? e($start->recorded_at->format('H:i')) . ' WIB' : '-') . '</td>';
                    $out .= '<td class="center">' . ($end ? e($end->recorded_at->format('H:i')) . ' WIB' : '-') . '</td>';
                    $out .= '<td class="center">' . ($field ? e($field->recorded_at->format('H:i')) . ' WIB' : '-') . '</td>';
                    $out .= '<td class="center">' . e($statusLabel) . '</td>';
                    $out .= '<td class="start">' . e($location) . '</td>';
                    $out .= '<td class="start">' . e($note) . '</td>';
                    $out .= '</tr>';
                }
            }

            $out .= '</table></body></html>';

            Storage::disk('local')->put($storagePath, $out);

            $this->export->update([
                'status' => 'completed',
                'file_path' => $storagePath,
                'file_name' => $fileName,
            ]);
        } catch (\Throwable $e) {
            $this->export->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}