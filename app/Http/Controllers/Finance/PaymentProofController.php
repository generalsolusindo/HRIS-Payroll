<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\PayrollPayment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function show(PayrollPayment $payment): StreamedResponse
    {
        // Gated by the route's auth/active/role:finance middleware.
        abort_if($payment->proof_path === null, 404);
        abort_unless(Storage::disk('payment_proofs')->exists($payment->proof_path), 404);

        return Storage::disk('payment_proofs')->response($payment->proof_path);
    }
}
