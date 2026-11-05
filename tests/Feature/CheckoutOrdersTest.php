<?php

use App\Services\Session\DeviceSession;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const CO_API = 'http://10.0.2.2:8799/api/v1';
const CO_MESSAGE = 'Checkout says: test only.';

beforeEach(function () {
    config(['services.inventory_api.url' => CO_API]);
    app(DeviceSession::class)->store('tok', [
        'id' => 1, 'name' => 'Ann', 'email' => 'ann@example.com', 'phone' => null,
        'role' => 'customer', 'shop_name' => null, 'status' => 'active',
    ]);
});

function coOrder(array $extra = []): array
{
    return array_merge([
        'id' => 12, 'number' => 'ORD-0012', 'status' => 'pending', 'payment_status' => 'unpaid',
        'subtotal' => '39.80', 'total' => '39.80', 'paid_at' => null, 'created_at' => '2026-11-01T10:00:00Z',
        'items' => [['product_id' => 7, 'product_name' => 'Lamp', 'unit_price' => '19.90', 'quantity' => 2, 'line_total' => '39.80']],
    ], $extra);
}

function coFakes(array $extra = []): array
{
    return $extra + [
        CO_API.'/settings/public*' => Http::response(['store_name' => 'S', 'currency_symbol' => '$', 'currency_code' => 'USD', 'test_mode_message' => 'Settings message']),
        CO_API.'/cart' => Http::response(['items' => [], 'subtotal' => '0.00']),
    ];
}

it('creates the order and redirects to the checkout page', function () {
    Http::fake(coFakes([CO_API.'/checkout' => Http::response(['order' => coOrder(), 'test_mode_message' => CO_MESSAGE], 201)]));

    $this->post('/checkout')->assertRedirect('/checkout/12');
});

it('shows the checkout page with the API test mode message and the order', function () {
    Http::fake(coFakes([
        CO_API.'/checkout' => Http::response(['order' => coOrder(), 'test_mode_message' => CO_MESSAGE], 201),
        CO_API.'/orders/12' => Http::response(coOrder()),
    ]));

    $this->post('/checkout');
    $this->get('/checkout/12')->assertInertia(fn (Assert $p) => $p
        ->component('checkout/Show')
        ->where('test_mode_message', CO_MESSAGE)
        ->where('order.number', 'ORD-0012')
        ->where('order.total', '39.80')
        ->where('order.items.0.product_name', 'Lamp'));
});

it('falls back to the public settings message on the checkout page', function () {
    Http::fake(coFakes([CO_API.'/orders/12' => Http::response(coOrder())]));

    $this->get('/checkout/12')->assertInertia(fn (Assert $p) => $p->where('test_mode_message', 'Settings message'));
});

it('redirects checking out a non-pending order to the order page', function () {
    Http::fake(coFakes([CO_API.'/orders/12' => Http::response(coOrder(['status' => 'paid', 'payment_status' => 'paid']))]));

    $this->get('/checkout/12')->assertRedirect('/orders/12');
});

it('marks the lines on an insufficient stock checkout', function () {
    Http::fake(coFakes([CO_API.'/checkout' => Http::response(['message' => 'Not enough stock', 'error' => 'insufficient_stock', 'product_ids' => [7, 9]], 422)]));

    $this->post('/checkout')->assertRedirect('/cart')
        ->assertSessionHas('unavailable_ids', [7, 9])
        ->assertSessionHas('error', 'Not enough stock');

    $this->get('/cart')->assertInertia(fn (Assert $p) => $p->where('unavailable_ids', [7, 9]));
});

it('keeps default handling for other domain errors at checkout', function () {
    Http::fake(coFakes([CO_API.'/checkout' => Http::response(['message' => 'Cart is empty', 'error' => 'cart_empty'], 422)]));

    $this->from('/cart')->post('/checkout')->assertRedirect('/cart')
        ->assertSessionHas('error', 'Cart is empty')->assertSessionMissing('unavailable_ids');
});

it('pays and shows the reference on the paid page', function () {
    Http::fake(coFakes([
        CO_API.'/orders/12/pay' => Http::response(['order' => coOrder(['status' => 'paid']), 'payment' => ['reference' => 'TEST-ABC123', 'method' => 'test'], 'test_mode_message' => CO_MESSAGE]),
        CO_API.'/orders/12' => Http::response(coOrder(['status' => 'paid', 'payment_status' => 'paid', 'paid_at' => '2026-11-01T10:05:00Z'])),
    ]));

    $this->post('/orders/12/pay')->assertRedirect('/orders/12/paid');
    $this->get('/orders/12/paid')->assertInertia(fn (Assert $p) => $p
        ->component('checkout/Paid')
        ->where('reference', 'TEST-ABC123')
        ->where('test_mode_message', CO_MESSAGE)
        ->where('order.number', 'ORD-0012')
        ->where('order.total', '39.80'));
});

it('renders the paid page without a reference but with the message once the stash is gone', function () {
    Http::fake(coFakes([
        CO_API.'/orders/12/pay' => Http::response(['order' => coOrder(['status' => 'paid']), 'payment' => ['reference' => 'TEST-ABC123', 'method' => 'test'], 'test_mode_message' => CO_MESSAGE]),
        CO_API.'/orders/12' => Http::response(coOrder(['status' => 'paid', 'payment_status' => 'paid'])),
    ]));

    $this->post('/orders/12/pay');
    $this->get('/orders/12/paid');
    $this->get('/orders/12/paid')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('checkout/Paid')
        ->where('reference', null)
        ->where('test_mode_message', 'Settings message')
        ->where('order.number', 'ORD-0012'));
});

it('sends a repeated pay to the order page with a toast, not an error page', function () {
    Http::fake(coFakes([
        CO_API.'/orders/12/pay' => Http::response(['message' => 'This order cannot be paid.', 'error' => 'order_not_payable'], 422),
    ]));

    $this->post('/orders/12/pay')->assertRedirect('/orders/12')
        ->assertSessionHas('error', 'This order cannot be paid.');
});

it('does not call the API twice when a second pay arrives while the first holds the lock', function () {
    Http::fake(coFakes());
    $lock = Cache::lock('pay:12', 10);
    expect($lock->get())->toBeTrue();

    $this->post('/orders/12/pay')->assertRedirect('/orders/12')->assertSessionHas('error');

    Http::assertSentCount(0);
    $lock->release();
});

it('releases the pay lock after paying so a retry can reach the API', function () {
    Http::fake(coFakes([
        CO_API.'/orders/12/pay' => Http::response(['message' => 'No', 'error' => 'order_not_payable'], 422),
    ]));

    $this->post('/orders/12/pay');
    $this->post('/orders/12/pay');

    Http::assertSentCount(2);
});

it('lists orders newest first with a local next link', function () {
    Http::fake(coFakes([
        CO_API.'/orders*' => Http::response([
            'data' => [coOrder(['id' => 13, 'number' => 'ORD-0013']), coOrder()],
            'links' => ['next' => CO_API.'/orders?page=2'],
        ]),
    ]));

    $this->get('/orders')->assertInertia(fn (Assert $p) => $p
        ->component('orders/Index')
        ->where('orders.0.number', 'ORD-0013')
        ->where('orders.1.total', '39.80')
        ->where('next_page_url', '/orders?page=2')
        ->has('orders', 2));
});

it('forwards the page and has no next link on the last page', function () {
    Http::fake(coFakes([CO_API.'/orders*' => Http::response(['data' => [coOrder()], 'links' => ['next' => null]])]));

    $this->get('/orders?page=2')->assertInertia(fn (Assert $p) => $p->where('next_page_url', null));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/orders') && $r['page'] == 2);
});

it('shows an order with snapshot items and a cancel flag only when pending', function (string $status, bool $flag) {
    Http::fake(coFakes([CO_API.'/orders/12' => Http::response(coOrder(['status' => $status, 'payment_status' => $status === 'paid' ? 'paid' : 'unpaid', 'paid_at' => $status === 'paid' ? '2026-11-01T10:05:00Z' : null]))]));

    $this->get('/orders/12')->assertInertia(fn (Assert $p) => $p
        ->component('orders/Show')
        ->where('order.items.0.product_name', 'Lamp')
        ->where('order.subtotal', '39.80')
        ->where('order.status', $status)
        ->where('can_cancel', $flag)
        ->where('can_pay', $flag));
})->with([['pending', true], ['paid', false], ['cancelled', false], ['completed', false]]);

it('cancels a pending order', function () {
    Http::fake(coFakes([CO_API.'/orders/12/cancel' => Http::response(coOrder(['status' => 'cancelled']))]));

    $this->from('/orders/12')->post('/orders/12/cancel')->assertRedirect('/orders/12')
        ->assertSessionHas('success', 'Order cancelled.');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/orders/12/cancel') && $r->method() === 'POST');
});

it('shows the friendly error page on a 403 for another customers order', function () {
    Http::fake(coFakes([CO_API.'/orders/12' => Http::response(['message' => 'Forbidden'], 403)]));

    $this->get('/orders/12')->assertInertia(fn (Assert $p) => $p->component('Error')->where('status', 403));
});

it('shows the error page when cancelling a paid order (403)', function () {
    Http::fake(coFakes([CO_API.'/orders/12/cancel' => Http::response(['message' => 'Forbidden'], 403)]));

    $this->post('/orders/12/cancel')->assertInertia(fn (Assert $p) => $p->component('Error'));
});

it('keeps sellers off checkout and order routes', function () {
    app(DeviceSession::class)->store('tok', [
        'id' => 2, 'name' => 'Sam', 'email' => 's@example.com', 'phone' => null,
        'role' => 'seller', 'shop_name' => 'S', 'status' => 'active',
    ]);
    Http::fake(coFakes());

    $this->post('/checkout')->assertRedirect('/seller/products');
    $this->get('/orders')->assertRedirect('/seller/products');
    $this->post('/orders/12/pay')->assertRedirect('/seller/products');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/checkout') || str_contains($r->url(), '/pay'));
});
