<?php

namespace App\Services\Api\Exceptions;

use RuntimeException;

class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(public readonly int $status, public readonly array $body = [], ?string $message = null)
    {
        parent::__construct($message ?? (is_string($body['message'] ?? null) ? $body['message'] : 'API error '.$status), $status);
    }
}
