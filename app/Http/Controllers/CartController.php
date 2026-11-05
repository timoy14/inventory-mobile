<?php

namespace App\Http\Controllers;

use App\Services\Api\ApiClient;
use App\Support\ApiData;
use App\Support\ProductPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function show(Request $request, ApiClient $api): Response
    {
        $cart = $api->cart();

        return Inertia::render('cart/Index', [
            'cart' => [
                'subtotal' => (string) ($cart['subtotal'] ?? '0.00'),
                'items' => collect(ApiData::array($cart['items'] ?? []))->map(fn (array $item) => [
                    'product_id' => $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (string) ($item['unit_price'] ?? '0.00'),
                    'line_total' => (string) ($item['line_total'] ?? '0.00'),
                    'product' => ProductPresenter::present($item['product'] ?? ['id' => $item['product_id']]),
                ])->all(),
            ],
            // Flashed by checkout (insufficient stock) so the lines can be marked.
            'unavailable_ids' => array_map('intval', (array) $request->session()->get('unavailable_ids', [])),
        ]);
    }

    public function store(Request $request, ApiClient $api): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $api->post('/cart/items', $data);

        return back()->with('success', 'Added to cart.');
    }

    public function update(Request $request, ApiClient $api, int $product): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $api->patch("/cart/items/{$product}", $data);

        return back();
    }

    public function destroy(ApiClient $api, int $product): RedirectResponse
    {
        $api->delete("/cart/items/{$product}");

        return back()->with('success', 'Removed from cart.');
    }
}
