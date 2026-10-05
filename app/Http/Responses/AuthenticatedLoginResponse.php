<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatedLoginResponse implements LoginResponse, PasskeyLoginResponse, TwoFactorLoginResponse
{
    public function toResponse($request): Response
    {
        $user = $request->user();
        if (! $user?->isActive()) {
            $request->session()->forget('url.intended');

            return $request->wantsJson()
                ? response()->json(['code' => 'account_inactive', 'redirect' => route('pending-approval')], 403)
                : redirect()->route('pending-approval');
        }

        if (config('face.verification_enabled') && ! $user->hasFaceEnrolled()) {
            // Enrollment takes precedence over an arbitrary intended URL.
            $request->session()->forget('url.intended');
            $redirect = redirect()->route('face.edit');
        } else {
            $redirect = redirect()->intended(Fortify::redirects('login'));
        }

        return $request->wantsJson()
            ? response()->json(['two_factor' => false, 'redirect' => $redirect->getTargetUrl()])
            : $redirect;
    }
}
