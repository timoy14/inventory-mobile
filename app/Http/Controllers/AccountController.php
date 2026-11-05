<?php

namespace App\Http\Controllers;

use App\Services\Api\ApiClient;
use App\Services\Api\Exceptions\ApiUnavailable;
use App\Services\Session\DeviceSession;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __invoke(ApiClient $api, DeviceSession $session): Response
    {
        $user = $session->user();

        try {
            $user = $api->get('/me');
            $session->storeUser($user);
        } catch (ApiUnavailable) {
            // Offline: show the stored user.
        }

        return Inertia::render('Account', ['user' => $user]);
    }
}
