<?php

use App\Services\Session\DeviceSession;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const SHOP_API = 'http://10.0.2.2:8799/api/v1';

beforeEach(function () {
    config(['services.inventory_api.url' => SHOP_API]);
});

function shopSignIn(string $role = 'customer'): void
{
    app(DeviceSession::class)->store('tok', [
        'id' => 1, 'name' => 'Ann', 'email' => 'ann@example.com', 'phone' => null,
        'role' => $role, 'shop_name' => null, 'status' => 'active',
    ]);
}

function shopProduct(array $extra = []): array
{
    return array_merge([
        'id' => 7, 'name' => 'Lamp', 'slug' => 'lamp', 'description' => 'Bright', 'sku' => 'L-1',
        'price' => '19.90', 'stock' => 4, 'status' => 'active',
        'images' => [['path' => 'p/a.jpg', 'url' => 'http://127.0.0.1:8799/storage/p/a.jpg']],
        'category' => ['id' => 2, 'name' => 'Home'],
        'seller' => ['id' => 9, 'name' => 'Sam', 'shop_name' => 'Sam Shop'],
    ], $extra);
}

function shopCart(array $items = [], string $subtotal = '0.00'): array
{
    return ['items' => $items, 'subtotal' => $subtotal];
}

function shopFakes(array $extra = []): array
{
    return $extra + [
        SHOP_API.'/settings/public*' => Http::response(['store_name' => 'S', 'currency_symbol' => '$', 'currency_code' => 'USD', 'test_mode_message' => 'T']),
        SHOP_API.'/cart' => Http::response(shopCart()),
    ];
}

it('passes products and categories through with image urls fixed and search/category forwarded', function () {
    shopSignIn();
    Http::fake(shopFakes([
        SHOP_API.'/products*' => Http::response([
            'data' => [shopProduct()],
            'links' => ['next' => SHOP_API.'/products?page=2&search=lam&category_id=2'],
            'meta' => ['current_page' => 1],
        ]),
        SHOP_API.'/categories' => Http::response(['data' => [['id' => 2, 'name' => 'Home']]]),
    ]));

    $this->get('/shop?search=lam&category_id=2')->assertInertia(fn (Assert $p) => $p
        ->component('shop/Index')
        ->where('products.0.id', 7)
        ->where('products.0.images.0.url', 'http://10.0.2.2:8799/storage/p/a.jpg')
        ->where('categories', [['id' => 2, 'name' => 'Home']])
        ->where('filters', ['search' => 'lam', 'category_id' => 2])
        ->where('next_page_url', '/shop?page=2&search=lam&category_id=2')
    );

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/products')
        && $r['search'] === 'lam' && $r['category_id'] == 2);
});

it('marks the products prop as mergeable for load more', function () {
    shopSignIn();
    Http::fake(shopFakes([
        SHOP_API.'/products*' => Http::response(['data' => [shopProduct()], 'links' => ['next' => null], 'meta' => []]),
        SHOP_API.'/categories' => Http::response(['data' => []]),
    ]));

    $response = $this->get('/shop')->assertOk();
    expect($response->viewData('page')['mergeProps'])->toContain('products');
});

it('forwards the page to the API on load more', function () {
    shopSignIn();
    Http::fake(shopFakes([
        SHOP_API.'/products*' => Http::response(['data' => [], 'links' => ['next' => null], 'meta' => []]),
        SHOP_API.'/categories' => Http::response(['data' => []]),
    ]));

    $this->get('/shop?page=3');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/products') && $r['page'] == 3);
});

it('shows a product with can_buy true for a customer', function () {
    shopSignIn();
    Http::fake(shopFakes([SHOP_API.'/products/7' => Http::response(shopProduct())]));

    $this->get('/shop/7')->assertInertia(fn (Assert $p) => $p
        ->component('shop/Show')
        ->where('can_buy', true)
        ->where('product.seller_name', 'Sam Shop')
        ->where('product.images.0.url', 'http://10.0.2.2:8799/storage/p/a.jpg'));
});

it('gives a seller can_buy false on the product page', function () {
    shopSignIn('seller');
    Http::fake(shopFakes([SHOP_API.'/products/7' => Http::response(shopProduct())]));

    $this->get('/shop/7')->assertInertia(fn (Assert $p) => $p->where('can_buy', false));
});

it('renders the error page when the product is not found', function () {
    shopSignIn();
    Http::fake(shopFakes([SHOP_API.'/products/99' => Http::response(['message' => 'Not found'], 404)]));

    $this->get('/shop/99')->assertInertia(fn (Assert $p) => $p->component('Error'));
});

it('lists the cart with fixed image urls and flashed unavailable ids', function () {
    shopSignIn();
    Http::fake(shopFakes([SHOP_API.'/cart' => Http::response(shopCart([
        ['product_id' => 7, 'quantity' => 2, 'unit_price' => '19.90', 'line_total' => '39.80', 'product' => shopProduct()],
    ], '39.80'))]));

    $this->withSession(['unavailable_ids' => [7]])->get('/cart')->assertInertia(fn (Assert $p) => $p
        ->component('cart/Index')
        ->where('cart.subtotal', '39.80')
        ->where('cart.items.0.product.images.0.url', 'http://10.0.2.2:8799/storage/p/a.jpg')
        ->where('unavailable_ids', [7]));
});

it('defaults unavailable ids to empty', function () {
    shopSignIn();
    Http::fake(shopFakes());

    $this->get('/cart')->assertInertia(fn (Assert $p) => $p->where('unavailable_ids', []));
});

it('adds to the cart and flashes success', function () {
    shopSignIn();
    Http::fake(shopFakes([SHOP_API.'/cart/items' => Http::response(shopCart())]));

    $this->from('/shop/7')->post('/cart/items', ['product_id' => 7, 'quantity' => 2])
        ->assertRedirect('/shop/7')->assertSessionHas('success');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/cart/items')
        && $r['product_id'] == 7 && $r['quantity'] == 2);
});

it('flashes the API message when over stock', function () {
    shopSignIn();
    Http::fake(shopFakes([SHOP_API.'/cart/items' => Http::response(['message' => 'Only 1 left.', 'error' => 'insufficient_stock', 'product_ids' => [7]], 422)]));

    $this->from('/shop/7')->post('/cart/items', ['product_id' => 7, 'quantity' => 5])
        ->assertRedirect('/shop/7')->assertSessionHas('error', 'Only 1 left.');
});

it('rejects a quantity of 0 locally without calling the API', function () {
    shopSignIn();
    Http::fake(shopFakes());

    $this->from('/shop/7')->post('/cart/items', ['product_id' => 7, 'quantity' => 0])
        ->assertSessionHasErrors('quantity');
    $this->from('/cart')->patch('/cart/items/7', ['quantity' => 0])
        ->assertSessionHasErrors('quantity');

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/cart/items'));
});

it('requires product_id as an integer', function () {
    shopSignIn();
    Http::fake(shopFakes());

    $this->post('/cart/items', ['product_id' => 'x', 'quantity' => 1])->assertSessionHasErrors('product_id');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/cart/items'));
});

it('updates a line quantity', function () {
    shopSignIn();
    Http::fake(shopFakes([SHOP_API.'/cart/items/7' => Http::response(shopCart())]));

    $this->from('/cart')->patch('/cart/items/7', ['quantity' => 3])->assertRedirect('/cart');

    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/cart/items/7') && $r['quantity'] == 3);
});

it('removes a line', function () {
    shopSignIn();
    Http::fake(shopFakes([SHOP_API.'/cart/items/7' => Http::response(shopCart())]));

    $this->from('/cart')->delete('/cart/items/7')->assertRedirect('/cart')->assertSessionHas('success');

    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/cart/items/7'));
});

it('keeps sellers away from the cart routes', function () {
    shopSignIn('seller');
    Http::fake(shopFakes());

    $this->post('/cart/items', ['product_id' => 7, 'quantity' => 1])->assertRedirect('/seller/products');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/cart/items'));
});

it('fetches the cart once for the cart page', function () {
    shopSignIn();
    Http::fake(shopFakes());

    $this->get('/cart')->assertOk();

    expect(collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/cart'))->count())->toBe(1);
});
