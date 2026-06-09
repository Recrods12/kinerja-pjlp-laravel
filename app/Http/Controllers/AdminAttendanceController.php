<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

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

        $rows = $users->map(function (User $user) use ($records, $leaves) {
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

        if (in_array($status, ['hadir', 'dinas_luar', 'izin', 'alfa', 'belum_lengkap'], true)) {
            $rows = $rows->filter(fn ($row) => $row['status'] === $status)->values();
        }

        return view('admin.attendance.index', [
            'date' => $date,
            'rows' => $rows,
            'status' => $status,
            'search' => $search,
            'summary' => [
                'hadir' => $rows->where('status', 'hadir')->count(),
                'dinas_luar' => $rows->where('status', 'dinas_luar')->count(),
                'izin' => $rows->where('status', 'izin')->count(),
                'alfa' => $rows->where('status', 'alfa')->count(),
                'belum_lengkap' => $rows->where('status', 'belum_lengkap')->count(),
            ],
        ]);
    }

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

    private function statusFor($records, ?LeaveRequest $leave): string
    {
        if ($leave) {
            return 'izin';
        }

        if ($records->has(AttendanceRecord::TYPE_FIELD)) {
            return 'dinas_luar';
        }

        if ($records->has(AttendanceRecord::TYPE_START) && $records->has(AttendanceRecord::TYPE_END)) {
            return 'hadir';
        }

        if ($records->has(AttendanceRecord::TYPE_START)) {
            return 'belum_lengkap';
        }

        return 'alfa';
    }
}
