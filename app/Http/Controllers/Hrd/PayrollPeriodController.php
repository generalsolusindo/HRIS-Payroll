<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hrd\StorePayrollPeriodRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollPeriodDeletionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollPeriodController extends Controller
{
    use ScopesToOwnCompany;

    public function index(Request $request): Response
    {
        $periods = PayrollPeriod::where('company_id', $request->user()->company_id)
            ->whereNull('archived_at')
            ->with([
                'runs' => fn ($query) => $query->latest('version')->limit(1),
                'deletionRequests' => fn ($query) => $query->latest('requested_at')->limit(1),
            ])
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (PayrollPeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'starts_on' => $period->starts_on->toDateString(),
                'ends_on' => $period->ends_on->toDateString(),
                'status' => $period->status,
                'latest_run' => $period->runs->first() ? [
                    'id' => $period->runs->first()->id,
                    'version' => $period->runs->first()->version,
                    'status' => $period->runs->first()->status,
                    'employee_count' => $period->runs->first()->employee_count,
                    'net_total' => (string) $period->runs->first()->net_total,
                ] : null,
                'can_request_deletion' => $period->runs->first() !== null
                    && ! in_array($period->runs->first()->status, ['approved', 'locked', 'partially_paid', 'paid'], true),
                'deletion_request' => $period->deletionRequests->first() ? [
                    'status' => $period->deletionRequests->first()->status,
                    'reason' => $period->deletionRequests->first()->reason,
                ] : null,
            ]);

        return Inertia::render('hrd/payroll-periods', ['periods' => $periods]);
    }

    public function store(StorePayrollPeriodRequest $request): RedirectResponse
    {
        $period = PayrollPeriod::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Periode {$period->name} berhasil dibuat."]);

        return back();
    }

    public function destroy(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $this->assertOwnCompany($request, $period->company_id);

        if ($period->runs()->exists()) {
            throw ValidationException::withMessages([
                'period' => "Periode {$period->name} sudah pernah dihitung — tidak bisa dihapus. Hapus hanya berlaku untuk periode yang belum pernah diproses sama sekali.",
            ]);
        }

        $name = $period->name;
        $period->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Periode {$name} berhasil dihapus."]);

        return back();
    }

    public function requestDeletion(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $this->assertOwnCompany($request, $period->company_id);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $period, $validated) {
            $lockedPeriod = PayrollPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();

            if ($lockedPeriod->archived_at !== null || ! $lockedPeriod->runs()->exists()) {
                throw ValidationException::withMessages([
                    'period' => 'Permintaan penghapusan hanya tersedia untuk periode payroll yang sudah dihitung dan belum diarsipkan.',
                ]);
            }

            $latestRun = $lockedPeriod->runs()->latest('version')->first();
            if (! $latestRun || in_array($latestRun->status, ['approved', 'locked', 'partially_paid', 'paid'], true)) {
                throw ValidationException::withMessages([
                    'period' => 'Periode tidak dapat diarsipkan setelah payroll disetujui atau pembayaran dimulai. Batalkan payroll terlebih dahulu jika masih memenuhi syarat.',
                ]);
            }

            if ($lockedPeriod->deletionRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'period' => 'Permintaan penghapusan periode ini masih menunggu keputusan Manager.',
                ]);
            }

            PayrollPeriodDeletionRequest::create([
                'payroll_period_id' => $lockedPeriod->id,
                'requested_by' => $request->user()->id,
                'requested_by_snapshot' => ['name' => $request->user()->name],
                'status' => 'pending',
                'reason' => $validated['reason'],
                'requested_at' => now(),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permintaan penghapusan dikirim dan menunggu persetujuan Manager.']);

        return back();
    }
}
