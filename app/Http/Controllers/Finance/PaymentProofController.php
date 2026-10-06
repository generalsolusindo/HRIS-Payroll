<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Concerns\ScopesToOwnCompany;
use App\Http\Controllers\Controller;
use App\Models\PayrollPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    use ScopesToOwnCompany;

    public function show(Request $request, PayrollPayment $payment): StreamedResponse
    {
        // Gated by the route's auth/active/role:finance middleware, plus an
        // explicit tenant check: role alone doesn't imply "same company".
        $this->assertOwnCompany($request, $payment->entry->run->period->company_id);

        abort_if($payment->proof_path === null, 404);
        abort_unless(Storage::disk('payment_proofs')->exists($payment->proof_path), 404);

        return Storage::disk('payment_proofs')->response($payment->proof_path);
    }
}
