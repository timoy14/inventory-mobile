<?php

namespace App\Http\Middleware;

use App\Services\Api\ApiClient;
use App\Services\Api\Exceptions\ApiException;
use App\Services\Api\Exceptions\ApiUnauthorized;
use App\Services\Session\DeviceSession;
use App\Support\ApiData;
use App\Support\Auth\SignOut;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Alias `app.auth`: requires a stored token; shares auth.user and public settings. */
class EnsureSignedIn
{
    public function __construct(private DeviceSession $session, private ApiClient $api) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->session->signedInUser();

        if ($user === null) {
            SignOut::run();

            return redirect('/login');
        }

        Inertia::share([
            'auth' => ['user' => $user],
            'settings' => fn () => $this->api->publicSettings(),
        ]);

        if (($user['role'] ?? null) === 'customer') {
            Inertia::share('cart_count', fn () => $this->cartCount());
        }

        return $next($request);
    }

    /** Sum of cart item quantities; 0 when the API cannot answer (a 401 still signs the user out). */
    private function cartCount(): int
    {
        // Never make an anonymous call: the API would 401 and the handler would redirect.
        if ($this->session->token() === null) {
            return 0;
        }

        try {
            $items = $this->api->cart()['items'] ?? [];
        } catch (ApiUnauthorized $e) {
            throw $e;
        } catch (ApiException) {
            return 0;
        }

        return (int) collect(ApiData::array($items))->sum('quantity');
    }
}
