<?php

namespace App\Http\Controllers;

use App\Services\Api\ApiClient;
use App\Support\ApiData;
use App\Support\OrderPresenter;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request, ApiClient $api): Response
    {
        $result = $api->get('/orders', array_filter(['page' => $request->integer('page') ?: null]));

        return Inertia::render('orders/Index', [
            'orders' => Inertia::merge(
                collect(ApiData::array($result['data'] ?? []))->map(OrderPresenter::present(...))->all()
            )->matchOn('id'),
            'next_page_url' => Pagination::nextUrl($result, '/orders'),
        ]);
    }

    public function show(ApiClient $api, int $order): Response
    {
        $data = OrderPresenter::present($api->get("/orders/{$order}"));
        $pending = $data['status'] === 'pending';

        return Inertia::render('orders/Show', [
            'order' => $data,
            'can_cancel' => $pending,
            'can_pay' => $pending,
        ]);
    }

    public function cancel(ApiClient $api, int $order): RedirectResponse
    {
        $api->post("/orders/{$order}/cancel");

        return back()->with('success', 'Order cancelled.');
    }
}
