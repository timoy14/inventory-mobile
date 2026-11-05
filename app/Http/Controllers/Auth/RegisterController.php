<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Api\ApiClient;
use App\Services\Session\DeviceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function show(ApiClient $api, DeviceSession $session): Response|RedirectResponse
    {
        if ($session->signedInUser() !== null) {
            return redirect('/');
        }

        return Inertia::render('auth/Register', [
            'settings' => $api->publicSettings(),
            'flash_error' => session('error'),
        ]);
    }

    public function store(Request $request, DeviceSession $session): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'role' => ['required', Rule::in(['customer', 'seller'])],
            'phone' => ['nullable', 'string', 'max:50'],
            'shop_name' => ['nullable', 'required_if:role,seller', 'string', 'max:255'],
        ]);

        if ($data['role'] !== 'seller') {
            unset($data['shop_name']);
        }

        $result = ApiClient::anonymous()->post('/register', array_filter($data, fn ($v) => $v !== null && $v !== ''));
        $session->store($result['token'], $result['user']);

        return redirect('/');
    }
}
