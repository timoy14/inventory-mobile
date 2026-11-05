<?php

namespace App\Support;

class Pagination
{
    /**
     * The API's `next` link points at the API host; rebuild a local one for $path that keeps $extra filters.
     *
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $extra
     */
    public static function nextUrl(array $result, string $path, array $extra = []): ?string
    {
        if (empty($result['links']['next'])) {
            return null;
        }

        parse_str((string) parse_url($result['links']['next'], PHP_URL_QUERY), $query);
        $page = (int) ($query['page'] ?? 0);

        return $page > 0 ? $path.'?'.http_build_query(['page' => $page] + $extra) : null;
    }
}
