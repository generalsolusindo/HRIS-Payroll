<?php

namespace App\Services;

use App\Models\PayrollEntry;
use App\Models\PayrollRun;

class PayrollTotals
{
    /** Caller must hold the run lock while changing its entries. */
    public function refreshEntry(PayrollEntry $entry): void
    {
        $totals = ['earning' => '0.00', 'deduction' => '0.00', 'employer_contribution' => '0.00'];
        $reward = '0.00';
        foreach ($entry->lines()->get() as $line) {
            $amount = PayrollMoney::decimal($line->amount);
            $totals[$line->type] = bcadd($totals[$line->type], $amount, 2);
            if ($line->type === 'earning' && $line->source === 'reward') {
                $reward = bcadd($reward, $amount, 2);
            }
        }
        $entry->update([
            'gross_amount' => $totals['earning'], 'deduction_amount' => $totals['deduction'],
            'employer_contribution_amount' => $totals['employer_contribution'],
            'reward_amount' => $reward, 'net_amount' => bcsub($totals['earning'], $totals['deduction'], 2),
        ]);
    }

    public function refreshRun(PayrollRun $run): void
    {
        $totals = ['gross_total' => '0.00', 'deduction_total' => '0.00', 'net_total' => '0.00', 'reward_total' => '0.00', 'employer_contribution_total' => '0.00'];
        $entries = $run->entries()->get();
        foreach ($entries as $entry) {
            foreach (['gross', 'deduction', 'net', 'reward', 'employer_contribution'] as $field) {
                $totals[$field.'_total'] = bcadd($totals[$field.'_total'], PayrollMoney::decimal($entry->{$field.'_amount'}), 2);
            }
        }
        $run->update([...$totals, 'employee_count' => $entries->count()]);
    }
}
