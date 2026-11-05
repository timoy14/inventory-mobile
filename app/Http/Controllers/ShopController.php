<?php

namespace App\Http\Controllers;

use App\Services\Api\ApiClient;
use App\Services\Session\DeviceSession;
use App\Support\ApiData;
use App\Support\Pagination;
use App\Support\ProductPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function index(Request $request, ApiClient $api): Response
    {
        $search = trim((string) $request->query('search', ''));

        $filters = array_filter([
            'search' => $search !== '' ? $search : null,
            'category_id' => $request->integer('category_id') ?: null,
        ]);

        $result = $api->get('/products', array_filter($filters + ['page' => $request->integer('page') ?: null]));

        return Inertia::render('shop/Index', [
            // Load more appends rows to this list (partial reload of `products`).
            'products' => Inertia::merge(
                collect(ApiData::array($result['data'] ?? []))->map(ProductPresenter::present(...))->all()
            )->matchOn('id'),
            'next_page_url' => Pagination::nextUrl($result, '/shop', $filters),
            'categories' => fn () => $api->get('/categories')['data'] ?? [],
            'filters' => (object) $filters,
        ]);
    }

    public function show(int $id, ApiClient $api, DeviceSession $session): Response
    {
        return Inertia::render('shop/Show', [
            'product' => ProductPresenter::present($api->get("/products/{$id}")),
            'can_buy' => ($session->signedInUser()['role'] ?? null) === 'customer',
        ]);
    }
}
