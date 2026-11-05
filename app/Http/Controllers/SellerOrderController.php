<?php

namespace App\Http\Controllers;

use App\Services\Api\ApiClient;
use App\Support\ApiData;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerOrderController extends Controller
{
    public function index(Request $request, ApiClient $api): Response
    {
        $result = $api->get('/seller/orders', array_filter(['page' => $request->integer('page') ?: null]));

        // Whitelisted: the buyer's name is all a seller sees, never contact details.
        $orders = collect(ApiData::array($result['data'] ?? []))->map(fn (array $order) => [
            'id' => (int) $order['id'],
            'number' => (string) ($order['number'] ?? ''),
            'status' => (string) ($order['status'] ?? ''),
            'created_at' => $order['created_at'] ?? null,
            'customer_name' => (string) ($order['customer']['name'] ?? ''),
            'seller_total' => (string) ($order['seller_total'] ?? '0.00'),
            'items' => collect(ApiData::array($order['items'] ?? []))->map(fn (array $item) => [
                'id' => $item['id'] ?? null,
                'product_name' => (string) ($item['product_name'] ?? ''),
                'unit_price' => (string) ($item['unit_price'] ?? '0.00'),
                'quantity' => (int) ($item['quantity'] ?? 0),
                'line_total' => (string) ($item['line_total'] ?? '0.00'),
            ])->all(),
        ])->all();

        return Inertia::render('seller/orders/Index', [
            'orders' => Inertia::merge($orders)->matchOn('id'),
            'next_page_url' => Pagination::nextUrl($result, '/seller/orders'),
        ]);
    }
}
