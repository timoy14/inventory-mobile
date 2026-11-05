<?php

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use Inertia\Inertia;

/**
 * NativePHP's persistent runtime boots Laravel once and dispatches every request through the same
 * application, so anything a request leaves in a singleton leaks into the next one. This clears it.
 */
class ResetRequestState
{
    public static function flush(Application $app): void
    {
        // Shared props are registered per request by the middleware; a leftover closure from a
        // previous user (e.g. cart_count) would otherwise be resolved on the next page.
        Inertia::flushShared();

        // Drops the scoped ApiClient (its memoised settings and token resolver) and similar.
        $app->forgetScopedInstances();
    }
}
