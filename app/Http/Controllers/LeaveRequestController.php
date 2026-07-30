<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        $leaveRequests = $request->user()
            ->leaveRequests()
            ->latest()
            ->paginate(10);

        $summary = [
            'pending' => $request->user()->leaveRequests()->where('status', LeaveRequest::STATUS_PENDING)->count(),
            'approved' => $request->user()->leaveRequests()->where('status', LeaveRequest::STATUS_APPROVED)->count(),
            'rejected' => $request->user()->leaveRequests()->where('status', LeaveRequest::STATUS_REJECTED)->count(),
        ];

        return view('leave.index', compact('leaveRequests', 'summary'));
    }

    public function create(Request $request): View
    {
        return view('leave.create', ['user' => $request->user()]);
    }

    public function calendar(Request $request): View
    {
        $monthNumber = max(1, min(12, (int) $request->query('month', now()->month)));
        $yearNumber = max(2020, min(2100, (int) $request->query('year', now()->year)));
        $month = Carbon::create($yearNumber, $monthNumber, 1)->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $leaveRequests = $request->user()
            ->leaveRequests()
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $month)
            ->orderBy('start_date')
            ->orderBy('end_date')
            ->get();

        $calendarDays = $this->buildCalendarDays($month, $leaveRequests);
        $monthNames = $this->monthNames();

        $summary = [
            'pending' => $leaveRequests->where('status', LeaveRequest::STATUS_PENDING)->count(),
            'approved' => $leaveRequests->where('status', LeaveRequest::STATUS_APPROVED)->count(),
            'rejected' => $leaveRequests->where('status', LeaveRequest::STATUS_REJECTED)->count(),
        ];

        return view('leave.calendar', compact('calendarDays', 'leaveRequests', 'month', 'monthNames', 'summary'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'duration_unit' => ['required', 'in:hari,bulan,tahun'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'start_date.required' => 'Tanggal mulai cuti wajib diisi.',
            'start_date.date' => 'Tanggal mulai cuti tidak valid.',
            'start_date.after_or_equal' => 'Tanggal mulai cuti tidak boleh sebelum hari ini.',
            'end_date.required' => 'Tanggal selesai cuti wajib diisi.',
            'end_date.date' => 'Tanggal selesai cuti tidak valid.',
            'end_date.after_or_equal' => 'Tanggal selesai cuti tidak boleh sebelum tanggal mulai.',
            'duration_unit.required' => 'Jenis lamanya cuti wajib dipilih.',
            'duration_unit.in' => 'Jenis lamanya cuti tidak valid.',
            'reason.required' => 'Keperluan cuti wajib diisi.',
            'reason.min' => 'Keperluan cuti minimal 5 karakter.',
            'reason.max' => 'Keperluan cuti maksimal 2000 karakter.',
        ]);

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $totalDays = $this->workdayCount($startDate, $endDate);

        if ($totalDays < 1) {
            return back()
                ->withErrors(['start_date' => 'Tanggal cuti harus berada pada hari kerja.'])
                ->withInput();
        }

        if ($totalDays > $request->user()->annual_leave_remaining) {
            return back()
                ->withErrors(['end_date' => 'Jumlah hari cuti melebihi sisa cuti Anda.'])
                ->withInput();
        }

        $hasOverlap = $request->user()
            ->leaveRequests()
            ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($hasOverlap) {
            return back()
                ->withErrors(['start_date' => 'Tanggal cuti bertabrakan dengan pengajuan yang masih aktif.'])
                ->withInput();
        }

        DB::transaction(function () use ($request, $data, $totalDays) {
            $user = User::query()
                ->whereKey($request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $leaveRequest = $user->leaveRequests()->create([
                ...$data,
                'total_days' => $totalDays,
                'status' => LeaveRequest::STATUS_PENDING,
            ]);

            // Notify admin
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'leave_submitted',
                    'title' => 'Pengajuan Cuti Baru',
                    'body' => $user->name . ' mengajukan cuti ' . $leaveRequest->total_days . ' ' . $leaveRequest->duration_unit . ' (' . $leaveRequest->start_date->format('d/m/Y') . ' - ' . $leaveRequest->end_date->format('d/m/Y') . ').',
                    'link' => route('admin.leave.show', $leaveRequest),
                ]);
            }
        });

        return redirect()
            ->route('leave.index')
            ->with('status', 'Pengajuan cuti berhasil dikirim dan menunggu persetujuan admin.');
    }

    public function show(Request $request, LeaveRequest $leaveRequest): View
    {
        abort_unless($leaveRequest->user_id === $request->user()->id, 403);

        return view('leave.show', compact('leaveRequest'));
    }

    public function print(Request $request, LeaveRequest $leaveRequest): View
    {
        abort_unless($leaveRequest->user_id === $request->user()->id, 403);
        abort_unless($leaveRequest->status === LeaveRequest::STATUS_APPROVED, 403);

        $leaveRequest->load('user', 'approver');
        $approver = $leaveRequest->approver;

        return view('admin.leave.print', compact('leaveRequest', 'approver'));
    }

    private function buildCalendarDays(Carbon $month, $leaveRequests): array
    {
        $days = [];
        $offset = $month->copy()->startOfMonth()->dayOfWeek;

        for ($blank = 0; $blank < $offset; $blank++) {
            $days[] = ['date' => null, 'requests' => collect()];
        }

        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $date = $month->copy()->day($day);
            $dateString = $date->toDateString();

            $days[] = [
                'date' => $date,
                'requests' => $leaveRequests->filter(function (LeaveRequest $leaveRequest) use ($dateString) {
                    return $leaveRequest->start_date->toDateString() <= $dateString
                        && $leaveRequest->end_date->toDateString() >= $dateString;
                })->values(),
            ];
        }

        return $days;
    }

    private function monthNames(): array
    {
        return [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
    }

    private function workdayCount(Carbon $startDate, Carbon $endDate): int
    {
        $holidayDates = Holiday::query()
            ->whereBetween('holiday_date', [$startDate->copy()->startOfDay(), $endDate->copy()->startOfDay()])
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $count = 0;
        $date = $startDate->copy();

        while ($date->lte($endDate)) {
            if (! $date->isWeekend() && ! in_array($date->toDateString(), $holidayDates, true)) {
                $count++;
            }

            $date->addDay();
        }

        return $count;
    }
}
