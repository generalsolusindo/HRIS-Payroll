<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Dashboard Monitoring HR" — combines a KPI summary with a "perlu
 * perhatian" worklist of things nearing a deadline (trial/probation,
 * kontrak, missing baseline documents).
 *
 * Kandidat and PKL are part of the roadmap (modul rekrutmen/magang
 * belum dibangun) — their stats are reported as null rather than 0 so
 * the frontend can show "belum tersedia" instead of a misleading zero,
 * and they never appear in the "Perlu Perhatian" worklist since there is
 * no underlying data for them yet.
 */
class MonitoringDashboardController extends Controller
{
    /**
     * The two baseline ID documents counted toward "Dokumen Lengkap" and
     * flagged individually when missing. The other document types (KTM,
     * Ijazah, Lainnya) stay purely optional and never affect this metric.
     */
    private const REQUIRED_DOCUMENT_TYPES = ['ktp', 'kk'];

    /** How far out a kontrak/probation deadline has to be before it stops showing up here at all. */
    private const ATTENTION_WINDOW_DAYS = 45;

    public function index(): Response
    {
        $employees = User::where('role', 'karyawan')->where('status', 'active')
            ->get(['id', 'name', 'probation_ends_on', 'contract_ends_on']);

        $documentTypesByUser = EmployeeDocument::whereIn('user_id', $employees->pluck('id'))
            ->whereIn('type', self::REQUIRED_DOCUMENT_TYPES)
            ->get(['user_id', 'type'])
            ->groupBy('user_id')
            ->map(fn ($docs) => $docs->pluck('type')->unique()->all());

        $completeCount = 0;
        $attention = collect();

        foreach ($employees as $employee) {
            $missingDocs = array_diff(self::REQUIRED_DOCUMENT_TYPES, $documentTypesByUser->get($employee->id, []));

            if ($missingDocs === []) {
                $completeCount++;
            }

            foreach ($missingDocs as $type) {
                $attention->push([
                    'name' => $employee->name,
                    'monitoring' => 'Dokumen '.strtoupper($type),
                    'deadline' => null,
                    'days_left' => null,
                    'severity' => 'red',
                ]);
            }

            $this->pushIfWithinWindow($attention, $employee->name, 'Trial selesai', $employee->probation_ends_on);
            $this->pushIfWithinWindow($attention, $employee->name, 'Kontrak', $employee->contract_ends_on);
        }

        $severityRank = ['red' => 0, 'amber' => 1, 'green' => 2];
        $sorted = $attention
            ->sortBy([
                fn (array $item) => $severityRank[$item['severity']],
                fn (array $item) => $item['days_left'] ?? -1,
            ])
            ->values();

        return Inertia::render('hrd/monitoring-dashboard', [
            'stats' => [
                'karyawan_aktif' => $employees->count(),
                'kandidat_aktif' => null,
                'pkl_aktif' => null,
                'dokumen_lengkap_percent' => $employees->isEmpty()
                    ? 0 : (int) round($completeCount / $employees->count() * 100),
            ],
            'attention' => $sorted,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{name: string, monitoring: string, deadline: string|null, days_left: int|null, severity: string}>  $attention
     */
    private function pushIfWithinWindow($attention, string $name, string $label, ?CarbonInterface $date): void
    {
        if ($date === null) {
            return;
        }

        $daysLeft = (int) Carbon::today()->diffInDays($date, false);

        if ($daysLeft > self::ATTENTION_WINDOW_DAYS) {
            return;
        }

        $attention->push([
            'name' => $name,
            'monitoring' => $label,
            'deadline' => $date->toDateString(),
            'days_left' => $daysLeft,
            'severity' => match (true) {
                $daysLeft < 0 => 'red',
                $daysLeft <= 7 => 'amber',
                default => 'green',
            },
        ]);
    }
}
