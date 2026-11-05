<?php

namespace App\Support;

class ImageUrl
{
    private const LOOPBACK = ['127.0.0.1', 'localhost'];

    /**
     * Point loopback image URLs at the configured API host so an emulator can load them.
     */
    public static function fix(string $url): string
    {
        $api = parse_url((string) config('services.inventory_api.url'));
        $apiHost = $api['host'] ?? null;

        if ($apiHost === null || in_array($apiHost, self::LOOPBACK, true)) {
            return $url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! in_array($parts['host'] ?? null, self::LOOPBACK, true)) {
            return $url;
        }

        $authority = $apiHost.(isset($api['port']) ? ':'.$api['port'] : '');

        return ($parts['scheme'] ?? $api['scheme'] ?? 'http').'://'.$authority
            .($parts['path'] ?? '')
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
