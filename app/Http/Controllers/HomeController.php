<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureRole;
use App\Services\Session\DeviceSession;
use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    public function __invoke(DeviceSession $session): RedirectResponse
    {
        $role = $session->signedInUser()['role'] ?? null;

        return redirect(EnsureRole::HOMES[$role] ?? '/login');
    }
}
