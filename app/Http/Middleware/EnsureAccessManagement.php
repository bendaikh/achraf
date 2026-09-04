<?php

namespace App\Http\Middleware;

use App\Support\AccessPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccessManagement
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! AccessPermission::canManageAccess($user)) {
            abort(403, 'Accès réservé à l\'administration des utilisateurs.');
        }

        return $next($request);
    }
}
