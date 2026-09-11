<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_approval_and_rejection_cannot_process_any_resubmitted_type(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['awal' => '09:00', 'akhir' => '16:00', 'dinas_luar' => '09:00'] as $type => $time) {
            $this->travelTo(Carbon::parse('2026-09-11 ' . $time));
            $user = User::factory()->create();
            $record = $user->attendanceRecords()->create([
                'type' => $type, 'work_date' => now()->toDateString(), 'recorded_at' => now(),
            ]);
            $oldVersion = $record->submission_version;
            $this->actingAs($admin)->post(route('admin.attendance.reject', $record), [
                'submission_version' => $oldVersion,
            ])->assertSessionHasNoErrors();
            $this->travel(1)->minutes();
            $this->actingAs($user)->post(route('attendance.store', $type), [
                'latitude' => -6.2, 'longitude' => 106.8, 'note' => 'Pengajuan baru',
                'selfie' => UploadedFile::fake()->image('new.jpg'), 'submission_version' => 99,
            ])->assertSessionHasNoErrors();
            $record->refresh();
            $this->assertSame($oldVersion + 1, $record->submission_version);
            $this->assertSame('pending', $record->approval_status);
            $this->assertSame(1, $user->attendanceRecords()->count());
            $notificationCount = Notification::count();
            $this->actingAs($admin);
            foreach (['approve', 'reject'] as $action) {
                $this->post(route('admin.attendance.' . $action, $record), [
                    'submission_version' => $oldVersion,
                ])->assertSessionHasErrors('approval');
                $this->assertSame('pending', $record->fresh()->approval_status);
                $this->assertNull($record->fresh()->reviewed_by);
                $this->assertSame($notificationCount, Notification::count());
            }
            $this->post(route('admin.attendance.approve', $record), [
                'submission_version' => $record->submission_version,
            ])->assertSessionHasNoErrors();
            $this->assertSame('approved', $record->fresh()->approval_status);
        }
    }

    public function test_forms_before_versioning_and_invalid_versions_are_rejected(): void
    {
        $user = User::factory()->create();
        $record = $user->attendanceRecords()->create(['type' => 'awal', 'work_date' => now(), 'recorded_at' => now()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['approve', 'reject'] as $action) {
            foreach ([[], ['submission_version' => 0], ['submission_version' => 'invalid']] as $payload) {
                $this->post(route('admin.attendance.' . $action, $record), $payload)->assertSessionHasErrors('submission_version');
                $this->assertSame('pending', $record->fresh()->approval_status);
            }
        }
    }

    public function test_admin_edit_invalidates_previous_review_form_and_remains_available(): void
    {
        $user = User::factory()->create();
        $record = $user->attendanceRecords()->create(['type' => 'awal', 'work_date' => now(), 'recorded_at' => now()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.attendance.edit', $record))->assertOk();
        $oldVersion = $record->submission_version;
        $this->put(route('admin.attendance.update', $record), [
            'type' => 'awal', 'work_date' => now()->toDateString(), 'recorded_time' => '08:30', 'note' => 'Koreksi admin',
        ])->assertSessionHasNoErrors();
        $this->assertSame($oldVersion + 1, $record->fresh()->submission_version);
        $this->post(route('admin.attendance.approve', $record), ['submission_version' => $oldVersion])->assertSessionHasErrors('approval');
        $this->post(route('admin.attendance.reject', $record), ['submission_version' => $record->fresh()->submission_version])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $record->fresh()->approval_status);
    }

    public function test_employee_edit_endpoints_cannot_change_any_attendance_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['pending', 'approved', 'rejected'] as $index => $status) {
            $record = $user->attendanceRecords()->create([
                'type' => ['awal', 'akhir', 'dinas_luar'][$index], 'work_date' => now(), 'recorded_at' => now(), 'approval_status' => $status,
            ]);
            $this->get('/absensi/riwayat/' . $record->id . '/edit')->assertNotFound();
            $this->put('/absensi/riwayat/' . $record->id, ['note' => 'Change'])->assertStatus(405);
            $this->assertSame($status, $record->fresh()->approval_status);
            $this->assertSame(1, $record->fresh()->submission_version);
        }
    }

    public function test_both_review_forms_render_the_current_version(): void
    {
        $user = User::factory()->create();
        $record = $user->attendanceRecords()->create(['type' => 'awal', 'work_date' => now(), 'recorded_at' => now()]);
        $record->submission_version = 3;
        $record->save();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([route('admin.attendance.index'), route('admin.attendance.show', $record)] as $url) {
            $this->get($url)->assertOk()->assertSee('name="submission_version" value="3"', false)
                ->assertSee('data-version="3"', false)
                ->assertSee('form.elements.submission_version.value = button.dataset.version;', false);
        }
        file_put_contents(sys_get_temp_dir() . '/attendance-version-fixture.html', $this->get(route('admin.attendance.index'))->getContent());
    }
}
