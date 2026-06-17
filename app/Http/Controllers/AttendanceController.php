<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('date')) {
            return redirect()->route('attendance.index');
        }

        $user = $request->user();
        $date = now()->startOfDay();
        $records = $this->recordsForDate($user, $date);

        return view('attendance.index', [
            'user' => $user,
            'date' => $date,
            'records' => $records,
            'summary' => $this->summary($records),
            'recentRecords' => $user->attendanceRecords()
                ->latest('recorded_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function create(Request $request, string $type)
    {
        abort_unless(array_key_exists($type, AttendanceRecord::labels()), 404);

        $user = $request->user();
        $date = now()->startOfDay();
        $records = $this->recordsForDate($user, $date);

        return view('attendance.form', [
            'user' => $user,
            'type' => $type,
            'typeLabel' => AttendanceRecord::labels()[$type],
            'date' => $date,
            'records' => $records,
            'rules' => $this->ruleText($type),
        ]);
    }

    public function store(Request $request, string $type)
    {
        abort_unless(array_key_exists($type, AttendanceRecord::labels()), 404);

        $user = $request->user();
        $now = now();
        $date = $now->copy()->startOfDay();
        $records = $this->recordsForDate($user, $date);

        $this->validateAttendanceFlow($type, $records, $now);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => [$type === AttendanceRecord::TYPE_FIELD ? 'required' : 'nullable', 'string', 'max:1000'],
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:12288'],
        ], [
            'latitude.required' => 'Lokasi GPS wajib aktif sebelum absensi.',
            'longitude.required' => 'Lokasi GPS wajib aktif sebelum absensi.',
            'note.required' => 'Tujuan atau keterangan dinas luar wajib diisi.',
            'selfie.required' => 'Foto selfie wajib diunggah untuk absensi.',
            'selfie.uploaded' => 'Foto selfie gagal diunggah. Coba ambil foto ulang, pastikan koneksi stabil, atau kecilkan ukuran foto.',
            'selfie.file' => 'Foto selfie tidak valid.',
            'selfie.mimes' => 'File selfie harus berupa gambar JPG, PNG, WEBP, HEIC, atau HEIF.',
            'selfie.max' => 'Ukuran foto selfie maksimal 12 MB.',
        ]);

        $data['selfie_path'] = $request->file('selfie')->store('attendance-selfies', 'public');
        unset($data['selfie']);

        $user->attendanceRecords()->create($data + [
            'work_date' => $date->toDateString(),
            'type' => $type,
            'recorded_at' => $now,
        ]);

        // Notifikasi admin
        $typeLabel = AttendanceRecord::labels()[$type];
        $admins = \App\Models\User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::create([
                'user_id' => $admin->id,
                'type' => 'attendance_' . $type,
                'title' => $typeLabel . ' — ' . $user->name,
                'body' => $user->name . ' melakukan ' . strtolower($typeLabel) . ' pada ' . $now->format('d/m/Y H:i') . ' WIB.',
                'link' => route('admin.attendance.index'),
            ]);
        }

        return redirect()
            ->route('attendance.index')
            ->with('status', $typeLabel . ' berhasil disimpan.');
    }

    public function show(Request $request, AttendanceRecord $attendanceRecord)
    {
        $this->ensureOwnedByUser($request, $attendanceRecord);

        $records = AttendanceRecord::query()
            ->where('user_id', $attendanceRecord->user_id)
            ->whereDate('work_date', $attendanceRecord->work_date)
            ->orderBy('recorded_at')
            ->get();

        return view('attendance.show', [
            'attendanceRecord' => $attendanceRecord->load('user'),
            'records' => $records,
        ]);
    }

    public function edit(Request $request, AttendanceRecord $attendanceRecord)
    {
        $this->ensureOwnedByUser($request, $attendanceRecord);

        return view('attendance.edit', [
            'attendanceRecord' => $attendanceRecord,
        ]);
    }

    public function update(Request $request, AttendanceRecord $attendanceRecord)
    {
        $this->ensureOwnedByUser($request, $attendanceRecord);

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => [$attendanceRecord->type === AttendanceRecord::TYPE_FIELD ? 'required' : 'nullable', 'string', 'max:1000'],
            'selfie' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:12288'],
        ], [
            'note.required' => 'Tujuan atau keterangan dinas luar wajib diisi.',
            'selfie.uploaded' => 'Foto selfie gagal diunggah. Coba ambil foto ulang, pastikan koneksi stabil, atau kecilkan ukuran foto.',
            'selfie.file' => 'Foto selfie tidak valid.',
            'selfie.mimes' => 'File selfie harus berupa gambar JPG, PNG, WEBP, HEIC, atau HEIF.',
            'selfie.max' => 'Ukuran foto selfie maksimal 12 MB.',
        ]);

        if ($request->hasFile('selfie')) {
            $this->replaceSelfie($attendanceRecord, $request);
        }

        unset($data['selfie']);
        $attendanceRecord->update($data);

        return redirect()
            ->route('attendance.show', $attendanceRecord)
            ->with('status', 'Data absensi berhasil diperbarui.');
    }

    private function recordsForDate($user, Carbon $date)
    {
        return $user->attendanceRecords()
            ->whereDate('work_date', $date)
            ->get()
            ->keyBy('type');
    }

    private function ensureOwnedByUser(Request $request, AttendanceRecord $attendanceRecord): void
    {
        abort_unless($attendanceRecord->user_id === $request->user()->id, 403);
    }

    private function replaceSelfie(AttendanceRecord $attendanceRecord, Request $request): void
    {
        if ($attendanceRecord->selfie_path) {
            Storage::disk('public')->delete($attendanceRecord->selfie_path);
        }

        $attendanceRecord->selfie_path = $request->file('selfie')->store('attendance-selfies', 'public');
    }

    private function summary($records): array
    {
        return [
            'start' => $records->get(AttendanceRecord::TYPE_START),
            'end' => $records->get(AttendanceRecord::TYPE_END),
            'field' => $records->get(AttendanceRecord::TYPE_FIELD),
        ];
    }

    private function validateAttendanceFlow(string $type, $records, Carbon $now): void
    {
        if ($records->has($type)) {
            throw ValidationException::withMessages([
                'attendance' => AttendanceRecord::labels()[$type] . ' hari ini sudah tersimpan.',
            ]);
        }

        if ($records->has(AttendanceRecord::TYPE_FIELD) && $type !== AttendanceRecord::TYPE_FIELD) {
            throw ValidationException::withMessages([
                'attendance' => 'Hari ini sudah menggunakan Absen Dinas Luar, tidak perlu absen awal atau akhir.',
            ]);
        }

        if ($type === AttendanceRecord::TYPE_START && $records->has(AttendanceRecord::TYPE_FIELD)) {
            throw ValidationException::withMessages([
                'attendance' => 'Absen awal tidak bisa dibuat karena dinas luar sudah aktif.',
            ]);
        }

        if ($type === AttendanceRecord::TYPE_START && $records->has(AttendanceRecord::TYPE_END)) {
            throw ValidationException::withMessages([
                'attendance' => 'Absen awal tidak bisa dibuat karena sudah ada absen akhir hari ini.',
            ]);
        }

        if ($type === AttendanceRecord::TYPE_START && $now->hour >= 12) {
            throw ValidationException::withMessages([
                'attendance' => 'Absen awal hanya bisa dilakukan sebelum pukul 12.00.',
            ]);
        }

        if ($type === AttendanceRecord::TYPE_END) {
            if ($now->hour < 12) {
                throw ValidationException::withMessages([
                    'attendance' => 'Absen akhir baru bisa dilakukan mulai pukul 12.00.',
                ]);
            }
        }

        if ($type === AttendanceRecord::TYPE_FIELD) {
            if ($records->has(AttendanceRecord::TYPE_START) || $records->has(AttendanceRecord::TYPE_END)) {
                throw ValidationException::withMessages([
                    'attendance' => 'Dinas luar tidak bisa dipilih karena sudah ada absen awal atau akhir hari ini.',
                ]);
            }
        }
    }

    private function ruleText(string $type): array
    {
        return match ($type) {
            AttendanceRecord::TYPE_START => [
                'Absen awal digunakan untuk memulai aktivitas kerja di pagi hari.',
                'Gunakan saat bertugas dari luar kantor atau saat mulai aktivitas lapangan.',
            ],
            AttendanceRecord::TYPE_END => [
                'Absen akhir digunakan untuk mengakhiri aktivitas kerja atau tugas luar setelah absen finger print di kantor.',
                'Bisa dilakukan mulai pukul 12.00, meskipun tidak ada Absen Awal di sistem.',
            ],
            AttendanceRecord::TYPE_FIELD => [
                'Dinas luar digunakan jika bertugas di luar kantor seharian.',
                'Cukup absen satu kali dan isi tujuan tugas. Mencakup absen awal dan akhir.',
            ],
            default => [],
        };
    }
}
