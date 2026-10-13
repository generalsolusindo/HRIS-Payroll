<?php

namespace Tests\Feature;

use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_karyawan_can_submit_an_overtime_request(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->post(route('lembur.store'), [
                'starts_at' => '2026-09-28 18:00:00',
                'ends_at' => '2026-09-28 21:00:00',
                'break_minutes' => 30,
                'reason' => 'Menyelesaikan laporan bulanan.',
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $overtimeRequest = OvertimeRequest::where('user_id', $karyawan->id)->firstOrFail();
        $this->assertSame('submitted', $overtimeRequest->status);
        $this->assertSame($karyawan->company_id, $overtimeRequest->company_id);
        $this->assertSame(30, $overtimeRequest->break_minutes);
    }

    public function test_hrd_cannot_submit_an_overtime_request(): void
    {
        $hrd = User::factory()->create(['role' => 'hrd']);

        $this->actingAs($hrd)
            ->post(route('lembur.store'), [
                'starts_at' => '2026-09-28 18:00:00',
                'ends_at' => '2026-09-28 21:00:00',
                'reason' => 'Tidak boleh.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('overtime_requests', 0);
    }

    public function test_manager_can_submit_an_overtime_request(): void
    {
        $manager = User::factory()->create(['role' => 'admin']);

        $this->actingAs($manager)
            ->post(route('lembur.store'), [
                'starts_at' => '2026-09-28 18:00:00',
                'ends_at' => '2026-09-28 21:00:00',
                'reason' => 'Rapat evaluasi bulanan.',
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $this->assertDatabaseCount('overtime_requests', 1);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->post(route('lembur.store'), [
                'starts_at' => '2026-09-28 21:00:00',
                'ends_at' => '2026-09-28 18:00:00',
                'reason' => 'Tidak valid.',
            ])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_reason_is_required(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($karyawan)
            ->post(route('lembur.store'), [
                'starts_at' => '2026-09-28 18:00:00',
                'ends_at' => '2026-09-28 21:00:00',
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_karyawan_only_sees_their_own_overtime_requests(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);
        $other = User::factory()->create(['role' => 'karyawan']);

        $mine = OvertimeRequest::create([
            'user_id' => $karyawan->id, 'company_id' => $karyawan->company_id,
            'starts_at' => '2026-09-28 18:00:00', 'ends_at' => '2026-09-28 21:00:00',
            'reason' => 'Punyaku', 'status' => 'submitted',
        ]);
        OvertimeRequest::create([
            'user_id' => $other->id, 'company_id' => $other->company_id,
            'starts_at' => '2026-09-28 18:00:00', 'ends_at' => '2026-09-28 21:00:00',
            'reason' => 'Punya orang lain', 'status' => 'submitted',
        ]);

        $response = $this->actingAs($karyawan)
            ->get(route('lembur.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('lembur'));

        $ids = collect($response->viewData('page')['props']['overtimeRequests'])->pluck('id');
        $this->assertSame([$mine->id], $ids->all());
    }
}
