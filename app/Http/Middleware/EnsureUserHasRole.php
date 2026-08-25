<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = $user instanceof User && collect($roles)->contains(
            fn (string $role): bool => match ($role) {
                'admin' => $user->isAdmin(),
                'technician' => $user->isTechnician(),
                'customer' => $user->isCustomer(),
                default => $user->role === $role,
            },
        ) && $user->hasActiveAccount();

        abort_unless($allowed, 403);

        return $next($request);
    }
}
