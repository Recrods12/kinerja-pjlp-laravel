<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        $summaryRows = $users->map(function (User $user) use ($records, $leaves) {
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

        $rows = $summaryRows;

        if (in_array($status, ['hadir', 'dinas_luar', 'izin', 'alfa', 'belum_lengkap'], true)) {
            $rows = $rows->filter(fn ($row) => $row['status'] === $status)->values();
        }

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
