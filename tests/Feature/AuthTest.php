<?php

use App\Services\Api\Exceptions\ApiUnavailable;
use App\Services\Session\DeviceSession;
use App\Support\Auth\SignOut;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

const AUTH_API = 'http://127.0.0.1:8799/api/v1';

beforeEach(function () {
    config(['services.inventory_api.url' => AUTH_API]);
});

function apiUser(string $role = 'customer', array $extra = []): array
{
    return array_merge([
        'id' => 1, 'name' => 'Ann', 'email' => 'ann@example.com', 'phone' => null,
        'role' => $role, 'shop_name' => $role === 'seller' ? 'Ann Shop' : null, 'status' => 'active',
    ], $extra);
}

function signIn(string $role = 'customer'): void
{
    app(DeviceSession::class)->store('plain-token-123', apiUser($role));
}

function fakeSettings(): array
{
    return [AUTH_API.'/seller/products*' => Http::response(['data' => [], 'links' => ['next' => null]]), AUTH_API.'/products*' => Http::response(['data' => [], 'links' => ['next' => null], 'meta' => []]), AUTH_API.'/categories' => Http::response(['data' => []]), AUTH_API.'/cart' => Http::response(['items' => [], 'subtotal' => '0.00']), AUTH_API.'/settings/public' => Http::response(['store_name' => 'Violet Store', 'currency_symbol' => '$', 'currency_code' => 'USD', 'test_mode_message' => 'Test'])];
}

it('stores the token and user encrypted at rest after login', function () {
    Http::fake([AUTH_API.'/login' => Http::response(['token' => 'plain-token-123', 'user' => apiUser()])] + fakeSettings());

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'secret123'])->assertRedirect('/');

    $raw = DB::table('device_session')->pluck('value', 'key')->all();
    expect(json_encode($raw))->not->toContain('plain-token-123')->not->toContain('ann@example.com')
        ->and(Crypt::decryptString($raw['token']))->toBe('plain-token-123')
        ->and(app(DeviceSession::class)->token())->toBe('plain-token-123')
        ->and(app(DeviceSession::class)->user()['email'])->toBe('ann@example.com');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/login')
        && $r['device_name'] === 'inventory-mobile' && $r['email'] === 'ann@example.com');
});

it('shows field errors for a wrong password', function () {
    Http::fake([AUTH_API.'/login' => Http::response(['message' => 'bad', 'errors' => ['email' => ['These credentials do not match our records.']]], 422)] + fakeSettings());

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'nope'])
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
    expect(app(DeviceSession::class)->token())->toBeNull();
});

it('shows the API 422 message for an admin account', function () {
    Http::fake([AUTH_API.'/login' => Http::response(['message' => 'x', 'errors' => ['email' => ['Admins must use the web panel.']]], 422)]);

    $this->post('/login', ['email' => 'admin@example.com', 'password' => 'secret123'])
        ->assertSessionHasErrors(['email' => 'Admins must use the web panel.']);
});

it('shows the suspended flash on login', function () {
    Http::fake([AUTH_API.'/login' => Http::response(['error' => 'account_suspended', 'message' => 'Suspended'], 403)] + fakeSettings());

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'secret123'])
        ->assertRedirect('/login')->assertSessionHas('error', 'Your account has been suspended');

    $this->get('/login')->assertInertia(fn (Assert $p) => $p->component('auth/Login')
        ->where('flash_error', 'Your account has been suspended'));
});

it('renders the login page with the store name', function () {
    Http::fake(fakeSettings());

    $this->get('/login')->assertInertia(fn (Assert $p) => $p->component('auth/Login')
        ->where('settings.store_name', 'Violet Store'));
});

it('registers a customer and a seller', function (string $role, array $extra) {
    Http::fake([AUTH_API.'/register' => Http::response(['token' => 'tok-r', 'user' => apiUser($role)], 201)] + fakeSettings());

    $this->post('/register', ['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'secret123',
        'password_confirmation' => 'secret123', 'role' => $role] + $extra)->assertRedirect('/');

    expect(app(DeviceSession::class)->token())->toBe('tok-r');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/register') && $r['role'] === $role
        && (($r['shop_name'] ?? null) === ($role === 'seller' ? 'Ann Shop' : null)));
})->with([
    'customer' => ['customer', ['shop_name' => 'ignored-for-customers']],
    'seller' => ['seller', ['shop_name' => 'Ann Shop', 'phone' => '555']],
]);

it('does not send shop_name for customers', function () {
    Http::fake([AUTH_API.'/register' => Http::response(['token' => 't', 'user' => apiUser()], 201)]);

    $this->post('/register', ['name' => 'Ann', 'email' => 'a@b.co', 'password' => 'secret123',
        'password_confirmation' => 'secret123', 'role' => 'customer', 'shop_name' => 'x']);

    Http::assertSent(fn (Request $r) => ! isset($r['shop_name']));
});

it('requires password confirmation and a shop name for sellers on register', function () {
    Http::fake(fakeSettings());

    $this->post('/register', ['name' => 'Ann', 'email' => 'a@b.co', 'password' => 'secret123', 'role' => 'seller'])
        ->assertSessionHasErrors(['password', 'shop_name']);
});

it('shows API validation errors on register', function () {
    Http::fake([AUTH_API.'/register' => Http::response(['errors' => ['email' => ['taken']]], 422)]);

    $this->post('/register', ['name' => 'Ann', 'email' => 'a@b.co', 'password' => 'secret123',
        'password_confirmation' => 'secret123', 'role' => 'customer'])->assertSessionHasErrors(['email' => 'taken']);
});

it('logout calls the API and clears the session', function () {
    signIn();
    Http::fake([AUTH_API.'/logout' => Http::response(null, 204)]);

    $this->post('/logout')->assertRedirect('/login');

    expect(app(DeviceSession::class)->token())->toBeNull();
    expect(DB::table('device_session')->count())->toBe(0);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/logout') && $r->hasHeader('Authorization', 'Bearer plain-token-123'));
});

it('logout still clears locally when the API says 401 or is down', function (callable $response) {
    signIn();
    Http::fake([AUTH_API.'/logout' => $response]);

    $this->post('/logout')->assertRedirect('/login');
    expect(app(DeviceSession::class)->token())->toBeNull();
})->with([
    '401' => [fn () => Http::response(['message' => 'Unauthenticated.'], 401)],
    'down' => [fn () => Http::failedConnection()],
]);

it('treats an undecryptable stored value as a guest and clears it', function () {
    DB::table('device_session')->insert([
        ['key' => 'token', 'value' => 'garbage-not-encrypted'],
        ['key' => 'user', 'value' => 'garbage'],
    ]);
    Http::fake(fakeSettings());

    expect(app(DeviceSession::class)->token())->toBeNull();
    expect(DB::table('device_session')->count())->toBe(0);

    DB::table('device_session')->insert(['key' => 'user', 'value' => 'garbage']);
    expect(app(DeviceSession::class)->user())->toBeNull();
    expect(DB::table('device_session')->count())->toBe(0);
});

it('renders /login for a stale undecryptable token without looping', function () {
    DB::table('device_session')->insert([
        ['key' => 'token', 'value' => 'garbage'], ['key' => 'user', 'value' => 'garbage'],
    ]);
    Http::fake(fakeSettings());

    $this->get('/login')->assertOk()->assertInertia(fn (Assert $p) => $p->component('auth/Login'));
    $this->get('/register')->assertOk()->assertInertia(fn (Assert $p) => $p->component('auth/Register'));
    $this->get('/account')->assertRedirect('/login');
});

it('redirects / by role', function (?string $role, string $to) {
    if ($role) {
        signIn($role);
    }

    $this->get('/')->assertRedirect($to);
})->with([[null, '/login'], ['customer', '/shop'], ['seller', '/seller/products']]);

it('redirects a signed-in user away from guest pages', function () {
    signIn('seller');

    $this->get('/login')->assertRedirect('/');
});

it('gates role routes by redirecting to the own home', function () {
    Http::fake(fakeSettings());

    signIn('seller');
    $this->get('/cart')->assertRedirect('/seller/products');
    $this->get('/seller/products')->assertOk();

    signIn('customer');
    $this->get('/seller/products')->assertRedirect('/shop');
    $this->get('/shop')->assertOk();
    $this->get('/cart')->assertOk();
});

it('redirects guests from protected routes to login', function () {
    $this->get('/shop')->assertRedirect('/login');
    $this->get('/account')->assertRedirect('/login');
});

it('shares auth.user and settings on authenticated pages', function () {
    signIn();
    Http::fake(fakeSettings());

    $this->get('/shop')->assertInertia(fn (Assert $p) => $p->where('auth.user.email', 'ann@example.com')
        ->where('settings.store_name', 'Violet Store'));
});

it('sends the stored bearer from the resolver after login', function () {
    Http::fake([AUTH_API.'/login' => Http::response(['token' => 'tok-after', 'user' => apiUser()]),
        AUTH_API.'/me' => Http::response(apiUser())] + fakeSettings());

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'secret123']);
    $this->get('/account');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/me') && $r->hasHeader('Authorization', 'Bearer tok-after'));
});

it('account shows the refreshed user from /me', function () {
    signIn();
    Http::fake([AUTH_API.'/me' => Http::response(apiUser('customer', ['name' => 'Ann Updated', 'phone' => '555']))] + fakeSettings());

    $this->get('/account')->assertInertia(fn (Assert $p) => $p->component('Account')
        ->where('user.name', 'Ann Updated')->where('user.phone', '555'));
    expect(app(DeviceSession::class)->user()['name'])->toBe('Ann Updated');
});

it('account falls back to the stored user when the API is down', function () {
    signIn();
    Http::fake([AUTH_API.'/me' => Http::failedConnection()] + fakeSettings());

    $this->get('/account')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Account')
        ->where('user.name', 'Ann'));
});

it('signs out on a 401 from any page', function () {
    signIn();
    Http::fake([AUTH_API.'/me' => Http::response(['message' => 'Unauthenticated.'], 401)] + fakeSettings());

    $this->get('/account')->assertRedirect('/login');
    expect(app(DeviceSession::class)->token())->toBeNull();
});

it('SignOut::run clears the session', function () {
    signIn();
    SignOut::run();

    expect(app(DeviceSession::class)->token())->toBeNull();
});

it('renders a 403 Forbidden as the Error page', function () {
    signIn();
    Http::fake([AUTH_API.'/me' => Http::response(['message' => 'No'], 403)] + fakeSettings());

    $this->get('/account')->assertStatus(403)->assertInertia(fn (Assert $p) => $p->component('Error')
        ->where('status', 403)->where('message', "You don't have access to that."));
});

it('renders other unhandled API errors as a 502 Error page', function () {
    signIn();
    Http::fake([AUTH_API.'/me' => Http::response(['message' => 'teapot'], 418)] + fakeSettings());

    $this->get('/account')->assertStatus(502)->assertInertia(fn (Assert $p) => $p->component('Error')
        ->where('status', 502)->where('message', 'Something went wrong talking to the store.'));
});

it('still renders Offline for ApiUnavailable rather than the generic Error page', function () {
    signIn();
    Http::fake([AUTH_API.'/me' => Http::response([], 503)] + fakeSettings());

    $this->get('/account')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Account'));

    Route::middleware('web')->get('/_down', fn () => throw new ApiUnavailable(0));
    $this->get('/_down')->assertInertia(fn (Assert $p) => $p->component('Offline'));
});
