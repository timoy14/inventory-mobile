<?php

namespace App\Support;

/** Shapes an API order for the pages. Money stays as the API's strings; nothing is computed. */
class OrderPresenter
{
    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    public static function present(array $order): array
    {
        return [
            'id' => (int) $order['id'],
            'number' => (string) ($order['number'] ?? ''),
            'status' => (string) ($order['status'] ?? ''),
            'payment_status' => (string) ($order['payment_status'] ?? ''),
            'subtotal' => (string) ($order['subtotal'] ?? '0.00'),
            'total' => (string) ($order['total'] ?? '0.00'),
            'paid_at' => $order['paid_at'] ?? null,
            'created_at' => $order['created_at'] ?? null,
            'items' => collect(ApiData::array($order['items'] ?? []))->map(fn (array $item) => [
                'product_id' => $item['product_id'] ?? null,
                'product_name' => (string) ($item['product_name'] ?? ''),
                'unit_price' => (string) ($item['unit_price'] ?? '0.00'),
                'quantity' => (int) ($item['quantity'] ?? 0),
                'line_total' => (string) ($item['line_total'] ?? '0.00'),
            ])->all(),
        ];
    }
}
