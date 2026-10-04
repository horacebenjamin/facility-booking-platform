<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class RoleAwareLoginResponse implements LoginResponse, TwoFactorLoginResponse
{
    public function __construct(private bool $twoFactor = false) {}

    /** @param Request $request */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return $this->twoFactor ? response()->json('', 204) : response()->json(['two_factor' => false]);
        }

        $user = $request->user();
        $response = redirect()->intended($user instanceof User ? $user->workspaceUrl() : route('home', absolute: false));
        $path = trim((string) parse_url($response->getTargetUrl(), PHP_URL_PATH), '/');

        if ($request->header('X-Inertia') && str($path)->is(['operations', 'operations/*', 'management', 'management/*'])) {
            return Inertia::location($response->getTargetUrl());
        }

        return $response;
    }
}
