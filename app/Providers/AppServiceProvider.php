<?php

namespace App\Providers;

use App\Listeners\StorePickedPhotos;
use App\Services\Api\ApiClient;
use App\Services\Session\DeviceSession;
use App\Support\ResetRequestState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Runtime;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The bearer is resolved lazily on every request, so login/logout mid-request is picked up.
        // Scoped so the runtime reset (forgetScopedInstances) drops both between requests.
        $this->app->scoped(DeviceSession::class);
        $this->app->scoped(ApiClient::class, fn ($app) => new ApiClient(fn () => $app->make(DeviceSession::class)->token()));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // The persistent native runtime reuses one app for every request: reset per-request state between them.
        Runtime::onReset(ResetRequestState::flush(...));

        // Native picker results arrive as events; park the temp file paths in the session.
        Event::listen(MediaSelected::class, [StorePickedPhotos::class, 'handleMediaSelected']);
        Event::listen(PhotoTaken::class, [StorePickedPhotos::class, 'handlePhotoTaken']);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
