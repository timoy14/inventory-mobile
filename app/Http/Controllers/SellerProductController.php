<?php

namespace App\Http\Controllers;

use App\Services\Api\ApiClient;
use App\Support\ApiData;
use App\Support\Pagination;
use App\Support\PickedPhotos;
use App\Support\SellerProductPresenter;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Native\Mobile\Facades\Camera;

class SellerProductController extends Controller
{
    private const MAX_IMAGES = 5;

    public function index(Request $request, ApiClient $api): Response
    {
        $result = $api->get('/seller/products', array_filter(['page' => $request->integer('page') ?: null]));

        return Inertia::render('seller/products/Index', [
            'products' => Inertia::merge(
                collect(ApiData::array($result['data'] ?? []))->map(SellerProductPresenter::present(...))->all()
            )->matchOn('id'),
            'next_page_url' => Pagination::nextUrl($result, '/seller/products'),
        ]);
    }

    public function create(ApiClient $api): Response
    {
        return $this->form($api, null);
    }

    public function edit(ApiClient $api, int $id): Response
    {
        return $this->form($api, fn () => SellerProductPresenter::present($api->get("/seller/products/{$id}")));
    }

    public function store(Request $request, ApiClient $api): RedirectResponse
    {
        [$fields, $files, $used] = $this->input($request);

        $files === []
            ? $api->post('/seller/products', array_filter($fields, fn ($v) => $v !== null && $v !== ''))
            : $api->multipart('/seller/products', $fields, ['images[]' => $files]);

        PickedPhotos::release($used);

        return redirect('/seller/products')->with('success', 'Product added.');
    }

    public function update(Request $request, ApiClient $api, int $id): RedirectResponse
    {
        [$fields, $files, $used] = $this->input($request);

        // PHP cannot parse multipart on PUT, so always POST + _method=PUT with every required field.
        $api->multipart("/seller/products/{$id}", [
            '_method' => 'PUT',
            ...$fields,
            'category_id' => $fields['category_id'] ?? '',
            'description' => $fields['description'] ?? '',
            'remove_images' => array_values((array) $request->input('remove_images', [])),
        ], ['images[]' => $files]);

        PickedPhotos::release($used);

        return redirect('/seller/products')->with('success', 'Product saved.');
    }

    public function destroy(ApiClient $api, int $id): RedirectResponse
    {
        $api->delete("/seller/products/{$id}");

        return redirect('/seller/products')->with('success', 'Product deleted.');
    }

    /** Opens the native gallery or camera; the result arrives later as an event the listener stores. */
    public function pick(Request $request): RedirectResponse
    {
        $source = $request->validate(['source' => ['required', 'in:gallery,camera']])['source'];

        $source === 'camera' ? Camera::getPhoto() : Camera::pickImages('images', true, self::MAX_IMAGES);

        return back();
    }

    public function discardPicked(Request $request): RedirectResponse
    {
        PickedPhotos::discard($request->validate(['index' => ['required', 'integer', 'min:0']])['index']);

        return back();
    }

    /** @param  (Closure(): array<string, mixed>)|null  $product  lazy, so partial reloads of picked_photos make no API calls */
    private function form(ApiClient $api, ?Closure $product): Response
    {
        return Inertia::render('seller/products/Form', [
            'product' => $product,
            'categories' => fn () => collect(ApiData::array($api->get('/categories')['data'] ?? []))
                ->map(fn (array $c) => ['id' => $c['id'], 'name' => $c['name']])->all(),
            'native' => function_exists('nativephp_call'),
            'picked_photos' => fn () => PickedPhotos::summary(),
        ]);
    }

    /**
     * Validates like the API does, then gathers uploads from the browser input and the native picker.
     *
     * @return array{0: array<string, mixed>, 1: list<UploadedFile|string>, 2: list<string>}
     */
    private function input(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'sku' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:2048'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['string'],
        ]);

        $uploads = array_values($request->file('images', []));
        $picked = PickedPhotos::paths();

        if ($unusable = array_values(array_filter($picked, fn (string $path) => ! PickedPhotos::usable($path)))) {
            PickedPhotos::release($unusable);

            throw ValidationException::withMessages(['images' => 'A picked photo was missing, too large (2 MB max) or not an image. Pick it again.']);
        }

        if (count($uploads) + count($picked) > self::MAX_IMAGES) {
            throw ValidationException::withMessages(['images' => 'A product can have at most '.self::MAX_IMAGES.' photos.']);
        }

        unset($data['images'], $data['remove_images']);

        return [$data, [...$uploads, ...$picked], $picked];
    }
}
