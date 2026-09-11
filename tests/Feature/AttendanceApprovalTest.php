<?php
namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function record(User $user, string $type = 'awal'): AttendanceRecord
    {
        return $user->attendanceRecords()->create(['type' => $type, 'work_date' => now()->toDateString(), 'recorded_at' => now(), 'note' => 'Tugas lapangan']);
    }

    public function test_all_mobile_types_require_separate_approval_and_end_can_be_submitted_while_start_pending(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['awal' => '08:00', 'akhir' => '16:00', 'dinas_luar' => '09:00'] as $type => $time) {
            $this->travelTo(\Carbon\Carbon::parse('2026-09-' . ($type === 'dinas_luar' ? '12' : '11') . ' ' . $time));
            $this->actingAs($user)->post(route('attendance.store', $type), [
                'latitude' => -6.2, 'longitude' => 106.8, 'note' => 'Tugas lapangan',
                'selfie' => UploadedFile::fake()->image('selfie.jpg'), 'approval_status' => 'approved',
            ])->assertSessionHasNoErrors()->assertRedirect(route('attendance.index'));
        }
        $records = $user->attendanceRecords()->get();
        $this->assertCount(3, $records);
        $this->assertSame(3, $records->where('approval_status', 'pending')->count());
        foreach ($records as $record) {
            $time = $record->recorded_at->toDateTimeString();
            $this->actingAs($admin)->post(route('admin.attendance.approve', $record), ['submission_version' => $record->submission_version])->assertSessionHasNoErrors();
            $this->assertSame('approved', $record->fresh()->approval_status);
            $this->assertSame($time, $record->fresh()->recorded_at->toDateTimeString());
            $this->assertSame($admin->id, $record->fresh()->reviewed_by);
        }
    }

    public function test_only_admin_can_review_and_rejection_cannot_be_processed_twice(): void
    {
        $user = User::factory()->create();
        $record = $this->record($user);
        $this->actingAs($user)->post(route('admin.attendance.approve', $record), ['submission_version' => $record->submission_version])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('admin.attendance.reject', $record), ['rejection_reason' => 'Foto tidak jelas', 'submission_version' => $record->submission_version])->assertRedirect();
        $this->assertSame('rejected', $record->fresh()->approval_status);
        $this->post(route('admin.attendance.approve', $record), ['submission_version' => $record->submission_version])->assertSessionHasErrors('approval');
        $this->actingAs($user)->get(route('attendance.show', $record))->assertOk()->assertSee('Foto tidak jelas');
        $this->get('/absensi/riwayat/' . $record->id . '/edit')->assertNotFound();
        $this->put('/absensi/riwayat/' . $record->id, ['note' => 'Diperbaiki'])->assertStatus(405);
        $this->assertSame('rejected', $record->fresh()->approval_status);
    }

    public function test_admin_can_reject_without_a_reason(): void
    {
        $user = User::factory()->create();
        $record = $this->record($user);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.attendance.reject', $record), ['submission_version' => $record->submission_version])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('rejected', $record->fresh()->approval_status);
        $this->assertNull($record->fresh()->rejection_reason);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'body' => $record->work_date->format('d/m/Y') . ' telah ditolak admin.',
        ]);
    }

    public function test_dashboard_and_daily_export_do_not_count_pending_as_present(): void
    {
        $user = User::factory()->create();
        $record = $this->record($user, 'akhir');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.attendance.index'))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->first()['status'] === 'pending')->assertSee('Setujui Absen Akhir');
        $response = $this->get(route('admin.attendance.exportExcel'))->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'attendance-test');
        try {
            file_put_contents($file, $response->streamedContent());
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
            $this->assertSame('Menunggu persetujuan', $sheet->getCell('J3')->getValue());
            $this->assertSame('-', $sheet->getCell('H3')->getValue());
        } finally { unlink($file); }
        $this->post(route('admin.attendance.approve', $record), ['submission_version' => $record->submission_version]);
        $this->get(route('admin.attendance.index'))->assertViewHas('rows', fn ($rows) => $rows->first()['status'] === 'hadir');
    }

    public function test_monthly_workbook_keeps_pending_and_rejected_evidence_out_of_official_times(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-11 16:00'));
        $user = User::factory()->create();
        $record = $this->record($user, 'akhir');
        $controller = app(\App\Http\Controllers\AdminAttendanceController::class);
        $method = new \ReflectionMethod($controller, 'appendUserToMonthlyWorkbook');
        foreach (['pending' => 'Menunggu persetujuan', 'rejected' => 'Ditolak', 'approved' => 'Hadir'] as $status => $label) {
            $record->update(['approval_status' => $status]);
            $records = collect([$record])->groupBy(fn ($item) => $item->user_id . '|' . $item->work_date->toDateString());
            $file = tempnam(sys_get_temp_dir(), 'monthly-test');
            try {
                $method->invoke($controller, $file, $user, $records, [], now()->startOfMonth(), 'September 2026', true);
                $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
                $sheet = $book->getActiveSheet();
                $this->assertSame($label, $sheet->getCell('J13')->getValue());
                $this->assertSame($status === 'approved' ? '16:00 WIB' : '-', $sheet->getCell('H13')->getValue());
                $book->disconnectWorksheets();
            } finally { unlink($file); }
        }
    }

    public function test_rejected_attendance_can_be_submitted_again_but_pending_cannot(): void
    {
        Storage::fake('public');
        $this->travelTo(\Carbon\Carbon::parse('2026-09-11 09:00'));
        $user = User::factory()->create();
        $record = $this->record($user);
        $record->update(['approval_status' => 'rejected', 'rejection_reason' => 'Foto kurang jelas', 'reviewed_at' => now()]);
        $this->actingAs($user)->get(route('attendance.index'))->assertOk()->assertSee('Ditolak - Ajukan ulang')->assertSee(route('attendance.create', 'awal'));
        $this->get(route('attendance.create', 'awal'))->assertOk()->assertSee('Foto kurang jelas');
        $this->travelTo(\Carbon\Carbon::parse('2026-09-11 09:10'));
        $payload = ['latitude' => -6.2, 'longitude' => 106.8, 'selfie' => UploadedFile::fake()->image('new.jpg')];
        $this->post(route('attendance.store', 'awal'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, $user->attendanceRecords()->count());
        $this->assertSame('pending', $record->fresh()->approval_status);
        $this->assertNull($record->fresh()->rejection_reason);
        $this->assertNull($record->fresh()->reviewed_at);
        $this->assertSame('09:10', $record->fresh()->recorded_at->format('H:i'));
        $this->post(route('attendance.store', 'awal'), $payload)->assertSessionHasErrors('attendance');
    }

    public function test_rejected_records_leave_attendance_columns_empty_but_history_remains_accessible(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['awal', 'akhir', 'dinas_luar'] as $type) {
            $record = $this->record($user, $type);
            $record->update(['approval_status' => 'rejected', 'address' => 'Lokasi pengajuan ditolak']);
        }
        $this->get(route('admin.attendance.index'))->assertOk()
            ->assertViewHas('rows', function ($rows) {
                $row = $rows->first();
                return $row['status'] === 'rejected' && $row['records']->isEmpty()
                    && $row['latestRecord'] === null && $row['historyRecord'] !== null;
            })->assertDontSee('Lokasi pengajuan ditolak')->assertSee('Lihat Riwayat Absensi');
    }

    public function test_monthly_export_pipeline_includes_approved_time_and_photo_in_both_formats(): void
    {
        Storage::fake('public');
        $this->travelTo(\Carbon\Carbon::parse('2026-09-11 11:54'));
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $record = $this->record($user);
        $record->update(['approval_status' => 'approved', 'selfie_path' => UploadedFile::fake()->image('selfie.jpg')->store('attendance-selfies', 'public')]);
        $this->actingAs($admin);
        foreach (['monthly_attendance_workbook', 'monthly_attendance'] as $type) {
            $job = new \App\Models\ReportJob([
                'user_id' => $admin->id, 'type' => $type, 'status' => 'pending',
                'total_users' => 1, 'processed_users' => 0, 'month' => 9, 'year' => 2026,
            ]);
            $job->id = random_int(100000000, 2000000000);
            $job->save();
            $file = null;
            $temporary = null;
            try {
                $this->postJson(route('admin.report-jobs.step', $job))->assertOk()->assertJsonPath('status', 'completed');
                $file = $job->fresh()->zip_path;
                $this->get(route('admin.report-jobs.download', $job))->assertOk();
                $excel = $file;
                if ($type === 'monthly_attendance') {
                    $zip = new \ZipArchive();
                    $zip->open($file);
                    $temporary = tempnam(sys_get_temp_dir(), 'export-test');
                    file_put_contents($temporary, $zip->getFromIndex(0));
                    $zip->close();
                    $excel = $temporary;
                }
                $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($excel);
                $sheet = $book->getActiveSheet();
                $row = $type === 'monthly_attendance_workbook' ? 13 : 11;
                $this->assertSame('11:54 WIB', $sheet->getCell('G' . $row)->getValue());
                $this->assertSame('Belum Lengkap', $sheet->getCell('J' . $row)->getValue());
                $this->assertCount(1, $sheet->getDrawingCollection());
                $this->assertSame('M' . $row, $sheet->getDrawingCollection()[0]->getCoordinates());
                $book->disconnectWorksheets();
            } finally {
                if ($file && is_file($file)) unlink($file);
                if ($temporary && is_file($temporary)) unlink($temporary);
            }
        }
    }
}
