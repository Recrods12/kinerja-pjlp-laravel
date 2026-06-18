<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Holiday;
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

    public function downloadZip(Request $request)
    {
        set_time_limit(300);

        $period = $request->query('period', 'monthly');
        $yearNumber = (int) $request->query('year', now()->year);
        $monthNumber = max(1, min(12, (int) $request->query('month', now()->month)));
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

        if ($period === 'yearly') {
            $zipName = 'laporan-kinerja-tahunan-' . $yearNumber . '-' . now()->format('YmdHis') . '.zip';
        } else {
            $month = Carbon::create($yearNumber, $monthNumber, 1);
            $zipName = 'laporan-kinerja-' . Str::slug($month->translatedFormat('F-Y')) . '-' . now()->format('YmdHis') . '.zip';
        }

        $zipPath = $directory . DIRECTORY_SEPARATOR . $zipName;
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($period === 'yearly') {
            foreach ($users as $user) {
                for ($m = 1; $m <= 12; $m++) {
                    $month = Carbon::create($yearNumber, $m, 1);
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
            }
        } else {
            $month = Carbon::create($yearNumber, $monthNumber, 1);

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
        }

        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    private function entriesForDate(User $target, Carbon $date)
    {
        return $target->performanceEntries()
            ->whereDate('work_date', $date)
            ->orderBy('sort_order')
            ->get();
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
}
