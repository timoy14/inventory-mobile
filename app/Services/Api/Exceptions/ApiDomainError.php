<?php

namespace App\Services\Api\Exceptions;

class ApiDomainError extends ApiException
{
    public function code(): string
    {
        return (string) ($this->body['error'] ?? '');
    }

    /**
     * Extra fields from the response (e.g. product_ids, from, to).
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return array_diff_key($this->body, array_flip(['message', 'error']));
    }
}
