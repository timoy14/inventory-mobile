<?php

namespace App\Services\Session;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Device-global session: one signed-in user per installed app.
 * The API token and user JSON are encrypted at rest with the app key.
 */
class DeviceSession
{
    private const TABLE = 'device_session';

    // Reads are memoised for the request; the instance is scoped, so the runtime reset drops it.
    private ?string $token = null;

    /** @var array<string, mixed>|null */
    private ?array $user = null;

    private bool $tokenLoaded = false;

    private bool $userLoaded = false;

    public function token(): ?string
    {
        if (! $this->tokenLoaded) {
            $token = $this->read('token');
            // A failed decrypt clears the session (and this memo), so mark loaded only afterwards.
            $this->token = $token;
            $this->tokenLoaded = true;
        }

        return $this->token;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if (! $this->userLoaded) {
            $json = $this->read('user');
            $user = $json === null ? null : json_decode($json, true);
            $this->user = is_array($user) ? $user : null;
            $this->userLoaded = true;
        }

        return $this->user;
    }

    /**
     * The user, but only while a token is also stored.
     *
     * @return array<string, mixed>|null
     */
    public function signedInUser(): ?array
    {
        return $this->token() !== null ? $this->user() : null;
    }

    /** @param array<string, mixed> $user */
    public function store(string $token, array $user): void
    {
        $this->write('token', $token);
        $this->storeUser($user);
        $this->forget();
    }

    /** @param array<string, mixed> $user */
    public function storeUser(array $user): void
    {
        $this->write('user', json_encode($user, JSON_THROW_ON_ERROR));
        $this->forget();
    }

    public function clear(): void
    {
        DB::table(self::TABLE)->delete();
        $this->forget();
    }

    private function forget(): void
    {
        $this->token = $this->user = null;
        $this->tokenLoaded = $this->userLoaded = false;
    }

    private function read(string $key): ?string
    {
        $value = DB::table(self::TABLE)->where('key', $key)->value('value');

        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Reinstall, restored backup or tampering: treat as signed out and drop everything.
            $this->clear();

            return null;
        }
    }

    private function write(string $key, string $plain): void
    {
        DB::table(self::TABLE)->updateOrInsert(['key' => $key], ['value' => Crypt::encryptString($plain)]);
    }
}
