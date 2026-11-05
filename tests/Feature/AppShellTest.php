<?php

use App\Services\Session\DeviceSession;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const SHELL_API = 'http://127.0.0.1:8799/api/v1';

beforeEach(function () {
    config(['services.inventory_api.url' => SHELL_API]);
});

function shellSignIn(string $role): void
{
    app(DeviceSession::class)->store('tok', [
        'id' => 1, 'name' => 'Ann', 'email' => 'ann@example.com', 'phone' => null,
        'role' => $role, 'shop_name' => null, 'status' => 'active',
    ]);
}

it('redirects a seller visiting a customer URL to the seller home', function (string $path) {
    shellSignIn('seller');

    $this->get($path)->assertRedirect('/seller/products');
})->with(['/cart']);

it('redirects a customer visiting a seller URL to the shop', function () {
    shellSignIn('customer');

    $this->get('/seller/products')->assertRedirect('/shop');
});

it('redirects a guest to login', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with(['/cart', '/shop', '/seller/products', '/account']);

it('lets a seller open the shop tab', function () {
    shellSignIn('seller');
    Http::fake([
        SHELL_API.'/products*' => Http::response(['data' => [], 'links' => ['next' => null], 'meta' => []]),
        SHELL_API.'/categories' => Http::response(['data' => []]),
        SHELL_API.'/settings/public' => Http::response(['store_name' => 'S', 'currency_symbol' => '$', 'currency_code' => 'USD', 'test_mode_message' => 'T']),
    ]);

    $this->get('/shop')->assertOk();
});

it('shares cart_count as the sum of quantities for a customer', function () {
    shellSignIn('customer');
    Http::fake([
        SHELL_API.'/products*' => Http::response(['data' => [], 'links' => ['next' => null], 'meta' => []]),
        SHELL_API.'/categories' => Http::response(['data' => []]),
        SHELL_API.'/cart' => Http::response(['items' => [['product_id' => 1, 'quantity' => 2], ['product_id' => 2, 'quantity' => 3]], 'subtotal' => '10.00']),
        SHELL_API.'/settings/public' => Http::response(['store_name' => 'S', 'currency_symbol' => '$', 'currency_code' => 'USD', 'test_mode_message' => 'T']),
    ]);

    $this->get('/shop')->assertInertia(fn (Assert $p) => $p->where('cart_count', 5));
});

it('shares cart_count 0 when the API is unavailable', function () {
    shellSignIn('customer');
    Http::fake([
        SHELL_API.'/products*' => Http::response(['data' => [], 'links' => ['next' => null], 'meta' => []]),
        SHELL_API.'/categories' => Http::response(['data' => []]),
        SHELL_API.'/cart' => Http::response('', 500),
        SHELL_API.'/settings/public' => Http::response(['store_name' => 'S', 'currency_symbol' => '$', 'currency_code' => 'USD', 'test_mode_message' => 'T']),
    ]);

    $this->get('/shop')->assertInertia(fn (Assert $p) => $p->where('cart_count', 0));
});

it('does not share cart_count for a seller', function () {
    shellSignIn('seller');
    Http::fake([
        SHELL_API.'/settings/public' => Http::response(['store_name' => 'S', 'currency_symbol' => '$', 'currency_code' => 'USD', 'test_mode_message' => 'T']),
        SHELL_API.'/seller/products*' => Http::response(['data' => [], 'links' => ['next' => null]]),
    ]);

    $this->get('/seller/products')->assertInertia(fn (Assert $p) => $p->missing('cart_count'));
    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/cart'));
});

it('shares flash success and error messages', function () {
    shellSignIn('customer');
    Http::fake(['*' => Http::response([])]);

    $this->withSession(['success' => 'Saved', 'error' => 'Nope'])->get('/shop')
        ->assertInertia(fn (Assert $p) => $p->where('flash.success', 'Saved')->where('flash.error', 'Nope'));
});
