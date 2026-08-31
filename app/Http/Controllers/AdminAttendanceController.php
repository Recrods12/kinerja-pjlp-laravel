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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
        $fileName = 'rekap-absensi-pjlp-' . $date->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($rows, $date, $statusLabels) {
            $spreadsheet = $this->newAttendanceSpreadsheet('Rekap Absensi PJLP ' . $this->dateLabel($date));
            $sheet = $spreadsheet->getActiveSheet();

            foreach ($rows as $index => $row) {
                $user = $row['user'];
                $records = $row['records'];
                $start = $records->get(AttendanceRecord::TYPE_START);
                $end = $records->get(AttendanceRecord::TYPE_END);
                $field = $records->get(AttendanceRecord::TYPE_FIELD);
                $latest = $row['latestRecord'];
                $leave = $row['leave'];

                $excelRow = $index + 3;
                $sheet->fromArray([
                    $index + 1,
                    $user->name,
                    $user->nip ?: '-',
                    $user->nik ?: '-',
                    $user->jabatan ?: 'PJLP',
                    $this->dateLabel($date),
                    $this->timeCell($start),
                    $this->timeCell($end),
                    $this->timeCell($field),
                    $statusLabels[$row['status']] ?? $row['status'],
                    $this->locationText($latest),
                    $field?->note ?: ($latest?->note ?: ($leave?->reason ?: '-')),
                    '', '', '',
                ], null, 'A' . $excelRow);
                $this->addSelfieToSheet($sheet, $start, 'M', $excelRow);
                $this->addSelfieToSheet($sheet, $end, 'N', $excelRow);
                $this->addSelfieToSheet($sheet, $field, 'O', $excelRow);
            }

            $this->finishAttendanceSpreadsheet($sheet, $rows->count() + 2);
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
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
            'format' => 'zip',
        ]);
    }

    public function exportMonthlyAll(Request $request)
    {
        $monthNumber = max(1, min(12, (int) $request->query('month', now()->month)));
        $yearNumber = (int) $request->query('year', now()->year);

        return view('admin.attendance.export-progress', [
            'month' => $monthNumber,
            'year' => $yearNumber,
            'reportJob' => null,
            'format' => 'workbook',
        ]);
    }

    /**
     * Mulai export baru via AJAX POST.
     */
    public function startExport(Request $request)
    {
        $monthNumber = max(1, min(12, (int) $request->input('month', now()->month)));
        $yearNumber = (int) $request->input('year', now()->year);
        $format = $request->input('format') === 'workbook' ? 'workbook' : 'zip';

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
            'type' => $format === 'workbook' ? 'monthly_attendance_workbook' : 'monthly_attendance',
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
        abort_unless($reportJob->user_id === Auth::id(), 403);

        if ($reportJob->isFinished()) {
            return response()->json([
                'status' => $reportJob->status,
                'progress' => $reportJob->progressPercent(),
                'message' => $reportJob->status === 'completed' ? 'Selesai! File siap diunduh.' : 'Gagal.',
            ]);
        }

        if ($reportJob->processed_users >= $reportJob->total_users) {
            $reportJob->update(['status' => 'completed', 'current_user_name' => null]);
            return response()->json([
                'status' => 'completed',
                'progress' => 100,
                'message' => 'Selesai! File siap diunduh.',
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
                ->select('id', 'user_id', 'work_date', 'type', 'recorded_at', 'latitude', 'longitude', 'address', 'selfie_path', 'note')
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

            $spreadsheet = null;
            if ($reportJob->type !== 'monthly_attendance_workbook') {
                $spreadsheet = $this->newAttendanceSpreadsheet('Rekap Absensi ' . $user->name . ' - ' . $monthLabel);
                $sheet = $spreadsheet->getActiveSheet();

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

                    $excelRow = $index + 3;
                    $sheet->fromArray([
                        $index + 1, $user->name, $user->nip ?: '-', $user->nik ?: '-',
                        $user->jabatan ?: 'PJLP', $day->translatedFormat('d F Y'),
                        $start ? $start->recorded_at->format('H:i') . ' WIB' : '-',
                        $end ? $end->recorded_at->format('H:i') . ' WIB' : '-',
                        $field ? $field->recorded_at->format('H:i') . ' WIB' : '-',
                        $statusLabel, $location, $note, '', '', '',
                    ], null, 'A' . $excelRow);
                    $this->addSelfieToSheet($sheet, $start, 'M', $excelRow);
                    $this->addSelfieToSheet($sheet, $end, 'N', $excelRow);
                    $this->addSelfieToSheet($sheet, $field, 'O', $excelRow);
                }

                $this->finishAttendanceSpreadsheet($sheet, count($days) + 2);
            }
            $exportDir = storage_path('app/exports');
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0775, true);
            }

            if ($reportJob->type === 'monthly_attendance_workbook') {
                $exportPath = $exportDir . '/rekap-absensi-semua-' . $month->format('Y-m') . '-job-' . $reportJob->id . '.xlsx';
                $this->appendUserToMonthlyWorkbook($exportPath, $user, $records, $leaveDates, $month, $monthLabel, $processed === 0);
                $exportName = 'rekap-absensi-semua-' . $month->format('Y-m') . '.xlsx';
            } else {
                $tempPath = tempnam(sys_get_temp_dir(), 'attendance-');
                (new Xlsx($spreadsheet))->save($tempPath);
                $spreadsheet->disconnectWorksheets();

                $exportPath = $exportDir . '/rekap-absensi-bulanan-' . $month->format('Y-m') . '-job-' . $reportJob->id . '.zip';
                $zip = new ZipArchive();
                if ($zip->open($exportPath, ZipArchive::CREATE) !== true) {
                    throw new \RuntimeException('Gagal membuka ZIP.');
                }
                $zip->addFile($tempPath, $user->name . ' - ' . $monthLabel . '.xlsx');
                $zip->close();
                @unlink($tempPath);
                $exportName = 'rekap-absensi-bulanan-' . $month->format('Y-m') . '.zip';
            }

            $newProcessed = $processed + 1;
            $finished = $newProcessed >= $reportJob->total_users;
            $reportJob->update([
                'processed_users' => $newProcessed,
                'status' => $finished ? 'completed' : 'pending',
                'current_user_name' => $finished ? null : $user->name,
            ]);

            if ($finished) {
                $reportJob->update([
                    'zip_path' => $exportPath,
                    'zip_name' => $exportName,
                ]);
            }

            return response()->json([
                'status' => $finished ? 'completed' : 'pending',
                'progress' => $reportJob->progressPercent(),
                'current_user' => $user->name,
                'processed_users' => $reportJob->processed_users,
                'total_users' => $reportJob->total_users,
                'message' => $finished ? 'Selesai! File siap diunduh.' : $user->name . ' selesai diproses.',
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
        abort_unless($reportJob->user_id === Auth::id(), 403);
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

    private function newAttendanceSpreadsheet(string $title): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Absensi');
        $sheet->mergeCells('A1:O1');
        $sheet->setCellValue('A1', $title);
        $sheet->fromArray([
            'No', 'Nama Pegawai', 'NIP PJLP', 'NIK', 'Jabatan / Bidang', 'Tanggal',
            'Absen Awal', 'Absen Akhir', 'Dinas Luar', 'Status', 'Lokasi Terakhir',
            'Catatan / Tujuan', 'Foto Absen Awal', 'Foto Absen Akhir', 'Foto Dinas Luar',
        ], null, 'A2');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('A2:O2')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DFF6E8']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->freezePane('A3');

        return $spreadsheet;
    }

    private function addSelfieToSheet(
        $sheet,
        ?AttendanceRecord $record,
        string $column,
        int $row,
        int $maxWidth = 154,
        int $maxHeight = 108,
        int $cellWidth = 168,
        int $cellHeight = 120,
        ?int $topOffset = null,
        bool $showPlaceholder = true
    ): void {
        $cell = $column . $row;

        if (! $record?->selfie_path) {
            if ($showPlaceholder) {
                $sheet->setCellValue($cell, '-');
            }
            return;
        }

        $path = Storage::disk('public')->path($record->selfie_path);
        if (! is_file($path)) {
            if ($showPlaceholder) {
                $sheet->setCellValue($cell, 'File foto tidak ditemukan');
            }
            return;
        }

        $imageInfo = @getimagesize($path);
        $supportedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
        if (! $imageInfo || ! in_array($imageInfo['mime'] ?? '', $supportedTypes, true)) {
            if ($showPlaceholder) {
                $sheet->setCellValue($cell, 'Format foto tidak didukung Excel');
            }
            return;
        }

        $drawing = new Drawing();
        $drawing->setName($record->label());
        $drawing->setDescription('Foto ' . $record->label());
        $drawing->setPath($path);
        $drawing->setResizeProportional(true);
        $drawing->setWidthAndHeight($maxWidth, $maxHeight);
        $drawing->setCoordinates($cell);
        $drawing->setOffsetX(max(4, (int) floor(($cellWidth - $drawing->getWidth()) / 2)));
        $drawing->setOffsetY($topOffset ?? max(5, (int) floor(($cellHeight - $drawing->getHeight()) / 2)));
        $drawing->setWorksheet($sheet);
        $sheet->getRowDimension($row)->setRowHeight(90);
    }

    private function finishAttendanceSpreadsheet($sheet, int $lastRow): void
    {
        $lastRow = max(2, $lastRow);
        $sheet->getStyle('A2:O' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '444444']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);
        $sheet->getStyle('A3:J' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('M3:O' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C3:D' . $lastRow)->getNumberFormat()->setFormatCode('@');

        $widths = [
            'A' => 6, 'B' => 24, 'C' => 16, 'D' => 20, 'E' => 22, 'F' => 22,
            'G' => 15, 'H' => 15, 'I' => 15, 'J' => 16, 'K' => 30, 'L' => 30,
            'M' => 24, 'N' => 24, 'O' => 24,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(2)->setRowHeight(32);
        $sheet->setAutoFilter('A2:O' . $lastRow);
    }

    private function appendUserToMonthlyWorkbook(
        string $path,
        User $user,
        $records,
        array $leaveDates,
        Carbon $month,
        string $monthLabel,
        bool $isFirstUser
    ): void {
        if ($isFirstUser || ! is_file($path)) {
            $workbook = new Spreadsheet();
            $sheet = $workbook->getActiveSheet();
            $sheet->setTitle('Semua Absensi');
            $sheet->freezePane('A3');
        } else {
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getSheetByName('Semua Absensi');
            if (! $sheet) {
                throw new \RuntimeException('Sheet semua absensi tidak ditemukan.');
            }
        }

        $titleRow = $isFirstUser ? 1 : $sheet->getHighestRow() + 2;
        $headingRow = $titleRow + 1;
        $sheet->mergeCells('A' . $titleRow . ':O' . $titleRow);
        $sheet->setCellValue('A' . $titleRow, 'Rekap Absensi ' . $user->name . ' - ' . $monthLabel);
        $sheet->getStyle('A' . $titleRow)->getFont()->setBold(true)->setSize(16);
        $sheet->fromArray([
            'No', 'Nama Pegawai', 'NIP PJLP', 'NIK', 'Jabatan / Bidang', 'Tanggal',
            'Absen Awal', 'Absen Akhir', 'Dinas Luar', 'Status', 'Lokasi Terakhir',
            'Catatan / Tujuan', 'Foto Absen Awal', 'Foto Absen Akhir', 'Foto Dinas Luar',
        ], null, 'A' . $headingRow);
        $sheet->getStyle('A' . $headingRow . ':O' . $headingRow)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DFF6E8']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension($headingRow)->setRowHeight(32);

        for ($dayNumber = 1; $dayNumber <= $month->daysInMonth; $dayNumber++) {
            $day = $month->copy()->day($dayNumber);
            $dateString = $day->toDateString();
            $dayRecords = isset($records[$user->id . '|' . $dateString])
                ? $records[$user->id . '|' . $dateString]->keyBy('type')
                : collect();
            $start = $dayRecords->get(AttendanceRecord::TYPE_START);
            $end = $dayRecords->get(AttendanceRecord::TYPE_END);
            $field = $dayRecords->get(AttendanceRecord::TYPE_FIELD);
            $latest = $field ?: ($end ?: $start);
            $status = match (true) {
                isset($leaveDates[$dateString]) => 'Izin / Sakit',
                (bool) $field => 'Dinas Luar',
                (bool) $end => 'Hadir',
                (bool) $start => 'Belum Lengkap',
                $day->isWeekend() => 'Libur',
                default => 'Alfa',
            };
            $dataRow = $headingRow + $dayNumber;
            $sheet->fromArray([
                $dayNumber,
                $user->name,
                $user->nip ?: '-',
                $user->nik ?: '-',
                $user->jabatan ?: 'PJLP',
                $day->translatedFormat('d F Y'),
                $this->timeCell($start),
                $this->timeCell($end),
                $this->timeCell($field),
                $status,
                $this->locationText($latest),
                $field?->note ?: ($latest?->note ?: '-'),
                '', '', '',
            ], null, 'A' . $dataRow);
            $this->addSelfieToSheet($sheet, $start, 'M', $dataRow);
            $this->addSelfieToSheet($sheet, $end, 'N', $dataRow);
            $this->addSelfieToSheet($sheet, $field, 'O', $dataRow);
        }

        $lastDataRow = $headingRow + $month->daysInMonth;
        $sheet->getStyle('A' . $headingRow . ':O' . $lastDataRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '444444']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);
        $sheet->getStyle('A' . ($headingRow + 1) . ':J' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('M' . ($headingRow + 1) . ':O' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C' . ($headingRow + 1) . ':D' . $lastDataRow)->getNumberFormat()->setFormatCode('@');

        $widths = [
            'A' => 6, 'B' => 24, 'C' => 16, 'D' => 20, 'E' => 22, 'F' => 22,
            'G' => 15, 'H' => 15, 'I' => 15, 'J' => 16, 'K' => 30, 'L' => 30,
            'M' => 24, 'N' => 24, 'O' => 24,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $workbook->setActiveSheetIndex(0);
        (new Xlsx($workbook))->save($path);
        $workbook->disconnectWorksheets();
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
