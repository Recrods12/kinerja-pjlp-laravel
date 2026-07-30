<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminLeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $leaveRequests = $this->filteredQuery($status, $search)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $summary = [
            'pending' => LeaveRequest::where('status', LeaveRequest::STATUS_PENDING)->count(),
            'approved' => LeaveRequest::where('status', LeaveRequest::STATUS_APPROVED)->count(),
            'rejected' => LeaveRequest::where('status', LeaveRequest::STATUS_REJECTED)->count(),
        ];

        return view('admin.leave.index', compact('leaveRequests', 'summary', 'status', 'search'));
    }

    public function show(LeaveRequest $leaveRequest): View
    {
        $leaveRequest->load('user', 'approver');

        return view('admin.leave.show', compact('leaveRequest'));
    }

    public function calendar(Request $request): View
    {
        $monthNumber = max(1, min(12, (int) $request->query('month', now()->month)));
        $yearNumber = max(2020, min(2100, (int) $request->query('year', now()->year)));
        $month = Carbon::create($yearNumber, $monthNumber, 1)->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $leaveRequests = LeaveRequest::query()
            ->with('user')
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $month)
            ->orderBy('start_date')
            ->orderBy('end_date')
            ->get();

        $calendarDays = $this->buildCalendarDays($month, $leaveRequests);
        $monthNames = $this->monthNames();

        $summary = [
            'approved' => $leaveRequests->count(),
            'people' => $leaveRequests->pluck('user_id')->unique()->count(),
            'active_today' => $leaveRequests->filter(function (LeaveRequest $leaveRequest) {
                $today = now()->toDateString();

                return $leaveRequest->start_date->toDateString() <= $today
                    && $leaveRequest->end_date->toDateString() >= $today;
            })->count(),
            'pending' => LeaveRequest::where('status', LeaveRequest::STATUS_PENDING)->count(),
        ];

        return view('admin.leave.calendar', compact('calendarDays', 'leaveRequests', 'month', 'monthNames', 'summary'));
    }

    public function updateDates(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ], [
            'start_date.required' => 'Tanggal mulai cuti wajib diisi.',
            'start_date.date' => 'Tanggal mulai cuti tidak valid.',
            'end_date.required' => 'Tanggal selesai cuti wajib diisi.',
            'end_date.date' => 'Tanggal selesai cuti tidak valid.',
            'end_date.after_or_equal' => 'Tanggal selesai cuti tidak boleh sebelum tanggal mulai.',
        ]);

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $totalDays = $this->workdayCount($startDate, $endDate);

        if ($totalDays < 1) {
            return back()
                ->withErrors(['start_date' => 'Tanggal cuti harus berada pada hari kerja.'])
                ->withInput();
        }

        try {
            DB::transaction(function () use ($leaveRequest, $data, $totalDays) {
                $leaveRequest = LeaveRequest::query()->lockForUpdate()->findOrFail($leaveRequest->id);
                $user = $leaveRequest->user()->lockForUpdate()->firstOrFail();
                $oldTotalDays = $leaveRequest->total_days;

                if ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
                    $difference = $totalDays - $oldTotalDays;
                    $remaining = $user->annual_leave_remaining - $difference;

                    if ($remaining < 0) {
                        throw ValidationException::withMessages(['end_date' => 'Sisa cuti PJLP tidak mencukupi untuk perubahan tanggal ini.']);
                    }

                    $user->forceFill([
                        'annual_leave_remaining' => min($user->annual_leave_quota, $remaining),
                    ])->save();
                }

                $leaveRequest->update([
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'total_days' => $totalDays,
                ]);
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('status', 'Tanggal cuti berhasil diperbarui.');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($request, $leaveRequest, $data) {
                $leaveRequest = LeaveRequest::query()->lockForUpdate()->findOrFail($leaveRequest->id);
                $user = $leaveRequest->user()->lockForUpdate()->firstOrFail();

                if (! $leaveRequest->isPending()) {
                    throw ValidationException::withMessages(['approval' => 'Pengajuan ini sudah diproses.']);
                }

                if ($user->annual_leave_remaining < $leaveRequest->total_days) {
                    throw ValidationException::withMessages(['approval' => 'Sisa cuti PJLP tidak mencukupi.']);
                }

                $user->decrement('annual_leave_remaining', $leaveRequest->total_days);

                $leaveRequest->update([
                    'status' => LeaveRequest::STATUS_APPROVED,
                    'admin_note' => $data['admin_note'] ?? null,
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);

                Notification::create([
                    'user_id' => $leaveRequest->user_id,
                    'type' => 'leave_approved',
                    'title' => 'Cuti Disetujui',
                    'body' => 'Pengajuan cuti ' . $leaveRequest->start_date->format('d/m/Y') . ' - ' . $leaveRequest->end_date->format('d/m/Y') . ' (' . $leaveRequest->total_days . ' ' . $leaveRequest->duration_unit . ') telah disetujui.',
                    'link' => route('leave.show', $leaveRequest),
                ]);
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('status', 'Pengajuan cuti disetujui dan saldo cuti PJLP sudah dikurangi.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($request, $leaveRequest, $data) {
                $leaveRequest = LeaveRequest::query()->lockForUpdate()->findOrFail($leaveRequest->id);

                if (! $leaveRequest->isPending()) {
                    throw ValidationException::withMessages(['approval' => 'Pengajuan ini sudah diproses.']);
                }

                $leaveRequest->update([
                    'status' => LeaveRequest::STATUS_REJECTED,
                    'admin_note' => $data['admin_note'] ?? null,
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);

                Notification::create([
                    'user_id' => $leaveRequest->user_id,
                    'type' => 'leave_rejected',
                    'title' => 'Cuti Ditolak',
                    'body' => 'Pengajuan cuti ' . $leaveRequest->start_date->format('d/m/Y') . ' - ' . $leaveRequest->end_date->format('d/m/Y') . ' (' . $leaveRequest->total_days . ' ' . $leaveRequest->duration_unit . ') ditolak.' . ($data['admin_note'] ? ' Alasan: ' . $data['admin_note'] : ''),
                    'link' => route('leave.show', $leaveRequest),
                ]);
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('status', 'Pengajuan cuti ditolak.');
    }

    public function print(LeaveRequest $leaveRequest): View
    {
        abort_unless($leaveRequest->status === LeaveRequest::STATUS_APPROVED, 403);

        $leaveRequest->load('user', 'approver');
        $approver = $this->approverForLeave($leaveRequest);

        return view('admin.leave.print', compact('leaveRequest', 'approver'));
    }

    public function exportExcel(Request $request): Response
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $leaveRequests = $this->filteredQuery($status, $search)
            ->latest()
            ->get();

        $filename = 'data-cuti-'.now()->format('Ymd-His').'.xls';

        return response()
            ->view('admin.leave.excel', compact('leaveRequests'))
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function exportCalendar(Request $request): Response
    {
        $period = $request->query('period', 'monthly');
        $monthNumber = max(1, min(12, (int) $request->query('month', now()->month)));
        $yearNumber = max(2020, min(2100, (int) $request->query('year', now()->year)));

        if ($period === 'yearly') {
            $start = Carbon::create($yearNumber, 1, 1)->startOfYear();
            $end = $start->copy()->endOfYear();
            $filename = 'cuti-tahunan-'.$yearNumber.'.xls';
        } else {
            $start = Carbon::create($yearNumber, $monthNumber, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $filename = 'cuti-bulanan-'.$start->translatedFormat('F-Y').'.xls';
        }

        $leaveRequests = LeaveRequest::query()
            ->with('user', 'approver')
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->orderBy('start_date')
            ->orderBy('end_date')
            ->get();

        return response()
            ->view('admin.leave.excel', compact('leaveRequests'))
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    private function filteredQuery(?string $status, string $search)
    {
        return LeaveRequest::query()
            ->with('user', 'approver')
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('user', function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('jabatan', 'like', "%{$search}%");
                });
            });
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

    private function approverForLeave(LeaveRequest $leaveRequest): ?User
    {
        if ($leaveRequest->approver?->signature_path) {
            return $leaveRequest->approver;
        }

        return User::query()
            ->where('role', 'admin')
            ->where('name', 'like', '%Andhika%')
            ->whereNotNull('signature_path')
            ->first()
            ?? User::query()
                ->where('role', 'admin')
                ->whereNotNull('signature_path')
                ->orderBy('name')
                ->first()
            ?? $leaveRequest->approver
            ?? User::query()
                ->where('role', 'admin')
                ->where('name', 'like', '%Andhika%')
                ->first()
            ?? User::query()
                ->where('role', 'admin')
                ->orderBy('name')
                ->first();
    }
}
