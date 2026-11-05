<?php

namespace App\Support;

/** Narrows decoded API JSON, which PHP only knows as `mixed`, to the shapes the pages read. */
class ApiData
{
    /**
     * The value when it is an array (a JSON list or object), otherwise an empty array.
     *
     * @return array<array-key, mixed>
     */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
