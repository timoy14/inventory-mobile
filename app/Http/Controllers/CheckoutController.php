<?php

namespace App\Http\Controllers;

use App\Services\Api\ApiClient;
use App\Services\Api\Exceptions\ApiDomainError;
use App\Support\OrderPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function store(Request $request, ApiClient $api): RedirectResponse
    {
        try {
            $result = $api->post('/checkout');
        } catch (ApiDomainError $e) {
            if ($e->code() !== 'insufficient_stock') {
                throw $e;
            }

            return redirect('/cart')
                ->with('unavailable_ids', array_map('intval', (array) ($e->context()['product_ids'] ?? [])))
                ->with('error', $e->getMessage());
        }

        $id = (int) $result['order']['id'];
        if (! empty($result['test_mode_message'])) {
            $request->session()->put("test_mode_message.{$id}", (string) $result['test_mode_message']);
        }

        return redirect("/checkout/{$id}");
    }

    public function show(Request $request, ApiClient $api, int $order): Response|RedirectResponse
    {
        $data = $api->get("/orders/{$order}");

        // Only a pending order can be paid; anything else belongs on the order page.
        if (($data['status'] ?? null) !== 'pending') {
            return redirect("/orders/{$order}");
        }

        return Inertia::render('checkout/Show', [
            'order' => OrderPresenter::present($data),
            'test_mode_message' => $this->message($request, $order, $api),
        ]);
    }

    public function pay(Request $request, ApiClient $api, int $order): RedirectResponse
    {
        // A second concurrent submit must not reach the API; send it to the order page.
        $lock = Cache::lock("pay:{$order}", 10);
        if (! $lock->get()) {
            return redirect("/orders/{$order}")->with('error', 'Payment is already being processed.');
        }

        try {
            $result = $api->post("/orders/{$order}/pay");
        } catch (ApiDomainError $e) {
            if ($e->code() !== 'order_not_payable') {
                throw $e;
            }

            return redirect("/orders/{$order}")->with('error', $e->getMessage());
        } finally {
            $lock->release();
        }

        $request->session()->put("paid.{$order}", [
            'reference' => $result['payment']['reference'] ?? null,
            'message' => $result['test_mode_message'] ?? null,
        ]);

        return redirect("/orders/{$order}/paid");
    }

    public function paid(Request $request, ApiClient $api, int $order): Response|RedirectResponse
    {
        // Gone after a refresh; the page then renders without the reference.
        $stash = $request->session()->pull("paid.{$order}", []);
        $data = $api->get("/orders/{$order}");

        if (($data['payment_status'] ?? null) !== 'paid') {
            return redirect("/orders/{$order}");
        }

        return Inertia::render('checkout/Paid', [
            'order' => OrderPresenter::present($data),
            'reference' => $stash['reference'] ?? null,
            'test_mode_message' => $stash['message'] ?? $this->message($request, $order, $api),
        ]);
    }

    private function message(Request $request, int $order, ApiClient $api): string
    {
        return (string) ($request->session()->get("test_mode_message.{$order}")
            ?: $api->publicSettings()['test_mode_message']);
    }
}
