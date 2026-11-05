<?php

namespace App\Support;

/** A seller's own product: the shared presentation plus the fields the form needs. */
class SellerProductPresenter
{
    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public static function present(array $product): array
    {
        return [
            ...ProductPresenter::present($product),
            'sku' => (string) ($product['sku'] ?? ''),
            'status' => (string) ($product['status'] ?? 'inactive'),
            'category_id' => $product['category']['id'] ?? null,
            'images' => collect(ApiData::array($product['images'] ?? []))
                ->filter(fn ($image) => is_string($image['url'] ?? null) && is_string($image['path'] ?? null))
                ->map(fn ($image) => ['path' => $image['path'], 'url' => ImageUrl::fix($image['url'])])
                ->values()
                ->all(),
        ];
    }
}
