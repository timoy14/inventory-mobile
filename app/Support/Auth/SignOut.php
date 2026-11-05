<?php

namespace App\Support\Auth;

use App\Services\Session\DeviceSession;
use App\Support\PickedPhotos;

class SignOut
{
    /**
     * Clear the local device session.
     */
    public static function run(): void
    {
        app(DeviceSession::class)->clear();
        PickedPhotos::purge();
    }
}
