<?php

namespace App\Http\Middleware;

use App\Services\Session\DeviceSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Alias `app.role:{customer|seller}`: a mismatch redirects to the user's own home. */
class EnsureRole
{
    public const HOMES = ['customer' => '/shop', 'seller' => '/seller/products'];

    public function __construct(private DeviceSession $session) {}

    public function handle(Request $request, Closure $next, string $role): Response
    {
        $actual = $this->session->user()['role'] ?? null;

        if ($actual === $role) {
            return $next($request);
        }

        return redirect(self::HOMES[$actual] ?? '/login');
    }
}
