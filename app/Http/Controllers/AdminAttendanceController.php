<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\ReportJob;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AdminAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $summaryRows = $this->attendanceRows($date, $search);
        $rows = $this->filterRowsByStatus($summaryRows, $status);

        return view('admin.attendance.index', [
            'date' => $date,
            'rows' => $rows,
            'status' => $status,
            'search' => $search,
            'summary' => [
                'hadir' => $summaryRows->where('status', 'hadir')->count(),
                'dinas_luar' => $summaryRows->where('status', 'dinas_luar')->count(),
                'izin' => $summaryRows->where('status', 'izin')->count(),
                'alfa' => $summaryRows->where('status', 'alfa')->count(),
                'belum_lengkap' => $summaryRows->where('status', 'belum_lengkap')->count(),
            ],
        ]);
    }

    public function exportExcel(Request $request)
    {
        $date = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));
        $rows = $this->filterRowsByStatus($this->attendanceRows($date, $search), $status);
        $statusLabels = $this->statusLabels();
        $fileName = 'rekap-absensi-pjlp-' . $date->format('Y-m-d') . '.xls';

        return response()->streamDownload(function () use ($rows, $date, $statusLabels) {
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
            echo '<tr><td class="title" colspan="12">Rekap Absensi PJLP ' . e($this->dateLabel($date)) . '</td></tr>';
            echo '<tr>';

            foreach (['No', 'Nama Pegawai', 'NIP PJLP', 'NIK', 'Jabatan / Bidang', 'Tanggal', 'Absen Awal', 'Absen Akhir', 'Dinas Luar', 'Status', 'Lokasi Terakhir', 'Catatan / Tujuan'] as $heading) {
                echo '<th>' . e($heading) . '</th>';
            }

            echo '</tr>';

            foreach ($rows as $index => $row) {
                $user = $row['user'];
                $records = $row['records'];
                $start = $records->get(AttendanceRecord::TYPE_START);
                $end = $records->get(AttendanceRecord::TYPE_END);
                $field = $records->get(AttendanceRecord::TYPE_FIELD);
                $latest = $row['latestRecord'];
                $leave = $row['leave'];

                echo '<tr>';
                echo '<td class="center">' . e($index + 1) . '</td>';
                echo '<td>' . e($user->name) . '</td>';
                echo '<td class="center" style="mso-number-format:\\@">' . e($user->nip ?: '-') . '</td>';
                echo '<td class="center" style="mso-number-format:\\@">' . e($user->nik ?: '-') . '</td>';
                echo '<td>' . e($user->jabatan ?: 'PJLP') . '</td>';
                echo '<td class="center">' . e($this->dateLabel($date)) . '</td>';
                echo '<td class="center">' . e($this->timeCell($start)) . '</td>';
                echo '<td class="center">' . e($this->timeCell($end)) . '</td>';
                echo '<td class="center">' . e($this->timeCell($field)) . '</td>';
                echo '<td class="center">' . e($statusLabels[$row['status']] ?? $row['status']) . '</td>';
                echo '<td class="wrap">' . e($this->locationText($latest)) . '</td>';
                echo '<td class="wrap">' . e($field?->note ?: ($latest?->note ?: ($leave?->reason ?: '-'))) . '</td>';
                echo '</tr>';
            }

            echo '</table></body></html>';
        }, $fileName, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    /* ===== EXPORT BULANAN (ASYNC) ===== */

    /**
     * Tampilkan halaman progress export langsung.
     */
    public function exportMonthly(Request $request)
    {
        $monthNumber = max(1, min(12, (int) $request->query('month', now()->month)));
        $yearNumber = (int) $request->query('year', now()->year);

        return view('admin.attendance.export-progress', [
            'month' => $monthNumber,
            'year' => $yearNumber,
            'reportJob' => null,
        ]);
    }

    /**
     * Mulai export baru via AJAX POST.
     */
    public function startExport(Request $request)
    {
        $monthNumber = max(1, min(12, (int) $request->input('month', now()->month)));
        $yearNumber = (int) $request->input('year', now()->year);

        $users = User::query()
            ->select('id', 'name', 'nip', 'nik', 'jabatan')
            ->where('role', 'pjlp')
            ->orderBy('name')
            ->get();

        if ($users->isEmpty()) {
            return response()->json(['status' => 'failed', 'message' => 'Tidak ada user PJLP.'], 400);
        }

        $reportJob = ReportJob::create([
            'user_id' => Auth::id(),
            'type' => 'monthly_attendance',
            'status' => 'pending',
            'total_users' => $users->count(),
            'processed_users' => 0,
            'month' => $monthNumber,
            'year' => $yearNumber,
            'current_user_name' => null,
        ]);

        return response()->json([
            'status' => 'started',
            'report_job_id' => $reportJob->id,
            'total_users' => $reportJob->total_users,
        ]);
    }

    /**
     * Proses 1 user untuk ReportJob via AJAX.
     */
    public function processStep(ReportJob $reportJob, Request $request)
    {
        if ($reportJob->isFinished()) {
            return response()->json([
                'status' => $reportJob->status,
                'progress' => $reportJob->progressPercent(),
                'message' => $reportJob->status === 'completed' ? 'Selesai! ZIP siap diunduh.' : 'Gagal.',
            ]);
        }

        if ($reportJob->processed_users >= $reportJob->total_users) {
            $reportJob->update(['status' => 'completed', 'current_user_name' => null]);
            return response()->json([
                'status' => 'completed',
                'progress' => 100,
                'message' => 'Selesai! ZIP siap diunduh.',
            ]);
        }

        $month = Carbon::create($reportJob->year, $reportJob->month, 1)->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $processed = $reportJob->processed_users;

        $users = User::query()
            ->select('id', 'name', 'nip', 'nik', 'jabatan')
            ->where('role', 'pjlp')
            ->orderBy('name')
            ->get();

        $user = $users->skip($processed)->first();
        if (!$user) {
            $reportJob->update(['status' => 'failed', 'error_message' => 'User tidak ditemukan.']);
            return response()->json(['status' => 'failed', 'progress' => $reportJob->progressPercent(), 'message' => 'Gagal: user tidak ditemukan.']);
        }

        $reportJob->update(['status' => 'processing', 'current_user_name' => $user->name]);

        try {
            set_time_limit(30);

            // Ambil data untuk user ini saja — ringan
            $records = AttendanceRecord::query()
                ->select('id', 'user_id', 'work_date', 'type', 'recorded_at', 'latitude', 'longitude', 'address', 'note')
                ->where('user_id', $user->id)
                ->whereDate('work_date', '>=', $month)
                ->whereDate('work_date', '<=', $monthEnd)
                ->get()
                ->groupBy(fn ($r) => $r->user_id . '|' . $r->work_date->toDateString());

            $leaves = LeaveRequest::query()
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->where('user_id', $user->id)
                ->whereDate('start_date', '<=', $monthEnd)
                ->whereDate('end_date', '>=', $month)
                ->get(['user_id', 'start_date', 'end_date']);

            $leaveDates = [];
            foreach ($leaves as $leave) {
                $period = CarbonPeriod::create($leave->start_date, $leave->end_date);
                foreach ($period as $d) {
                    $leaveDates[$d->toDateString()] = true;
                }
            }

            $days = [];
            for ($d = 1; $d <= $month->daysInMonth; $d++) {
                $date = Carbon::create($reportJob->year, $reportJob->month, $d);
                if (!$date->isWeekend()) {
                    $days[] = $date;
                }
            }

            $monthLabel = $this->monthLabel($month);

            $html = '<!doctype html><html><head><meta charset="utf-8">';
            $html .= '<style>
                table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 10px; }
                th { background: #dff6e8; font-weight: bold; text-align: center; }
                th, td { border: 1px solid #333; padding: 3px 4px; vertical-align: top; }
                .center { text-align: center; }
                .start { mso-number-format:"\@"; white-space: normal; }
            </style></head><body>';
            $html .= '<table>';
            $html .= '<tr><th>No</th><th>Nama Pegawai</th><th>NIP PJLP</th><th>NIK</th><th>Jabatan / Bidang</th>';
            $html .= '<th>Tanggal</th><th>Absen Awal</th><th>Absen Akhir</th><th>Dinas Luar</th><th>Status</th><th>Lokasi Terakhir</th><th>Catatan / Tujuan</th></tr>';

            foreach ($days as $index => $day) {
                $dateStr = $day->toDateString();
                $key = $user->id . '|' . $dateStr;
                $dayRecords = isset($records[$key]) ? $records[$key]->keyBy('type') : collect();
                $isLeave = isset($leaveDates[$dateStr]);

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

                $html .= '<tr>';
                $html .= '<td class="center">' . ($index + 1) . '</td>';
                $html .= '<td>' . e($user->name) . '</td>';
                $html .= '<td class="center start">' . e($user->nip ?: '-') . '</td>';
                $html .= '<td class="center start">' . e($user->nik ?: '-') . '</td>';
                $html .= '<td>' . e($user->jabatan ?: 'PJLP') . '</td>';
                $html .= '<td class="center">' . e($day->translatedFormat('d F Y')) . '</td>';
                $html .= '<td class="center">' . ($start ? e($start->recorded_at->format('H:i')) . ' WIB' : '-') . '</td>';
                $html .= '<td class="center">' . ($end ? e($end->recorded_at->format('H:i')) . ' WIB' : '-') . '</td>';
                $html .= '<td class="center">' . ($field ? e($field->recorded_at->format('H:i')) . ' WIB' : '-') . '</td>';
                $html .= '<td class="center">' . e($statusLabel) . '</td>';
                $html .= '<td class="start">' . e($location) . '</td>';
                $html .= '<td class="start">' . e($note) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</table></body></html>';

            // Simpan ke ZIP
            $zipDir = storage_path('app/exports');
            if (!is_dir($zipDir)) {
                mkdir($zipDir, 0775, true);
            }
            $zipPath = $zipDir . '/rekap-absensi-bulanan-' . $month->format('Y-m') . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                throw new \RuntimeException('Gagal membuka ZIP.');
            }
            $userFileName = $user->name . ' - ' . $monthLabel . '.xls';
            $zip->addFromString($userFileName, $html);
            $zip->close();

            $newProcessed = $processed + 1;
            $finished = $newProcessed >= $reportJob->total_users;
            $reportJob->update([
                'processed_users' => $newProcessed,
                'status' => $finished ? 'completed' : 'pending',
                'current_user_name' => $finished ? null : $user->name,
            ]);

            if ($finished) {
                $reportJob->update([
                    'zip_path' => $zipPath,
                    'zip_name' => 'rekap-absensi-bulanan-' . $month->format('Y-m') . '.zip',
                ]);
            }

            return response()->json([
                'status' => $finished ? 'completed' : 'pending',
                'progress' => $reportJob->progressPercent(),
                'current_user' => $user->name,
                'processed_users' => $reportJob->processed_users,
                'total_users' => $reportJob->total_users,
                'message' => $finished ? 'Selesai! ZIP siap diunduh.' : $user->name . ' selesai diproses.',
            ]);
        } catch (\Throwable $e) {
            $reportJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'failed',
                'progress' => $reportJob->progressPercent(),
                'message' => 'Gagal: ' . $e->getMessage(),
            ]);
        }
    }

    public function downloadReportZip(ReportJob $reportJob)
    {
        abort_unless($reportJob->status === 'completed' && $reportJob->zip_path && file_exists($reportJob->zip_path), 404);

        return response()->download($reportJob->zip_path, $reportJob->zip_name)->deleteFileAfterSend(true);
    }

    /* ===== SHOW / EDIT / UPDATE ===== */

    public function show(AttendanceRecord $attendanceRecord)
    {
        $records = AttendanceRecord::query()
            ->where('user_id', $attendanceRecord->user_id)
            ->whereDate('work_date', $attendanceRecord->work_date)
            ->orderBy('recorded_at')
            ->get();

        return view('admin.attendance.show', [
            'attendanceRecord' => $attendanceRecord->load('user'),
            'records' => $records,
        ]);
    }

    public function edit(AttendanceRecord $attendanceRecord)
    {
        return view('admin.attendance.form', [
            'attendanceRecord' => $attendanceRecord->load('user'),
            'labels' => AttendanceRecord::labels(),
        ]);
    }

    public function update(Request $request, AttendanceRecord $attendanceRecord)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(AttendanceRecord::labels()))],
            'work_date' => ['required', 'date'],
            'recorded_time' => ['required', 'date_format:H:i'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'selfie' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:12288'],
        ], [
            'selfie.mimes' => 'File selfie harus berupa gambar JPG, PNG, WEBP, HEIC, atau HEIF.',
            'selfie.max' => 'Ukuran foto selfie maksimal 12 MB.',
        ]);

        $this->ensureUniqueSlot($attendanceRecord, $data);

        $recordedAt = Carbon::parse($data['work_date'] . ' ' . $data['recorded_time']);

        if ($request->hasFile('selfie')) {
            $data['selfie_path'] = $this->replaceSelfie($attendanceRecord, $request);
        }

        unset($data['recorded_time'], $data['selfie']);

        $attendanceRecord->update($data + [
            'recorded_at' => $recordedAt,
        ]);

        return redirect()
            ->route('admin.attendance.show', $attendanceRecord)
            ->with('status', 'Data absensi berhasil diperbarui.');
    }

    private function ensureUniqueSlot(AttendanceRecord $attendanceRecord, array $data): void
    {
        $exists = AttendanceRecord::query()
            ->where('user_id', $attendanceRecord->user_id)
            ->whereDate('work_date', $data['work_date'])
            ->where('type', $data['type'])
            ->where('id', '!=', $attendanceRecord->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'type' => 'User ini sudah memiliki absensi dengan jenis yang sama pada tanggal tersebut.',
            ]);
        }
    }

    private function replaceSelfie(AttendanceRecord $attendanceRecord, Request $request): string
    {
        if ($attendanceRecord->selfie_path) {
            Storage::disk('public')->delete($attendanceRecord->selfie_path);
        }

        return $request->file('selfie')->store('attendance-selfies', 'public');
    }

    /* ===== HELPERS ===== */

    private function attendanceRows(Carbon $date, string $search)
    {
        $users = User::query()
            ->where('role', 'pjlp')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('jabatan', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        $records = AttendanceRecord::query()
            ->with('user')
            ->whereDate('work_date', $date)
            ->get()
            ->groupBy('user_id');

        $leaves = LeaveRequest::query()
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get()
            ->keyBy('user_id');

        return $users->map(function (User $user) use ($records, $leaves) {
            $userRecords = $records->get($user->id, collect())->keyBy('type');
            $leave = $leaves->get($user->id);
            $rowStatus = $this->statusFor($userRecords, $leave);
            $latestRecord = $userRecords->sortByDesc('recorded_at')->first();

            return [
                'user' => $user,
                'records' => $userRecords,
                'leave' => $leave,
                'status' => $rowStatus,
                'latestRecord' => $latestRecord,
            ];
        });
    }

    private function filterRowsByStatus($rows, ?string $status)
    {
        if (in_array($status, array_keys($this->statusLabels()), true)) {
            return $rows->filter(fn ($row) => $row['status'] === $status)->values();
        }

        return $rows;
    }

    private function statusLabels(): array
    {
        return [
            'hadir' => 'Hadir',
            'dinas_luar' => 'Dinas Luar',
            'izin' => 'Izin / Sakit',
            'alfa' => 'Alfa',
            'belum_lengkap' => 'Belum Lengkap',
        ];
    }

    private function monthLabel(Carbon $date): string
    {
        $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        return $monthNames[$date->month] . ' ' . $date->year;
    }

    private function dateLabel(Carbon $date): string
    {
        $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        return $dayNames[$date->dayOfWeek] . ', ' . $date->format('d') . ' ' . $monthNames[$date->month] . ' ' . $date->year;
    }

    private function timeCell(?AttendanceRecord $record): string
    {
        return $record ? $record->recorded_at->format('H:i') . ' WIB' : '-';
    }

    private function locationText(?AttendanceRecord $record): string
    {
        if (! $record) {
            return '-';
        }

        if ($record->address) {
            return $record->address;
        }

        if ($record->latitude && $record->longitude) {
            return 'Lat ' . $record->latitude . ', Lng ' . $record->longitude;
        }

        return 'Koordinat tersimpan';
    }

    private function statusFor($records, ?LeaveRequest $leave): string
    {
        if ($leave) {
            return 'izin';
        }

        if ($records->has(AttendanceRecord::TYPE_FIELD)) {
            return 'dinas_luar';
        }

        if ($records->has(AttendanceRecord::TYPE_END)) {
            return 'hadir';
        }

        if ($records->has(AttendanceRecord::TYPE_START)) {
            return 'belum_lengkap';
        }

        return 'alfa';
    }
}