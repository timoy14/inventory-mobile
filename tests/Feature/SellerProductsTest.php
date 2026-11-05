<?php

use App\Services\Session\DeviceSession;
use App\Support\PickedPhotos;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Facades\Camera;

const SELLER_API = 'http://10.0.2.2:8799/api/v1';

beforeEach(function () {
    config(['services.inventory_api.url' => SELLER_API]);
});

function sellerSignIn(string $role = 'seller'): void
{
    app(DeviceSession::class)->store('tok', [
        'id' => 1, 'name' => 'Sam', 'email' => 'sam@example.com', 'phone' => null,
        'role' => $role, 'shop_name' => 'Sam Shop', 'status' => 'active',
    ]);
}

function sellerProduct(array $extra = []): array
{
    return array_merge([
        'id' => 7, 'name' => 'Lamp', 'slug' => 'lamp', 'description' => 'Bright', 'sku' => 'L-1',
        'price' => '19.90', 'stock' => 4, 'status' => 'active',
        'images' => [['path' => 'products/a.jpg', 'url' => 'http://127.0.0.1:8799/storage/products/a.jpg']],
        'category' => ['id' => 2, 'name' => 'Home'],
        'seller' => ['id' => 1, 'name' => 'Sam', 'shop_name' => 'Sam Shop'],
    ], $extra);
}

function sellerFakes(array $extra = []): array
{
    return $extra + [
        SELLER_API.'/settings/public*' => Http::response(['store_name' => 'S', 'currency_symbol' => '$', 'currency_code' => 'USD', 'test_mode_message' => 'T']),
        SELLER_API.'/categories' => Http::response(['data' => [['id' => 2, 'name' => 'Home', 'slug' => 'home']]]),
    ];
}

function validProductInput(array $extra = []): array
{
    return array_merge([
        'name' => 'Lamp', 'description' => 'Bright', 'category_id' => 2, 'sku' => 'L-1',
        'price' => '19.90', 'stock' => 4, 'status' => 'active',
    ], $extra);
}

function imageFile(string $name = 'a.jpg', int $kb = 10): UploadedFile
{
    return UploadedFile::fake()->image($name, 20, 20)->size($kb);
}

/** A real image on disk standing in for a native-picked file. */
function pickedFile(): string
{
    $path = tempnam(sys_get_temp_dir(), 'pick').'.png';
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

    return $path;
}

it('lists own products with fixed image urls, sku, status and a next link', function () {
    sellerSignIn();
    Http::fake(sellerFakes([
        SELLER_API.'/seller/products*' => Http::response([
            'data' => [sellerProduct()],
            'links' => ['next' => SELLER_API.'/seller/products?page=2'],
        ]),
    ]));

    $this->get('/seller/products?page=1')->assertInertia(fn (Assert $p) => $p
        ->component('seller/products/Index')
        ->where('products.0.sku', 'L-1')
        ->where('products.0.status', 'active')
        ->where('products.0.images.0.url', 'http://10.0.2.2:8799/storage/products/a.jpg')
        ->where('next_page_url', '/seller/products?page=2')
        ->has('products', 1)
    );
});

it('renders the create form with categories and no product', function () {
    sellerSignIn();
    Http::fake(sellerFakes());

    $this->get('/seller/products/create')->assertInertia(fn (Assert $p) => $p
        ->component('seller/products/Form')
        ->where('product', null)
        ->where('categories', [['id' => 2, 'name' => 'Home']])
        ->where('picked_photos', [])
    );
});

it('renders the edit form with image paths and fixed urls', function () {
    sellerSignIn();
    Http::fake(sellerFakes([SELLER_API.'/seller/products/7' => Http::response(sellerProduct())]));

    $this->get('/seller/products/7/edit')->assertInertia(fn (Assert $p) => $p
        ->component('seller/products/Form')
        ->where('product.id', 7)
        ->where('product.category_id', 2)
        ->where('product.images', [['path' => 'products/a.jpg', 'url' => 'http://10.0.2.2:8799/storage/products/a.jpg']])
    );
});

it('creates with multipart when there are images', function () {
    sellerSignIn();
    Http::fake(sellerFakes([SELLER_API.'/seller/products' => Http::response(sellerProduct(), 201)]));

    $this->post('/seller/products', validProductInput(['images' => [imageFile('a.jpg'), imageFile('b.jpg')]]))
        ->assertRedirect('/seller/products')->assertSessionHas('success');

    Http::assertSent(function (Request $r) {
        if ($r->url() !== SELLER_API.'/seller/products' || ! $r->isMultipart()) {
            return false;
        }
        $names = collect($r->data())->pluck('name');

        return $names->filter(fn ($n) => $n === 'images[]')->count() === 2
            && collect($r->data())->firstWhere('name', 'sku')['contents'] === 'L-1';
    });
});

it('creates with plain json when there are no images', function () {
    sellerSignIn();
    Http::fake(sellerFakes([SELLER_API.'/seller/products' => Http::response(sellerProduct(), 201)]));

    $this->post('/seller/products', validProductInput(['description' => '', 'category_id' => '']))
        ->assertRedirect('/seller/products');

    Http::assertSent(fn (Request $r) => $r->url() === SELLER_API.'/seller/products'
        && ! $r->isMultipart()
        && array_map('strval', $r->data()) === ['name' => 'Lamp', 'sku' => 'L-1', 'price' => '19.90', 'stock' => '4', 'status' => 'active']);
});

it('updates with POST and _method=PUT, all required fields and remove_images paths', function () {
    sellerSignIn();
    Http::fake(sellerFakes([SELLER_API.'/seller/products/7' => Http::response(sellerProduct())]));

    $this->put('/seller/products/7', validProductInput([
        'remove_images' => ['products/a.jpg', 'products/b.jpg'],
        'images' => [imageFile()],
    ]))->assertRedirect('/seller/products')->assertSessionHas('success');

    Http::assertSent(function (Request $r) {
        if ($r->method() !== 'POST' || $r->url() !== SELLER_API.'/seller/products/7' || ! $r->isMultipart()) {
            return false;
        }
        $parts = collect($r->data());
        $value = fn (string $n) => $parts->firstWhere('name', $n)['contents'] ?? null;

        return $value('_method') === 'PUT'
            && $value('name') === 'Lamp' && $value('sku') === 'L-1' && $value('price') === '19.90'
            && $value('stock') === '4' && $value('status') === 'active'
            && $parts->where('name', 'remove_images[]')->pluck('contents')->all() === ['products/a.jpg', 'products/b.jpg']
            && $parts->where('name', 'images[]')->count() === 1;
    });
});

it('updates without new files still as multipart with _method=PUT and every required field', function () {
    sellerSignIn();
    Http::fake(sellerFakes([SELLER_API.'/seller/products/7' => Http::response(sellerProduct())]));

    $this->put('/seller/products/7', validProductInput())->assertRedirect('/seller/products');

    Http::assertSent(function (Request $r) {
        $parts = collect($r->data());

        return $r->method() === 'POST' && $r->isMultipart()
            && $parts->firstWhere('name', '_method')['contents'] === 'PUT'
            && collect(['name', 'sku', 'price', 'stock', 'status'])->every(fn ($n) => $parts->contains('name', $n))
            && ! $parts->contains('name', 'images[]');
    });
});

it('maps api validation errors onto form fields', function () {
    sellerSignIn();
    Http::fake(sellerFakes([SELLER_API.'/seller/products' => Http::response([
        'message' => 'x',
        'errors' => ['sku' => ['The sku has already been taken.'], 'images.0' => ['Too big.']],
    ], 422)]));

    $this->from('/seller/products/create')->post('/seller/products', validProductInput())
        ->assertRedirect('/seller/products/create')
        ->assertSessionHasErrors(['sku' => 'The sku has already been taken.', 'images.0' => 'Too big.']);
});

it('validates locally with no api call', function (array $bad, string $field) {
    sellerSignIn();
    Http::fake(sellerFakes());

    $this->post('/seller/products', validProductInput($bad))->assertSessionHasErrors($field);

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/seller/products'));
})->with([
    'name required' => [['name' => ''], 'name'],
    'sku required' => [['sku' => ''], 'sku'],
    'price numeric' => [['price' => 'abc'], 'price'],
    'stock integer' => [['stock' => '1.5'], 'stock'],
    'status enum' => [['status' => 'live'], 'status'],
    'non-image upload' => [['images' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]], 'images.0'],
    'image over 2 MB' => [['images' => [UploadedFile::fake()->image('a.jpg')->size(3000)]], 'images.0'],
    'remove_images strings' => [['remove_images' => [['x']]], 'remove_images.0'],
]);

it('rejects more than 5 new images locally with no api call', function () {
    sellerSignIn();
    Http::fake(sellerFakes());

    $this->post('/seller/products', validProductInput(['images' => array_map(fn ($i) => imageFile("$i.jpg"), range(1, 6))]))
        ->assertSessionHasErrors('images');

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/seller/products'));
});

it('deletes then redirects to the list with a flash', function () {
    sellerSignIn();
    Http::fake(sellerFakes([SELLER_API.'/seller/products/7' => Http::response(null, 204)]));

    $this->delete('/seller/products/7')->assertRedirect('/seller/products')->assertSessionHas('success');

    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === SELLER_API.'/seller/products/7');
});

it('redirects a customer away from every seller route', function (string $method, string $uri) {
    sellerSignIn('customer');
    Http::fake(sellerFakes());

    $this->{$method}($uri)->assertRedirect('/shop');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/seller'));
})->with([
    ['get', '/seller/products'],
    ['get', '/seller/products/create'],
    ['post', '/seller/products'],
    ['get', '/seller/products/7/edit'],
    ['put', '/seller/products/7'],
    ['delete', '/seller/products/7'],
    ['get', '/seller/orders'],
    ['post', '/seller/photos/pick'],
]);

it('404s on a non-numeric product id', function (string $method, string $uri) {
    sellerSignIn();
    Http::fake(sellerFakes());

    $this->{$method}($uri)->assertNotFound();
})->with([['get', '/seller/products/abc/edit'], ['put', '/seller/products/abc'], ['delete', '/seller/products/abc']]);

it('turns an api 403 on someone elses product into the error page', function () {
    sellerSignIn();
    Http::fake(sellerFakes([SELLER_API.'/seller/products/9' => Http::response(['message' => 'No'], 403)]));

    $this->get('/seller/products/9/edit')->assertInertia(fn (Assert $p) => $p->component('Error'));
});

it('asks the native gallery for up to 5 images or the camera for one photo', function () {
    sellerSignIn();
    Camera::shouldReceive('pickImages')->once()->with('images', true, 5);
    $this->post('/seller/photos/pick', ['source' => 'gallery'])->assertRedirect();

    Camera::shouldReceive('getPhoto')->once();
    $this->post('/seller/photos/pick', ['source' => 'camera'])->assertRedirect();

    $this->post('/seller/photos/pick', ['source' => 'nope'])->assertSessionHasErrors('source');
});

it('stores picked paths from native events in the database, independent of any session', function () {
    sellerSignIn();

    [$a, $b, $c] = [pickedFile(), pickedFile(), pickedFile()];
    event(new MediaSelected(true, [['path' => $a], ['path' => $b]], 2));
    event(new PhotoTaken($c));
    event(new MediaSelected(false, [], 0, null, true));

    expect(PickedPhotos::paths())->toBe([realpath($a), realpath($b), realpath($c)]);
    PickedPhotos::release([$a, $b, $c]);
});

it('sends picked photos as files, then unlinks them and clears the session', function () {
    sellerSignIn();
    Http::fake(sellerFakes([SELLER_API.'/seller/products/7' => Http::response(sellerProduct())]));
    $path = pickedFile();
    PickedPhotos::add([$path]);

    $this->put('/seller/products/7', validProductInput())->assertRedirect('/seller/products');

    Http::assertSent(fn (Request $r) => collect($r->data())->where('name', 'images[]')->count() === 1);
    expect(file_exists($path))->toBeFalse()->and(PickedPhotos::paths())->toBe([]);
});

it('counts picked photos toward the 5 photo limit and rejects unusable ones without calling the api', function () {
    sellerSignIn();
    Http::fake(sellerFakes());
    $paths = array_map(fn () => pickedFile(), range(1, 5));
    PickedPhotos::add($paths);

    $this->post('/seller/products', validProductInput(['images' => [imageFile()]]))->assertSessionHasErrors('images');
    PickedPhotos::release($paths);

    $notImage = tempnam(sys_get_temp_dir(), 'pick');
    file_put_contents($notImage, 'not an image');
    PickedPhotos::add([$notImage]);
    $this->post('/seller/products', validProductInput())->assertSessionHasErrors('images');
    expect(PickedPhotos::paths())->toBe([]);

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/seller/products'));
});

it('exposes pending photos by name only and can discard one', function () {
    sellerSignIn();
    Http::fake(sellerFakes());
    $path = pickedFile();
    PickedPhotos::add([$path]);

    $this->get('/seller/products/create')->assertInertia(fn (Assert $p) => $p
        ->where('picked_photos', [['name' => basename($path), 'usable' => true]]));

    $this->post('/seller/photos/discard', ['index' => 0])->assertRedirect();
    expect(file_exists($path))->toBeFalse()->and(PickedPhotos::paths())->toBe([]);
});

it('shows seller orders with seller_total and customer name, never contact details', function () {
    sellerSignIn();
    Http::fake(sellerFakes([SELLER_API.'/seller/orders*' => Http::response([
        'data' => [[
            'id' => 3, 'number' => 'ORD-3', 'status' => 'paid', 'created_at' => '2026-11-01T10:00:00Z',
            'customer' => ['name' => 'Cy', 'email' => 'cy@example.com', 'phone' => '555'],
            'email' => 'leak@example.com', 'phone' => '999',
            'items' => [['id' => 1, 'product_id' => 7, 'product_name' => 'Lamp', 'unit_price' => '19.90', 'quantity' => 2, 'line_total' => '39.80']],
            'seller_total' => '39.80',
        ]],
        'links' => ['next' => SELLER_API.'/seller/orders?page=2'],
    ])]));

    $this->get('/seller/orders')->assertInertia(fn (Assert $p) => $p
        ->component('seller/orders/Index')
        ->where('orders.0.number', 'ORD-3')
        ->where('orders.0.seller_total', '39.80')
        ->where('orders.0.customer_name', 'Cy')
        ->where('orders.0.items.0.line_total', '39.80')
        ->where('next_page_url', '/seller/orders?page=2')
        ->missing('orders.0.email')->missing('orders.0.phone')->missing('orders.0.customer')
    );

    $json = json_encode($this->get('/seller/orders')->original->getData());
    expect($json)->not->toContain('cy@example.com')->not->toContain('leak@example.com')->not->toContain('555');
});

it('ignores picked paths outside the allowed roots and never uploads or unlinks them', function () {
    sellerSignIn();
    Http::fake(sellerFakes([SELLER_API.'/seller/products' => Http::response(sellerProduct(), 201)]));
    $inside = base_path('picked-outside-test.png');
    copy(pickedFile(), $inside);

    try {
        // add() drops them; a forged session entry is filtered on read too.
        PickedPhotos::add(['/etc/hosts', $inside, '/etc/../etc/hosts']);
        DB::table('picked_photos')->insert([['path' => '/etc/hosts'], ['path' => $inside]]);
        expect(PickedPhotos::paths())->toBe([]);

        $this->post('/seller/products', validProductInput())->assertRedirect('/seller/products');
        $this->post('/seller/photos/discard', ['index' => 0])->assertRedirect();
        PickedPhotos::release([$inside, '/etc/hosts']);

        Http::assertSent(fn (Request $r) => $r->url() === SELLER_API.'/seller/products' && ! $r->isMultipart());
        expect(file_exists($inside))->toBeTrue();
    } finally {
        @unlink($inside);
    }
});

it('does not follow a symlink in the temp dir out to a file outside the roots', function () {
    $link = sys_get_temp_dir().'/pick-link-'.uniqid().'.png';
    symlink('/etc/hosts', $link);

    try {
        PickedPhotos::add([$link]);
        expect(PickedPhotos::paths())->toBe([]);
    } finally {
        @unlink($link);
    }
});

it('accepts a path inside an allowed root and stores its real path', function () {
    $path = pickedFile();
    PickedPhotos::add([$path]);

    expect(PickedPhotos::paths())->toBe([realpath($path)]);
    PickedPhotos::release([$path]);
});

it('publishes the full nativephp config with the app id and camera permissions', function () {
    expect(config('nativephp.app_id'))->toBe('com.inventory.marketplace')
        ->and(config('nativephp.version'))->not->toBeNull()
        ->and(config('nativephp.start_url'))->toBe('/')
        ->and(config('nativephp.permissions'))->toHaveKeys(['NSCameraUsageDescription', 'NSPhotoLibraryUsageDescription']);
});
