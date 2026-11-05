<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Services\Session\DeviceSession;
use App\Support\Auth\SignOut;
use App\Support\PickedPhotos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const FR_API = 'http://10.0.2.2:8799/api/v1';

beforeEach(function () {
    config(['services.inventory_api.url' => FR_API]);
});

function frSignIn(string $role): void
{
    app(DeviceSession::class)->store('tok', [
        'id' => 1, 'name' => 'Ann', 'email' => 'ann@example.com', 'phone' => null,
        'role' => $role, 'shop_name' => null, 'status' => 'active',
    ]);
}

function frSettings(): array
{
    return [FR_API.'/settings/public*' => Http::response(['store_name' => 'S', 'currency_symbol' => '$', 'currency_code' => 'USD', 'test_mode_message' => 'T'])];
}

it('redirects back with an error toast when a mutation hits an unreachable API', function (string $uri, array $data) {
    frSignIn('customer');
    Http::fake(frSettings() + [FR_API.'/cart*' => Http::response(['items' => [], 'subtotal' => '0.00']), '*' => Http::failedConnection()]);

    $this->from('/shop')->post($uri, $data)
        ->assertRedirect('/shop')
        ->assertSessionHas('error', "Can't reach the store right now — try again");
})->with([
    'add to cart' => ['/cart/items', ['product_id' => 7, 'quantity' => 1]],
    'pay' => ['/orders/12/pay', []],
]);

it('redirects back with the toast on a 5xx for a mutation', function () {
    frSignIn('customer');
    Http::fake(frSettings() + [FR_API.'/orders/12/pay' => Http::response([], 503)]);

    $this->from('/orders/12')->post('/orders/12/pay')->assertRedirect('/orders/12')
        ->assertSessionHas('error');
});

it('still renders Offline for a GET when the API is unreachable', function () {
    frSignIn('customer');
    Http::fake(frSettings() + ['*' => Http::failedConnection()]);

    $this->get('/orders/12')->assertInertia(fn (Assert $p) => $p->component('Offline'));
});

it('makes no API calls for a picked_photos partial reload of the seller form', function (string $uri) {
    frSignIn('seller');
    Http::fake(['*' => Http::response([], 500)]);

    $version = app(Request::class);
    $this->get($uri, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'seller/products/Form',
        'X-Inertia-Partial-Data' => 'picked_photos',
    ])->assertOk()->assertJsonPath('props.picked_photos', []);

    Http::assertNothingSent();
})->with(['/seller/products/create', '/seller/products/7/edit']);

it('does not depend on the session to keep picked photos, and clears them on sign out', function () {
    $path = tempnam(sys_get_temp_dir(), 'pick').'.png';
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

    PickedPhotos::add([$path]);
    expect(session()->has('seller.picked_photos'))->toBeFalse()
        ->and(PickedPhotos::paths())->toBe([realpath($path)]);

    SignOut::run();

    expect(DB::table('picked_photos')->count())->toBe(0)->and(file_exists($path))->toBeFalse();
});

it('redirects the paid page to the order unless it is paid', function () {
    frSignIn('customer');
    Http::fake(frSettings() + [FR_API.'/orders/12' => Http::response(['id' => 12, 'number' => 'O', 'status' => 'pending', 'payment_status' => 'unpaid', 'subtotal' => '1.00', 'total' => '1.00', 'items' => []])]);

    $this->get('/orders/12/paid')->assertRedirect('/orders/12');
});

it('shows the unreachable-store error on the register page', function () {
    Http::fake(frSettings() + ['*' => Http::failedConnection()]);

    $this->from('/register')->post('/register', [
        'name' => 'A', 'email' => 'a@example.com', 'password' => 'secret123',
        'password_confirmation' => 'secret123', 'role' => 'customer',
    ])->assertRedirect('/register');

    $this->get('/register')->assertInertia(fn (Assert $p) => $p
        ->component('auth/Register')
        ->where('flash_error', "Can't reach the store right now — try again"));
});

it('purges picked photos when a stored session cannot be read', function () {
    $path = tempnam(sys_get_temp_dir(), 'pick').'.png';
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    PickedPhotos::add([$path]);

    $this->get('/shop')->assertRedirect('/login');

    expect(PickedPhotos::paths())->toBe([]);
});

it('accepts photos from a camera folder created after the first pick', function () {
    $home = storage_path('framework/testing/picks-'.uniqid()); // outside the always-allowed temp dir
    mkdir("{$home}/no_backup/Gallery", 0777, true);
    $previous = getenv('HOME');
    putenv("HOME={$home}");

    try {
        PickedPhotos::roots();

        mkdir("{$home}/no_backup/Camera", 0777, true);
        $photo = "{$home}/no_backup/Camera/shot.png";
        file_put_contents($photo, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        expect(PickedPhotos::confine($photo))->toBe(realpath($photo));
    } finally {
        putenv($previous === false ? 'HOME' : "HOME={$previous}");
        File::deleteDirectory($home);
    }
});
