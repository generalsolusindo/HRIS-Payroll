<?php

namespace App\Services;

use App\Models\PayrollEntry;
use App\Models\PayrollEntryLine;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\Storage;

/**
 * Renders one employee's payroll entry as a printable slip gaji PDF,
 * matching the company's existing paper template (letterhead, two-column
 * penghasilan/potongan grid, bank box, terbilang line, signature block).
 * Shared across HRD (even pre-approval, straight from the draft entry),
 * Manager, and Finance — none of them need a "published" PayrollPayslip
 * record to download one, only the entry itself.
 */
class PayslipPdfService
{
    private const INDONESIAN_MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    private const EMPLOYMENT_TYPE_LABELS = [
        'permanent' => 'Tetap',
        'contract' => 'Kontrak',
        'part_time' => 'Paruh Waktu',
        'daily' => 'Harian Lepas',
        'freelance' => 'Freelance',
        'outsourcing' => 'Outsourcing',
        'intern' => 'Magang',
        'volunteer' => 'Relawan',
        'probation' => 'Masa Percobaan',
    ];

    public function build(PayrollEntry $entry): DomPdf
    {
        $entry->loadMissing(['lines', 'run.period.company', 'user']);
        $company = $entry->run->period->company;
        $user = $entry->user;
        $period = $entry->run->period;

        $earnings = $entry->lines->where('type', 'earning');
        $deductions = $entry->lines->where('type', 'deduction');

        $data = [
            'company' => $company,
            'user' => $user,
            'entry' => $entry,
            'periodLabel' => 'Bulan '.self::INDONESIAN_MONTHS[(int) $period->starts_on->format('n')].' '.$period->starts_on->format('Y'),
            'statusLabel' => $this->statusLabel($user),
            'earnings' => $this->mapLines($earnings),
            'deductions' => $this->mapLines($deductions),
            'destination' => $this->currentDestination($user),
            'grossTotal' => $entry->gross_amount,
            'deductionTotal' => $entry->deduction_amount,
            'netAmount' => $entry->net_amount,
            'terbilang' => Terbilang::rupiah((int) round((float) $entry->net_amount)),
            'issueCity' => $company->city ?: '-',
            'issueDateLabel' => now()->format('d').' '.self::INDONESIAN_MONTHS[(int) now()->format('n')].' '.now()->format('Y'),
            'logoPath' => public_path('images/general-solusindo.png'),
            'signaturePath' => $company->director_signature_path
                ? Storage::disk('company_signatures')->path($company->director_signature_path)
                : null,
        ];

        return Pdf::loadView('payslips.slip', $data)->setPaper('a4', 'portrait');
    }

    /**
     * Lines have no guaranteed DB row order (no ORDER BY on the relation,
     * and recalculation can delete/recreate individual lines), so the slip
     * must impose its own order explicitly rather than trust row order —
     * salary components first (Gaji Pokok, Tunjangan, ...), then earned
     * bonuses, then attendance-based allowances last.
     */
    private const LINE_KEY_PRIORITY = [
        'overtime' => 1,
        'attendance-perfect-reward' => 1,
        'attendance-transport' => 2,
        'attendance-allowance' => 3,
        'attendance-late' => 4,
        'attendance-absence' => 4,
    ];

    /**
     * @param  \Illuminate\Support\Collection<int, PayrollEntryLine>  $lines
     * @return array<int, array{label: string, amount: string}>
     */
    private function mapLines(\Illuminate\Support\Collection $lines): array
    {
        $lines = $lines->sortBy(fn (PayrollEntryLine $line) => self::LINE_KEY_PRIORITY[$line->line_key] ?? 0);

        return $lines->map(function (PayrollEntryLine $line) {
            $label = match ($line->line_key) {
                'attendance-transport' => "Tj. Transportasi {$this->formatRate($line->rate)}*{$this->formatQty($line->quantity)}",
                'attendance-allowance' => "Tj. Kedatangan {$this->formatRate($line->rate)}*{$this->formatQty($line->quantity)}",
                default => $line->component_name,
            };

            return ['label' => $label, 'amount' => $line->amount];
        })->values()->all();
    }

    private function formatRate(string $rate): string
    {
        return number_format((float) $rate, 0, ',', '');
    }

    private function formatQty(string $quantity): string
    {
        $value = (float) $quantity;

        return $value === floor($value) ? (string) (int) $value : rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
    }

    private function statusLabel(User $user): string
    {
        $label = self::EMPLOYMENT_TYPE_LABELS[$user->employment_type ?? ''] ?? 'Karyawan';

        if ($user->employment_type === 'contract' && $user->employment_started_on && $user->contract_ends_on) {
            $months = $user->employment_started_on->diffInMonths($user->contract_ends_on);

            if ($months > 0) {
                return "Kontrak {$months} Bulan";
            }
        }

        return $label;
    }

    /**
     * Same reasoning as Finance\PayrollPaymentController::currentDestination()
     * — read live off the User record rather than a run-time snapshot, so
     * a bank account filled in after the run was generated still shows up.
     *
     * @return array{bank_name: ?string, account_number: ?string, account_holder: ?string}|null
     */
    private function currentDestination(User $user): ?array
    {
        if (! $user->bank_account_number) {
            return null;
        }

        return [
            'bank_name' => $user->bank_name,
            'account_number' => $user->bank_account_number,
            'account_holder' => $user->bank_account_holder,
        ];
    }
}
