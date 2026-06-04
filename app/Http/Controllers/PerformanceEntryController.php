<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PerformanceEntryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'work_time' => ['array'],
            'work_time.*' => ['nullable', 'string', 'max:50'],
            'task' => ['array'],
            'task.*' => ['nullable', 'string'],
            'note' => ['array'],
            'note.*' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();
        $date = Carbon::parse($data['work_date'])->toDateString();
        $workDate = Carbon::parse($date);
        $holidayDates = Holiday::query()
            ->whereBetween('holiday_date', [$workDate->copy()->startOfMonth(), $workDate->copy()->endOfMonth()])
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        if (! $user->isScheduledWorkday($workDate, $holidayDates)) {
            return back()->withErrors(['work_date' => 'Kinerja hanya diisi untuk jadwal kerja aktif.']);
        }

        $user->performanceEntries()->whereDate('work_date', $date)->delete();

        foreach (($data['task'] ?? []) as $index => $task) {
            $task = trim((string) $task);
            $workTime = trim((string) ($data['work_time'][$index] ?? ''));
            $note = trim((string) ($data['note'][$index] ?? ''));

            if ($task === '' && $workTime === '' && $note === '') {
                continue;
            }

            $user->performanceEntries()->create([
                'work_date' => $date,
                'work_time' => $workTime,
                'task' => $task,
                'note' => $note,
                'sort_order' => $index + 1,
            ]);
        }

        return redirect()->route('dashboard', [
            'date' => $date,
            'month' => Carbon::parse($date)->month,
            'year' => Carbon::parse($date)->year,
        ])->with('status', 'Kinerja harian berhasil disimpan.');
    }
}
