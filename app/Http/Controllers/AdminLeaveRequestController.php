<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
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

        return view('admin.leave.print', compact('leaveRequest'));
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
}
