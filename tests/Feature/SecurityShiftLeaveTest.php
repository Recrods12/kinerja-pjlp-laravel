<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityShiftLeaveTest extends TestCase
{
    use RefreshDatabase;

    private function securityUser(string $team, string $cycleStart): User
    {
        return User::factory()->create([
            'name' => 'Security '.$team,
            'username' => 'sec_'.strtolower($team),
            'role' => 'pjlp',
            'jabatan' => 'Keamanan',
            'security_team' => $team,
            'security_cycle_start_date' => $cycleStart,
            'annual_leave_quota' => 12,
            'annual_leave_remaining' => 12,
        ]);
    }

    private function regularUser(string $jabatan = 'Driver'): User
    {
        return User::factory()->create([
            'name' => $jabatan,
            'username' => strtolower($jabatan),
            'role' => 'pjlp',
            'jabatan' => $jabatan,
            'annual_leave_quota' => 12,
            'annual_leave_remaining' => 12,
        ]);
    }

    public function test_team_a_security_shift_leave_cycle_is_dynamic(): void
    {
        $user = $this->securityUser('A', '2026-10-01');

        $expected = [
            '2026-10-01' => true,
            '2026-10-02' => false,
            '2026-10-03' => false,
            '2026-10-04' => true,
            '2026-10-05' => false,
            '2026-10-06' => false,
            '2026-10-07' => true,
            '2026-10-08' => false,
            '2026-10-09' => false,
        ];

        foreach ($expected as $date => $shouldBeWorkday) {
            $this->assertSame(
                $shouldBeWorkday,
                $user->isScheduledWorkday(Carbon::parse($date)),
                "Team A schedule mismatch on {$date}"
            );
        }
    }

    public function test_team_b_security_shift_leave_cycle_is_dynamic(): void
    {
        $user = $this->securityUser('B', '2026-10-01');

        $expected = [
            '2026-10-01' => false,
            '2026-10-02' => true,
            '2026-10-03' => false,
            '2026-10-04' => false,
            '2026-10-05' => true,
            '2026-10-06' => false,
            '2026-10-07' => false,
            '2026-10-08' => true,
            '2026-10-09' => false,
        ];

        foreach ($expected as $date => $shouldBeWorkday) {
            $this->assertSame(
                $shouldBeWorkday,
                $user->isScheduledWorkday(Carbon::parse($date)),
                "Team B schedule mismatch on {$date}"
            );
        }
    }

    public function test_team_c_security_shift_leave_cycle_is_dynamic(): void
    {
        $user = $this->securityUser('C', '2026-10-01');

        $expected = [
            '2026-10-01' => false,
            '2026-10-02' => false,
            '2026-10-03' => true,
            '2026-10-04' => false,
            '2026-10-05' => false,
            '2026-10-06' => true,
            '2026-10-07' => false,
            '2026-10-08' => false,
            '2026-10-09' => true,
        ];

        foreach ($expected as $date => $shouldBeWorkday) {
            $this->assertSame(
                $shouldBeWorkday,
                $user->isScheduledWorkday(Carbon::parse($date)),
                "Team C schedule mismatch on {$date}"
            );
        }
    }

    public function test_security_shift_leave_count_counts_only_scheduled_workdays(): void
    {
        $user = $this->securityUser('A', '2026-10-01');

        $this->actingAs($user)->post('/cuti', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-09',
            'duration_unit' => 'hari',
            'reason' => 'Cuti keamanan beregu shift.',
        ])->assertSessionHasNoErrors()->assertRedirect(route('leave.index'));

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $user->id,
            'total_days' => 3,
        ]);
    }

    public function test_regular_user_sunday_leave_is_still_rejected(): void
    {
        $user = $this->regularUser('Driver');

        $response = $this->actingAs($user)->post('/cuti', [
            'start_date' => '2026-10-04',
            'end_date' => '2026-10-04',
            'duration_unit' => 'hari',
            'reason' => 'Cuti driver minggu.',
        ]);

        $response->assertSessionHasErrors('start_date');
    }

    public function test_regular_user_weekday_leave_is_accepted(): void
    {
        $user = $this->regularUser('Driver');

        $this->actingAs($user)->post('/cuti', [
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
            'duration_unit' => 'hari',
            'reason' => 'Cuti driver hari kerja.',
        ])->assertSessionHasNoErrors()->assertRedirect(route('leave.index'));

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $user->id,
            'total_days' => 1,
        ]);
    }

    public function test_security_shift_leave_before_and_after_cycle_start_uses_same_pattern(): void
    {
        $user = $this->securityUser('A', '2026-10-01');

        $this->assertFalse($user->isScheduledWorkday(Carbon::parse('2026-09-29')));
        $this->assertFalse($user->isScheduledWorkday(Carbon::parse('2026-09-30')));
        $this->assertTrue($user->isScheduledWorkday(Carbon::parse('2026-10-01')));
        $this->assertFalse($user->isScheduledWorkday(Carbon::parse('2026-10-02')));
        $this->assertFalse($user->isScheduledWorkday(Carbon::parse('2026-10-03')));
        $this->assertTrue($user->isScheduledWorkday(Carbon::parse('2026-10-04')));
    }
}
