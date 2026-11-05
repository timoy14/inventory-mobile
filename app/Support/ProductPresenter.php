<?php

namespace App\Support;

/** Maps an API product to page props; the one place image URLs are fixed. */
class ProductPresenter
{
    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public static function present(array $product): array
    {
        $seller = $product['seller'] ?? null;

        return [
            'id' => $product['id'],
            'name' => $product['name'] ?? '',
            'description' => $product['description'] ?? null,
            'price' => (string) ($product['price'] ?? '0.00'),
            'stock' => (int) ($product['stock'] ?? 0),
            'images' => collect(ApiData::array($product['images'] ?? []))
                ->filter(fn ($image) => is_string($image['url'] ?? null))
                ->map(fn ($image) => ['url' => ImageUrl::fix($image['url'])])
                ->values()
                ->all(),
            'category' => $product['category']['name'] ?? null,
            'seller_name' => $seller['shop_name'] ?? $seller['name'] ?? null,
        ];
    }
}
