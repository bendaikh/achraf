<?php

namespace App\Http\Middleware;

use App\Support\HrPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHrPermission
{
    public function handle(Request $request, Closure $next, string $permission = HrPermission::VIEW): Response
    {
        $user = $request->user();
        if (! HrPermission::allows($user, $permission)) {
            abort(403, 'Accès RH non autorisé.');
        }

        return $next($request);
    }
}
