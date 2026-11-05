<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Api\ApiClient;
use App\Services\Session\DeviceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function show(ApiClient $api, DeviceSession $session): Response|RedirectResponse
    {
        if ($session->signedInUser() !== null) {
            return redirect('/');
        }

        return Inertia::render('auth/Login', [
            'settings' => $api->publicSettings(),
            'flash_error' => session('error'),
        ]);
    }

    public function store(Request $request, DeviceSession $session): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string'], 'password' => ['required', 'string']]);

        // Anonymous client: never send a stale stored token to the login endpoint.
        $result = ApiClient::anonymous()->post('/login', $data + ['device_name' => 'inventory-mobile']);
        $session->store($result['token'], $result['user']);

        return redirect('/');
    }
}
