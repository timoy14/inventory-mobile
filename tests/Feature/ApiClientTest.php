<?php

use App\Services\Api\ApiClient;
use App\Services\Api\Exceptions\ApiDomainError;
use App\Services\Api\Exceptions\ApiForbidden;
use App\Services\Api\Exceptions\ApiNotFound;
use App\Services\Api\Exceptions\ApiSuspended;
use App\Services\Api\Exceptions\ApiUnauthorized;
use App\Services\Api\Exceptions\ApiUnavailable;
use App\Services\Api\Exceptions\ApiValidation;
use App\Support\ImageUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

const API = 'http://127.0.0.1:8799/api/v1';

beforeEach(function () {
    config(['services.inventory_api.url' => API.'/']);
});

function client(): ApiClient
{
    return app(ApiClient::class);
}

it('sends Accept and bearer headers and decodes json', function () {
    Http::fake([API.'/products*' => Http::response(['data' => [1]])]);

    $res = (new ApiClient(fn () => 'abc'))->get('/products', ['page' => 2]);

    expect($res)->toBe(['data' => [1]]);
    Http::assertSent(fn (Request $r) => $r->url() === API.'/products?page=2'
        && $r->hasHeader('Accept', 'application/json')
        && $r->hasHeader('Authorization', 'Bearer abc'));
});

it('omits Authorization without a token', function () {
    Http::fake([API.'/*' => Http::response([])]);

    client()->get('/products');

    Http::assertSent(fn (Request $r) => ! $r->hasHeader('Authorization'));
});

it('uses the token resolver lazily', function () {
    Http::fake([API.'/*' => Http::response([])]);

    (new ApiClient(fn () => 'resolved'))->get('/me');

    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer resolved'));
});

it('returns an empty array for 204', function () {
    Http::fake([API.'/*' => Http::response(null, 204)]);

    expect(client()->delete('/seller/products/1'))->toBe([]);
});

it('sends post and patch bodies as json', function () {
    Http::fake([API.'/*' => Http::response(['ok' => true])]);

    client()->post('/cart/items', ['product_id' => 1, 'quantity' => 2]);
    client()->patch('/cart/items/1', ['quantity' => 3]);

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['quantity'] === 2);
    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r['quantity'] === 3);
});

it('maps statuses to exceptions', function (int $status, array $body, string $class) {
    Http::fake([API.'/*' => Http::response($body, $status)]);

    try {
        client()->get('/x');
        $this->fail('no exception');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf($class);
        expect($e->status)->toBe($status);
        expect($e->body)->toBe($body);
    }
})->with([
    '401' => [401, ['message' => 'Unauthenticated.'], ApiUnauthorized::class],
    'suspended' => [403, ['message' => 'x', 'error' => 'account_suspended'], ApiSuspended::class],
    'forbidden' => [403, ['message' => 'x'], ApiForbidden::class],
    '404' => [404, ['message' => 'x'], ApiNotFound::class],
    'validation' => [422, ['message' => 'x', 'errors' => ['email' => ['Bad']]], ApiValidation::class],
    'domain' => [422, ['message' => 'Nope', 'error' => 'cart_empty'], ApiDomainError::class],
    '429' => [429, [], ApiUnavailable::class],
    '500' => [500, [], ApiUnavailable::class],
    '503' => [503, [], ApiUnavailable::class],
]);

it('exposes validation and domain error details', function () {
    Http::fake([
        API.'/v' => Http::response(['message' => 'm', 'errors' => ['email' => ['Bad']]], 422),
        API.'/d' => Http::response(['message' => 'Out of stock', 'error' => 'insufficient_stock', 'product_ids' => [3]], 422),
    ]);

    try {
        client()->get('/v');
    } catch (ApiValidation $e) {
        expect($e->errors())->toBe(['email' => ['Bad']]);
    }
    try {
        client()->get('/d');
    } catch (ApiDomainError $e) {
        expect($e->code())->toBe('insufficient_stock')
            ->and($e->getMessage())->toBe('Out of stock')
            ->and($e->context())->toBe(['product_ids' => [3]]);
    }
});

it('maps connection failures to ApiUnavailable', function () {
    Http::fake(fn () => throw new ConnectionException('refused'));

    client()->get('/x');
})->throws(ApiUnavailable::class);

it('sends multipart with repeated array keys and files', function () {
    Http::fake([API.'/*' => Http::response(['id' => 1], 201)]);
    $path = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($path, 'data');

    client()->multipart('/seller/products', [
        'name' => 'Cup',
        'remove_images' => ['a.jpg', 'b.jpg'],
    ], [
        'images[]' => [UploadedFile::fake()->image('one.jpg'), $path],
    ]);

    Http::assertSent(function (Request $r) {
        $names = collect($r->data())->pluck('name')->all();

        return $r->isMultipart()
            && array_count_values($names)['remove_images[]'] === 2
            && array_count_values($names)['images[]'] === 2
            && in_array('name', $names, true);
    });
    unlink($path);
});

it('fetches public settings without a token, memoized', function () {
    Http::fake([API.'/settings/public' => Http::response(['store_name' => 'Shop', 'currency_code' => 'EUR', 'currency_symbol' => 'E', 'test_mode_message' => 'T'])]);
    $c = new ApiClient(fn () => 'abc');

    expect($c->publicSettings()['store_name'])->toBe('Shop');
    $c->publicSettings();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => ! $r->hasHeader('Authorization'));
});

it('falls back to default public settings when unavailable', function () {
    Http::fake(fn () => throw new ConnectionException('refused'));

    expect(client()->publicSettings())->toBe([
        'store_name' => 'Inventory',
        'currency_symbol' => '$',
        'currency_code' => 'USD',
        'test_mode_message' => 'This is a testing site only. No real payment will be taken.',
    ]);
});

it('rewrites loopback image hosts to the API host', function () {
    config(['services.inventory_api.url' => 'http://10.0.2.2:8799/api/v1']);

    expect(ImageUrl::fix('http://127.0.0.1:8000/storage/p/a.jpg'))->toBe('http://10.0.2.2:8799/storage/p/a.jpg')
        ->and(ImageUrl::fix('http://localhost/storage/a.jpg?x=1'))->toBe('http://10.0.2.2:8799/storage/a.jpg?x=1')
        ->and(ImageUrl::fix('https://cdn.example.com/a.jpg'))->toBe('https://cdn.example.com/a.jpg');
});

it('leaves image urls alone when the API host is loopback', function () {
    expect(ImageUrl::fix('http://127.0.0.1:8000/storage/a.jpg'))->toBe('http://127.0.0.1:8000/storage/a.jpg');
});

function throwing(string $uri, Throwable $e): void
{
    Route::middleware('web')->get($uri, fn () => throw $e);
}

it('renders Offline for ApiUnavailable', function () {
    throwing('/t-unavail', new ApiUnavailable(503, []));

    $this->get('/t-unavail')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Offline')->where('retry_url', url('/t-unavail')));
});

it('renders Error with 404 for ApiNotFound', function () {
    throwing('/t-404', new ApiNotFound(404, []));

    $this->get('/t-404')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Error')->has('message'));
});

it('redirects to login on unauthorized', function () {
    throwing('/t-401', new ApiUnauthorized(401, []));

    $this->get('/t-401')->assertRedirect('/login');
});

it('redirects to login with flash on suspended', function () {
    throwing('/t-sus', new ApiSuspended(403, ['error' => 'account_suspended']));

    $this->get('/t-sus')->assertRedirect('/login')->assertSessionHas('error', 'Your account has been suspended');
});

it('turns ApiValidation into form errors', function () {
    throwing('/t-422', new ApiValidation(422, ['errors' => ['email' => ['Bad']]]));

    $this->from('/form')->get('/t-422')->assertRedirect('/form')->assertSessionHasErrors(['email' => 'Bad']);
});

it('turns ApiDomainError into a back redirect with flash', function () {
    throwing('/t-dom', new ApiDomainError(422, ['message' => 'Already paid', 'error' => 'order_not_payable']));

    $this->from('/orders/1')->get('/t-dom')->assertRedirect('/orders/1')->assertSessionHas('error', 'Already paid');
});

it('does not cache the fallback public settings', function () {
    Http::fake(fn () => throw new ConnectionException('refused'));
    client()->publicSettings();

    expect(Cache::has('api.public_settings'))->toBeFalse();
});

it('caches successful public settings across clients', function () {
    Http::fake([API.'/settings/public' => Http::response(['store_name' => 'Shop'])]);

    (new ApiClient)->publicSettings();
    expect((new ApiClient)->publicSettings()['store_name'])->toBe('Shop');

    Http::assertSentCount(1);
});
