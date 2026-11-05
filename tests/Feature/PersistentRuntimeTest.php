<?php

use App\Services\Api\ApiClient;
use App\Services\Api\Exceptions\ApiUnauthorized;
use App\Services\Session\DeviceSession;
use App\Support\ResetRequestState;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Native\Mobile\Runtime;

const RUNTIME_API = 'http://127.0.0.1:8799/api/v1';

beforeEach(function () {
    config(['services.inventory_api.url' => RUNTIME_API]);
});

/** What NativePHP does between two dispatches in the persistent runtime. */
function betweenNativeRequests(): void
{
    ResetRequestState::flush(app());
}

/** An API whose /cart answers 401 unless a bearer token is sent. */
function fakeApiRequiringTokenForCart(): void
{
    Http::fake([
        RUNTIME_API.'/me' => Http::response(['id' => 1, 'name' => 'Ann', 'email' => 'ann@example.com', 'phone' => null, 'role' => 'customer', 'shop_name' => null, 'status' => 'active']),
        RUNTIME_API.'/logout' => Http::response(null, 204),
        RUNTIME_API.'/settings/public' => Http::response(['store_name' => 'Violet Store', 'currency_symbol' => '$', 'currency_code' => 'USD', 'test_mode_message' => 'Test']),
        RUNTIME_API.'/cart' => fn (Request $r) => $r->hasHeader('Authorization')
            ? Http::response(['items' => [], 'subtotal' => '0.00'])
            : Http::response(['message' => 'Unauthenticated.'], 401),
    ]);
}

function customerSession(): void
{
    app(DeviceSession::class)->store('tok', [
        'id' => 1, 'name' => 'Ann', 'email' => 'ann@example.com', 'phone' => null,
        'role' => 'customer', 'shop_name' => null, 'status' => 'active',
    ]);
}

it('registers the reset hook with the native runtime', function () {
    $callbacks = (new ReflectionClass(Runtime::class))->getProperty('resetCallbacks')->getValue();

    expect($callbacks)->not->toBeEmpty();
});

it('lets a customer log out and land on a working login page in one persistent app', function () {
    customerSession();
    fakeApiRequiringTokenForCart();

    $this->get('/account')->assertOk();
    $this->post('/logout')->assertRedirect('/login');
    betweenNativeRequests();

    $this->get('/login')->assertOk();
});

it('does not loop even if the reset hook did not run', function () {
    customerSession();
    fakeApiRequiringTokenForCart();

    $this->get('/account')->assertOk();
    $this->post('/logout')->assertRedirect('/login');

    // No betweenNativeRequests(): the cart_count closure is still shared, and must be harmless.
    $this->get('/login')->assertOk();
});

it('never redirects the login page to itself on a 401', function () {
    fakeApiRequiringTokenForCart();
    $thrown = false;
    // Throws once (like a leftover shared closure would), then lets the Error page render.
    Inertia::share('boom', function () use (&$thrown) {
        if (! $thrown) {
            $thrown = true;
            throw new ApiUnauthorized(401);
        }
    });

    $this->get('/login')->assertStatus(401)->assertInertia(fn ($page) => $page->component('Error'));
});

it('skips the cart call when there is no token', function () {
    fakeApiRequiringTokenForCart();
    customerSession();
    $this->get('/account')->assertOk();
    app(DeviceSession::class)->clear();
    Http::fake()->preventStrayRequests();

    expect(Inertia::getShared('cart_count'))->toBeInstanceOf(Closure::class);
    $count = Inertia::getShared('cart_count')();

    expect($count)->toBe(0);
});

it('hands the next request a fresh ApiClient and settings after the reset', function () {
    $apiUp = false;
    Http::fake([RUNTIME_API.'/settings/public' => function () use (&$apiUp) {
        return $apiUp
            ? Http::response(['store_name' => 'Violet Store', 'currency_symbol' => '$', 'currency_code' => 'USD', 'test_mode_message' => 'Test'])
            : Http::failedConnection();
    }]);
    $first = app(ApiClient::class);
    expect($first->publicSettings()['store_name'])->toBe('Inventory')
        ->and(app(ApiClient::class))->toBe($first);

    $apiUp = true;
    betweenNativeRequests();

    $second = app(ApiClient::class);
    expect($second)->not->toBe($first)
        ->and($second->publicSettings()['store_name'])->toBe('Violet Store');
});

it('flushes shared props between requests', function () {
    Inertia::share('leftover', 'x');
    betweenNativeRequests();

    expect(Inertia::getShared('leftover'))->toBeNull();
});
