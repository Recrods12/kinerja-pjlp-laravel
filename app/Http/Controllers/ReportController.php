<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ReportJob;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use ZipArchive;

class ReportController extends Controller
{
    public function show(Request $request, ?User $user = null)
    {
        $viewer = Auth::user();
        $target = $viewer->role === 'admin' ? $user : $viewer;

        abort_if(! $target || $target->role !== 'pjlp', 404);

        $selectedDate = Carbon::parse($request->query('date', now()->toDateString()));
        $pair = $this->scheduledPairForMonth($target, $selectedDate);
        $leftDate = $pair['leftDate'];
        $rightDate = $pair['rightDate'];
        $showAll = $request->boolean('all');
        $reportPages = $showAll
            ? $this->reportPagesForMonth($target, $selectedDate)
            : [[
                'leftDate' => $leftDate,
                'rightDate' => $rightDate,
                'leftEntries' => $this->entriesForDate($target, $leftDate),
                'rightEntries' => $this->entriesForDate($target, $rightDate),
            ]];

        return view('reports.show', [
            'target' => $target,
            'approver' => $this->approverForReport($viewer),
            'leftDate' => $leftDate,
            'rightDate' => $rightDate,
            'previousPairDate' => $pair['previousPairDate'],
            'nextPairDate' => $pair['nextPairDate'],
            'reportPages' => $reportPages,
            'showAll' => $showAll,
            'selectedDate' => $selectedDate,
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $user = Auth::user();
        $month = Carbon::create(
            (int) $request->query('year', now()->year),
            (int) $request->query('month', now()->month),
            1
        );

        $reportPages = $this->reportPagesForMonth($user, $month);

        if (empty($reportPages)) {
            return back()->withErrors(['download' => 'Tidak ada data kinerja untuk bulan ini.']);
        }

        $pdf = Pdf::loadView('reports.pdf', [
            'target' => $user,
            'approver' => $this->approverForReport($user),
            'reportPages' => $reportPages,
        ])->setPaper('a4', 'landscape');

        $monthLabel = $month->translatedFormat('F-Y');
        $fileName = $user->name . ' - kinerja ' . $monthLabel . '.pdf';

        return $pdf->download($fileName);
    }

    public function downloadZip(Request $request)
    {
        set_time_limit(300);

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
                        ->orWhere('jabatan', 'like', "%{$search}%")
                        ->orWhere('unit', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        if ($users->isEmpty()) {
            return back()->withErrors(['download' => 'Tidak ada user yang cocok untuk di-download.']);
        }

        $directory = storage_path('app/report-zips');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $zipName = 'laporan-kinerja-' . Str::slug($month->translatedFormat('F-Y')) . '-' . now()->format('YmdHis') . '.zip';
        $zipPath = $directory . DIRECTORY_SEPARATOR . $zipName;
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($users as $user) {
            $reportPages = $this->reportPagesForMonth($user, $month);

            if (empty($reportPages)) {
                continue;
            }

            $pdf = Pdf::loadView('reports.pdf', [
                'target' => $user,
                'approver' => $this->approverForReport(Auth::user()),
                'reportPages' => $reportPages,
            ])->setPaper('a4', 'landscape');

            $userName = $user->name ?: $user->username;
            $monthLabel = $month->translatedFormat('F');
            $fileName = $userName . ' - kinerja ' . $monthLabel . '.pdf';
            $zip->addFromString($fileName, $pdf->output());
        }

        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    private function entriesForDate(User $target, Carbon $date)
    {
        $entries = $target->performanceEntries()
            ->whereDate('work_date', $date)
            ->orderBy('sort_order')
            ->get();

        if ($entries->isNotEmpty()) {
            return $entries;
        }

        // Cek apakah tanggal ini adalah cuti yang sudah disetujui
        $hasApprovedLeave = LeaveRequest::query()
            ->where('user_id', $target->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->exists();

        if ($hasApprovedLeave) {
            $fake = new \App\Models\PerformanceEntry();
            $fake->work_time = '';
            $fake->task = 'CUTI';
            $fake->note = 'Cuti';
            $fake->sort_order = 1;

            return collect([$fake]);
        }

        return $entries;
    }

    private function approverForReport(?User $viewer): ?User
    {
        if ($viewer?->role === 'admin') {
            return $viewer;
        }

        return User::query()
            ->where('role', 'admin')
            ->whereNotNull('signature_path')
            ->orderBy('name')
            ->first()
            ?? User::query()
                ->where('role', 'admin')
                ->where('name', 'like', '%Andhika%')
                ->first()
            ?? User::query()
                ->where('role', 'admin')
                ->orderBy('name')
                ->first();
    }

    private function reportPagesForMonth(User $target, Carbon $selectedDate): array
    {
        $workdays = $this->scheduledDatesForMonth($target, $selectedDate);

        $pages = [];
        foreach (array_chunk($workdays, 2) as $pair) {
            $leftDate = $pair[0];
            $rightDate = $pair[1] ?? null;

            $pages[] = [
                'leftDate' => $leftDate,
                'rightDate' => $rightDate,
                'leftEntries' => $this->entriesForDate($target, $leftDate),
                'rightEntries' => $rightDate ? $this->entriesForDate($target, $rightDate) : collect(),
            ];
        }

        return $pages;
    }

    private function scheduledPairForMonth(User $target, Carbon $selectedDate): array
    {
        $scheduledDates = $this->scheduledDatesForMonth($target, $selectedDate);

        if (empty($scheduledDates)) {
            return [
                'leftDate' => $selectedDate->copy()->startOfDay(),
                'rightDate' => null,
                'previousPairDate' => $selectedDate->copy()->startOfDay(),
                'nextPairDate' => $selectedDate->copy()->startOfDay(),
            ];
        }

        $position = $this->nearestScheduledPosition($scheduledDates, $selectedDate);
        $leftPosition = $position % 2 === 0 ? $position : $position - 1;
        $leftDate = $scheduledDates[$leftPosition];
        $rightDate = $scheduledDates[$leftPosition + 1] ?? null;

        return [
            'leftDate' => $leftDate,
            'rightDate' => $rightDate,
            'previousPairDate' => $this->previousPairDate($target, $scheduledDates, $leftPosition),
            'nextPairDate' => $this->nextPairDate($target, $scheduledDates, $leftPosition),
        ];
    }

    private function nearestScheduledPosition(array $scheduledDates, Carbon $selectedDate): int
    {
        $fallbackPosition = count($scheduledDates) - 1;

        foreach ($scheduledDates as $index => $date) {
            if ($date->isSameDay($selectedDate)) {
                return $index;
            }

            if ($date->greaterThan($selectedDate)) {
                return $index;
            }
        }

        return $fallbackPosition;
    }

    private function previousPairDate(User $target, array $scheduledDates, int $leftPosition): Carbon
    {
        if ($leftPosition >= 2) {
            return $scheduledDates[$leftPosition - 2];
        }

        $previousScheduledDate = $this->previousScheduledDate($target, $scheduledDates[0]->copy()->subDay());
        $previousMonthDates = $this->scheduledDatesForMonth($target, $previousScheduledDate);
        $previousPosition = $this->nearestScheduledPosition($previousMonthDates, $previousScheduledDate);

        return $previousMonthDates[$previousPosition % 2 === 0 ? $previousPosition : $previousPosition - 1];
    }

    private function nextPairDate(User $target, array $scheduledDates, int $leftPosition): Carbon
    {
        if (isset($scheduledDates[$leftPosition + 2])) {
            return $scheduledDates[$leftPosition + 2];
        }

        $nextScheduledDate = $this->nextScheduledDate($target, end($scheduledDates));
        $nextMonthDates = $this->scheduledDatesForMonth($target, $nextScheduledDate);
        $nextPosition = $this->nearestScheduledPosition($nextMonthDates, $nextScheduledDate);

        return $nextMonthDates[$nextPosition % 2 === 0 ? $nextPosition : $nextPosition - 1];
    }

    private function previousScheduledDate(User $target, Carbon $date): Carbon
    {
        $workday = $date->copy()->startOfDay();
        $month = $workday->copy()->startOfMonth();
        $holidayDates = $this->holidayDatesForMonth($month);

        while (! $target->isScheduledWorkday($workday, $holidayDates)) {
            $workday->subDay();
            if (! $workday->isSameMonth($month)) {
                $month = $workday->copy()->startOfMonth();
                $holidayDates = $this->holidayDatesForMonth($month);
            }
        }

        return $workday;
    }

    private function nextScheduledDate(User $target, Carbon $date): Carbon
    {
        $workday = $date->copy()->addDay();
        $month = $workday->copy()->startOfMonth();
        $holidayDates = $this->holidayDatesForMonth($month);

        while (! $target->isScheduledWorkday($workday, $holidayDates)) {
            $workday->addDay();
            if (! $workday->isSameMonth($month)) {
                $month = $workday->copy()->startOfMonth();
                $holidayDates = $this->holidayDatesForMonth($month);
            }
        }

        return $workday;
    }

    private function scheduledDatesForMonth(User $target, Carbon $month): array
    {
        $holidayDates = $this->holidayDatesForMonth($month);
        $dates = [];

        foreach (range(1, $month->daysInMonth) as $day) {
            $date = $month->copy()->day($day);
            if ($target->isScheduledWorkday($date, $holidayDates)) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    private function holidayDatesForMonth(Carbon $month): array
    {
        return Holiday::query()
            ->whereBetween('holiday_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();
    }

    /* ===== EXPORT BULANAN LAPORAN KINERJA (ASYNC) ===== */

    /**
     * Tampilkan halaman progress export laporan kinerja.
     */
    public function showPerformanceExport(Request $request)
    {
        $monthNumber = max(1, min(12, (int) $request->query("month", now()->month)));
        $yearNumber = (int) $request->query("year", now()->year);

        return view("admin.reports.export-progress", [
            "month" => $monthNumber,
            "year" => $yearNumber,
        ]);
    }

    /**
     * Mulai export laporan kinerja via AJAX.
     */
    public function startPerformanceExport(Request $request)
    {
        $monthNumber = max(1, min(12, (int) $request->input("month", now()->month)));
        $yearNumber = (int) $request->input("year", now()->year);

        $users = User::query()
            ->where("role", "pjlp")
            ->orderBy("name")
            ->get();

        if ($users->isEmpty()) {
            return response()->json(["status" => "failed", "message" => "Tidak ada user PJLP."], 400);
        }

        $reportJob = ReportJob::create([
            "user_id" => Auth::id(),
            "type" => "monthly_performance",
            "status" => "pending",
            "total_users" => $users->count(),
            "processed_users" => 0,
            "month" => $monthNumber,
            "year" => $yearNumber,
            "current_user_name" => null,
        ]);

        return response()->json([
            "status" => "started",
            "report_job_id" => $reportJob->id,
            "total_users" => $reportJob->total_users,
        ]);
    }

    /**
     * Proses 1 user untuk ReportJob (laporan kinerja) via AJAX.
     */
    public function processPerformanceStep(ReportJob $reportJob, Request $request)
    {
        if ($reportJob->isFinished()) {
            return response()->json([
                "status" => $reportJob->status,
                "progress" => $reportJob->progressPercent(),
                "message" => $reportJob->status === "completed" ? "Selesai! ZIP siap diunduh." : "Gagal.",
            ]);
        }

        if ($reportJob->processed_users >= $reportJob->total_users) {
            $reportJob->update(["status" => "completed", "current_user_name" => null]);
            return response()->json([
                "status" => "completed",
                "progress" => 100,
                "message" => "Selesai! ZIP siap diunduh.",
            ]);
        }

        $month = Carbon::create($reportJob->year, $reportJob->month, 1)->startOfMonth();
        $processed = $reportJob->processed_users;

        $users = User::query()
            ->where("role", "pjlp")
            ->orderBy("name")
            ->get();

        $user = $users->skip($processed)->first();
        if (!$user) {
            $reportJob->update(["status" => "failed", "error_message" => "User tidak ditemukan."]);
            return response()->json(["status" => "failed", "progress" => $reportJob->progressPercent(), "message" => "Gagal: user tidak ditemukan."]);
        }

        $reportJob->update(["status" => "processing", "current_user_name" => $user->name]);

        try {
            set_time_limit(30);

            $reportPages = $this->reportPagesForMonth($user, $month);

            if (empty($reportPages)) {
                $reportJob->increment("processed_users");
                $reportJob->update(["status" => $reportJob->processed_users >= $reportJob->total_users ? "completed" : "processing", "current_user_name" => null]);
                return response()->json([
                    "status" => $reportJob->status,
                    "progress" => $reportJob->progressPercent(),
                    "current_user" => null,
                    "processed_users" => $reportJob->processed_users,
                    "total_users" => $reportJob->total_users,
                ]);
            }

            $zipPath = $reportJob->zip_path;
            $zipName = $reportJob->zip_name;

            if (!$zipPath) {
                $directory = storage_path("app/report-zips");
                if (!is_dir($directory)) {
                    mkdir($directory, 0775, true);
                }
                $monthLabel = $month->translatedFormat("F-Y");
                $zipName = "laporan-kinerja-" . Str::slug($monthLabel) . "-" . now()->format("YmdHis") . ".zip";
                $zipPath = $directory . DIRECTORY_SEPARATOR . $zipName;
                $reportJob->update(["zip_path" => $zipPath, "zip_name" => $zipName]);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                throw new RuntimeException("Gagal membuka ZIP.");
            }

            $pdf = Pdf::loadView("reports.pdf", [
                "target" => $user,
                "approver" => $this->approverForReport(Auth::user()),
                "reportPages" => $reportPages,
            ])->setPaper("a4", "landscape");

            $userName = $user->name ?: $user->username;
            $monthLabel = $month->translatedFormat("F");
            $fileName = $userName . " - kinerja " . $monthLabel . ".pdf";
            $zip->addFromString($fileName, $pdf->output());
            $zip->close();

            $reportJob->increment("processed_users");

            $isCompleted = $reportJob->processed_users >= $reportJob->total_users;
            if ($isCompleted) {
                $reportJob->update(["status" => "completed", "current_user_name" => null]);
            } else {
                $reportJob->update(["status" => "processing", "current_user_name" => null]);
            }

            return response()->json([
                "status" => $isCompleted ? "completed" : "processing",
                "progress" => $reportJob->progressPercent(),
                "current_user" => null,
                "processed_users" => $reportJob->processed_users,
                "total_users" => $reportJob->total_users,
            ]);
        } catch (Exception $e) {
            $reportJob->update(["status" => "failed", "error_message" => $e->getMessage()]);
            return response()->json(["status" => "failed", "message" => $e->getMessage()]);
        }
    }

}
