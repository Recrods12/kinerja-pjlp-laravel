<?php

namespace App\Http\Controllers;

use App\Models\PerformanceEntry;
use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            return $this->admin($request);
        }

        $selectedDate = $this->nextWorkdayForUser($user, Carbon::parse($request->query('date', now()->toDateString())));
        $month = Carbon::create(
            (int) $request->query('year', $selectedDate->year),
            (int) $request->query('month', $selectedDate->month),
            1
        );

        $entries = $user->performanceEntries()
            ->whereDate('work_date', $selectedDate)
            ->orderBy('sort_order')
            ->get();

        return view('pjlp.dashboard', [
            'user' => $user,
            'selectedDate' => $selectedDate,
            'month' => $month,
            'entries' => $entries,
            'entryDates' => $this->entryDatesForMonth($user->id, $month),
            'holidayDates' => $this->holidayDatesForMonth($month),
            'workDates' => $this->workDatesForMonth($user, $month),
            'stats' => $this->monthStats($user, $month),
        ]);
    }

    private function admin(Request $request)
    {
        $latestEntryDate = PerformanceEntry::query()->latest('work_date')->value('work_date');
        $defaultMonth = $latestEntryDate ? Carbon::parse($latestEntryDate) : now();
        $jobRoles = ['Driver', 'Kebersihan', 'Keamanan', 'Mekanikal Enginer', 'Pelayanan Umum'];
        $selectedRole = $request->query('jabatan');
        $search = trim((string) $request->query('search', ''));

        $month = Carbon::create(
            (int) $request->query('year', $defaultMonth->year),
            (int) $request->query('month', $defaultMonth->month),
            1
        );

        $pjlpUsers = User::query()
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
            ->get()
            ->map(function (User $user) use ($month) {
                $user->work_dates = $this->workDatesForMonth($user, $month);
                $user->stats = $this->monthStats($user, $month);
                $user->entry_dates = $this->entryDatesForMonth($user->id, $month);
                $user->latest_entry_date = $user->performanceEntries()
                    ->whereBetween('work_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                    ->latest('work_date')
                    ->value('work_date');

                return $user;
            });
        $holidayDates = $this->holidayDatesForMonth($month);
        $today = now()->startOfDay();
        $todayIso = $today->toDateString();
        $scheduledToday = $today->isSameMonth($month)
            ? $pjlpUsers->filter(fn (User $user) => in_array($todayIso, $user->work_dates, true))
            : collect();
        $doneToday = $scheduledToday->filter(fn (User $user) => in_array($todayIso, $user->entry_dates, true))->count();
        $missingToday = max($scheduledToday->count() - $doneToday, 0);

        return view('admin.dashboard', [
            'month' => $month,
            'pjlpUsers' => $pjlpUsers,
            'monthDates' => collect(range(1, $month->daysInMonth))
                ->map(fn (int $day) => $month->copy()->day($day)),
            'holidayDates' => $holidayDates,
            'adminStats' => [
                'totalPjlp' => $pjlpUsers->count(),
                'doneToday' => $doneToday,
                'missingToday' => $missingToday,
                'holidays' => count($holidayDates),
            ],
            'jobRoles' => $jobRoles,
            'selectedRole' => $selectedRole,
            'search' => $search,
        ]);
    }

    private function entryDatesForMonth(int $userId, Carbon $month): array
    {
        return PerformanceEntry::query()
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->select('work_date')
            ->distinct()
            ->pluck('work_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();
    }

    private function monthStats(User $user, Carbon $month): array
    {
        $entryDates = $this->entryDatesForMonth($user->id, $month);
        $workDates = $this->workDatesForMonth($user, $month);
        $today = now()->startOfDay();
        $done = 0;
        $missing = 0;

        foreach ($workDates as $iso) {
            $date = Carbon::parse($iso);
            if ($date->greaterThan($today)) {
                continue;
            }

            in_array($iso, $entryDates, true) ? $done++ : $missing++;
        }

        return ['done' => $done, 'missing' => $missing];
    }

    private function nextWorkdayForUser(User $user, Carbon $date): Carbon
    {
        $workday = $date->copy();
        $month = $workday->copy()->startOfMonth();
        $holidayDates = $this->holidayDatesForMonth($month);

        while (! $user->isScheduledWorkday($workday, $holidayDates)) {
            $workday->addDay();
            if (! $workday->isSameMonth($month)) {
                $holidayDates = $this->holidayDatesForMonth($workday->copy()->startOfMonth());
                $month = $workday->copy()->startOfMonth();
            }
        }

        return $workday;
    }

    private function workDatesForMonth(User $user, Carbon $month): array
    {
        $holidayDates = $this->holidayDatesForMonth($month);
        $dates = [];

        foreach (range(1, $month->daysInMonth) as $day) {
            $date = $month->copy()->day($day);
            if ($user->isScheduledWorkday($date, $holidayDates)) {
                $dates[] = $date->toDateString();
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
