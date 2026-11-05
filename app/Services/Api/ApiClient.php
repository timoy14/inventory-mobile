<?php

namespace App\Services\Api;

use App\Services\Api\Exceptions\ApiDomainError;
use App\Services\Api\Exceptions\ApiException;
use App\Services\Api\Exceptions\ApiForbidden;
use App\Services\Api\Exceptions\ApiNotFound;
use App\Services\Api\Exceptions\ApiSuspended;
use App\Services\Api\Exceptions\ApiUnauthorized;
use App\Services\Api\Exceptions\ApiUnavailable;
use App\Services\Api\Exceptions\ApiValidation;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ApiClient
{
    public const DEFAULT_SETTINGS = [
        'store_name' => 'Inventory',
        'currency_symbol' => '$',
        'currency_code' => 'USD',
        'test_mode_message' => 'This is a testing site only. No real payment will be taken.',
    ];

    private const SETTINGS_CACHE_KEY = 'api.public_settings';

    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    /** @var array<string, mixed>|null */
    private ?array $cart = null;

    /**
     * @param  Closure(): ?string|null  $tokenResolver  Resolves the bearer token lazily (bound to DeviceSession::token()).
     */
    public function __construct(private ?Closure $tokenResolver = null) {}

    /** A client that never sends a bearer token (login, register, public settings). */
    public static function anonymous(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send(fn (PendingRequest $r) => $r->get($this->url($path), $query), write: false);
    }

    /**
     * The cart payload, fetched once per request; any write through this client drops the memo.
     *
     * @return array<string, mixed>
     */
    public function cart(): array
    {
        return $this->cart ??= $this->get('/cart');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function post(string $path, array $data = []): array
    {
        return $this->send(fn (PendingRequest $r) => $r->asJson()->post($this->url($path), $data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function patch(string $path, array $data = []): array
    {
        return $this->send(fn (PendingRequest $r) => $r->asJson()->patch($this->url($path), $data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function delete(string $path, array $data = []): array
    {
        return $this->send(fn (PendingRequest $r) => $r->asJson()->delete($this->url($path), $data));
    }

    /**
     * @param  array<string, mixed>  $fields  Array values are sent as repeated `key[]` parts.
     * @param  array<string, array<int, UploadedFile|string>>  $files  e.g. ['images[]' => [UploadedFile|absolute path]]
     * @return array<string, mixed>
     */
    public function multipart(string $path, array $fields, array $files): array
    {
        return $this->send(function (PendingRequest $r) use ($path, $fields, $files) {
            $r = $r->asMultipart();

            foreach ($files as $name => $list) {
                foreach ((array) $list as $file) {
                    $r = $file instanceof UploadedFile
                        ? $r->attach($name, $file->getContent(), $file->getClientOriginalName())
                        : $r->attach($name, (string) file_get_contents($file), basename($file));
                }
            }

            return $r->post($this->url($path), $this->flattenFields($fields));
        });
    }

    /** @return array<string, mixed> */
    public function publicSettings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        // Only a successful API answer is cached across requests; the fallback is retried next time.
        if (($cached = Cache::get(self::SETTINGS_CACHE_KEY)) !== null) {
            return $this->settings = $cached;
        }

        try {
            $data = self::anonymous()->get('/settings/public');
        } catch (ApiUnavailable) {
            return $this->settings = self::DEFAULT_SETTINGS;
        }

        $settings = array_merge(self::DEFAULT_SETTINGS, $data);
        Cache::put(self::SETTINGS_CACHE_KEY, $settings, 600);

        return $this->settings = $settings;
    }

    /**
     * Convert ['remove_images' => ['a','b']] to Laravel multipart parts with repeated `remove_images[]` keys.
     *
     * @param  array<string, mixed>  $fields
     * @return list<array{name: string, contents: string}>
     */
    private function flattenFields(array $fields): array
    {
        $parts = [];

        foreach ($fields as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                $parts[] = [
                    'name' => is_array($value) ? rtrim((string) $name, '[]').'[]' : (string) $name,
                    'contents' => is_bool($item) ? ($item ? '1' : '0') : (string) $item,
                ];
            }
        }

        return $parts;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.inventory_api.url'), '/').'/'.ltrim($path, '/');
    }

    private function token(): ?string
    {
        return $this->tokenResolver?->__invoke();
    }

    private function pending(): PendingRequest
    {
        $request = Http::acceptJson()->timeout(10)->connectTimeout(5);

        $token = $this->token();

        return $token ? $request->withToken($token) : $request;
    }

    /**
     * @param  Closure(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(Closure $call, bool $write = true): array
    {
        if ($write) {
            $this->cart = null;
        }

        try {
            $response = $call($this->pending());
        } catch (ConnectionException $e) {
            throw new ApiUnavailable(0, [], $e->getMessage());
        }

        throw_if(! $response->successful(), $this->exceptionFor($response));

        return $response->status() === 204 ? [] : ($response->json() ?? []);
    }

    private function exceptionFor(Response $response): ApiException
    {
        $status = $response->status();
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        return match (true) {
            $status === 401 => new ApiUnauthorized($status, $body),
            $status === 403 && ($body['error'] ?? null) === 'account_suspended' => new ApiSuspended($status, $body),
            $status === 403 => new ApiForbidden($status, $body),
            $status === 404 => new ApiNotFound($status, $body),
            $status === 422 && isset($body['errors']) => new ApiValidation($status, $body),
            $status === 422 && isset($body['error']) => new ApiDomainError($status, $body),
            default => $status === 429 || $status >= 500
                ? new ApiUnavailable($status, $body)
                : new ApiException($status, $body),
        };
    }
}
