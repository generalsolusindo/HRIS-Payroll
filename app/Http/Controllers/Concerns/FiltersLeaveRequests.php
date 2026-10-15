<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Division;
use App\Models\LeaveRequest;
use App\Services\LeaveTypeValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared data layer for the HRD/Manager "Pengajuan Izin", "Riwayat", and
 * "Laporan" screens — every query here is scoped to one company, the
 * caller's own, continuing the tenant-isolation pattern used across the
 * app (role middleware alone never carries the company boundary).
 */
trait FiltersLeaveRequests
{
    /**
     * @return array{total: int, pending: int, approved: int, rejected: int, today: int}
     */
    private function leaveRequestStats(int $companyId, string $pendingStatus): array
    {
        $scoped = fn () => LeaveRequest::whereHas('user', fn ($query) => $query->where('company_id', $companyId));

        return [
            'total' => $scoped()->count(),
            'pending' => $scoped()->where('status', $pendingStatus)->count(),
            'approved' => $scoped()->where('status', 'approved')->count(),
            'rejected' => $scoped()->where('status', 'rejected')->count(),
            'today' => $scoped()->whereDate('created_at', today())->count(),
        ];
    }

    /**
     * Base query for one company, with the optional search/type/division
     * filters every one of the three screens accepts the same way.
     *
     * @return Builder<LeaveRequest>
     */
    private function filteredLeaveRequestsQuery(Request $request, int $companyId): Builder
    {
        $search = trim((string) $request->string('search'));

        return LeaveRequest::query()
            ->whereHas('user', function ($query) use ($companyId, $request) {
                $query->where('company_id', $companyId);

                if ($request->filled('division_id')) {
                    $query->where('division_id', $request->integer('division_id'));
                }
            })
            ->when(
                $search !== '',
                fn (Builder $query) => $query->where(
                    fn (Builder $q) => $q->where('request_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"))
                ),
            )
            ->when(
                $request->filled('type'),
                fn (Builder $query) => $query->where('type', $request->string('type')->toString()),
            );
    }

    /**
     * Divisions belonging to the given company — the filter dropdown must
     * never leak another tenant's division list.
     *
     * @return Collection<int, array{id: int<0, max>, name: string}>
     */
    private function divisionsForCompany(int $companyId): Collection
    {
        return Division::where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Division $division) => ['id' => $division->id, 'name' => $division->name]);
    }

    /**
     * @return array<string, string>
     */
    private function leaveTypeOptions(): array
    {
        return collect(LeaveTypeValidator::TYPES)
            ->mapWithKeys(fn (string $type) => [$type => (new LeaveRequest(['type' => $type]))->typeLabel()])
            ->all();
    }

    private function leaveRequestStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu HRD',
            'pending_manager' => 'Menunggu Manager',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => $status,
        };
    }

    /**
     * Streams a CSV of whatever the given (already company-scoped) query
     * matches — used for the "Laporan" screen on both HRD and Manager.
     *
     * @param  Builder<LeaveRequest>  $query
     */
    private function streamLeaveRequestsCsv(Builder $query, string $filename): StreamedResponse
    {
        $leaveRequests = $query
            ->with(['user:id,name,division_id', 'user.division:id,name', 'hrdProcessedBy:id,name', 'processedBy:id,name'])
            ->oldest()
            ->get();

        return response()->streamDownload(function () use ($leaveRequests) {
            $handle = fopen('php://output', 'w');
            abort_if($handle === false, 500, 'Gagal membuat berkas laporan.');

            fputcsv($handle, [
                'No. Pengajuan', 'Nama', 'Jabatan', 'Jenis Izin', 'Tanggal Izin',
                'Status', 'Diproses HRD oleh', 'Diproses Manager oleh', 'Tanggal Keputusan',
            ]);

            foreach ($leaveRequests as $leaveRequest) {
                fputcsv($handle, array_map($this->csvSafe(...), [
                    $leaveRequest->request_number,
                    $leaveRequest->user->name,
                    $leaveRequest->user->division?->name ?? '-',
                    $leaveRequest->typeLabel(),
                    $leaveRequest->leave_date->format('d/m/Y'),
                    $this->leaveRequestStatusLabel($leaveRequest->status),
                    $leaveRequest->hrdProcessedBy->name ?? '-',
                    $leaveRequest->processedBy->name ?? '-',
                    $leaveRequest->processed_at?->format('d/m/Y H:i') ?? '-',
                ]));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Neutralizes CSV/formula injection ("CSV injection"): user-controlled
     * fields like a name (free text, self-editable, no character
     * restriction — see ProfileValidationRules::nameRules()) could
     * otherwise start with =, +, -, or @ and be executed as a formula by
     * Excel/Sheets/LibreOffice when a staff member opens this export.
     * Prefixing with a tab keeps the value readable but inert as a formula.
     */
    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "\t{$value}" : $value;
    }
}
