<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Api\ApiClient;
use App\Services\Api\Exceptions\ApiUnauthorized;
use App\Services\Api\Exceptions\ApiUnavailable;
use App\Support\Auth\SignOut;
use Illuminate\Http\RedirectResponse;

class LogoutController extends Controller
{
    public function __invoke(ApiClient $api): RedirectResponse
    {
        try {
            $api->post('/logout');
        } catch (ApiUnauthorized|ApiUnavailable) {
            // The token is already dead or the API is unreachable: signing out locally is what matters.
        } finally {
            SignOut::run();
        }

        return redirect('/login');
    }
}
