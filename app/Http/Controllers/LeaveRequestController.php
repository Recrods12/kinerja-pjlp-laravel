<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'duration_unit' => ['required', 'in:hari,bulan,tahun'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'start_date.required' => 'Tanggal mulai cuti wajib diisi.',
            'start_date.date' => 'Tanggal mulai cuti tidak valid.',
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

        $isPastLeave = $endDate->lt(now()->startOfDay());

        DB::transaction(function () use ($request, $data, $totalDays, $isPastLeave) {
            $user = User::query()
                ->whereKey($request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($isPastLeave) {
                $user->decrement('annual_leave_remaining', $totalDays);
            }

            $user->leaveRequests()->create([
                ...$data,
                'total_days' => $totalDays,
                'status' => $isPastLeave ? LeaveRequest::STATUS_APPROVED : LeaveRequest::STATUS_PENDING,
                'admin_note' => $isPastLeave ? 'Cuti susulan untuk tanggal yang sudah lewat, otomatis tercatat disetujui.' : null,
                'approved_at' => $isPastLeave ? now() : null,
            ]);
        });

        return redirect()
            ->route('leave.index')
            ->with('status', $isPastLeave
                ? 'Cuti susulan berhasil dicatat dan otomatis disetujui.'
                : 'Pengajuan cuti berhasil dikirim dan menunggu persetujuan admin.');
    }

    public function show(Request $request, LeaveRequest $leaveRequest): View
    {
        abort_unless($leaveRequest->user_id === $request->user()->id, 403);

        return view('leave.show', compact('leaveRequest'));
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
