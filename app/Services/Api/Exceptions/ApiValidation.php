<?php

namespace App\Services\Api\Exceptions;

class ApiValidation extends ApiException
{
    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return is_array($this->body['errors'] ?? null) ? $this->body['errors'] : [];
    }
}
