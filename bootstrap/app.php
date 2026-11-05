<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSignedIn;
use App\Http\Middleware\HandleInertiaRequests;
use App\Services\Api\Exceptions\ApiDomainError;
use App\Services\Api\Exceptions\ApiException;
use App\Services\Api\Exceptions\ApiForbidden;
use App\Services\Api\Exceptions\ApiNotFound;
use App\Services\Api\Exceptions\ApiSuspended;
use App\Services\Api\Exceptions\ApiUnauthorized;
use App\Services\Api\Exceptions\ApiUnavailable;
use App\Services\Api\Exceptions\ApiValidation;
use App\Support\Auth\SignOut;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'app.auth' => EnsureSignedIn::class,
            'app.role' => EnsureRole::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            ApiUnauthorized::class,
            ApiSuspended::class,
            ApiForbidden::class,
            ApiNotFound::class,
            ApiValidation::class,
            ApiDomainError::class,
            ApiUnavailable::class,
        ]);

        $exceptions->map(
            ApiValidation::class,
            fn (ApiValidation $e) => ValidationException::withMessages($e->errors()),
        );

        $exceptions->render(function (ApiUnauthorized $e, Request $request) {
            SignOut::run();

            // Never redirect the sign-in pages to themselves (a loop the native webview follows 10 times).
            if ($request->is('login', 'register')) {
                return Inertia::render('Error', [
                    'status' => 401,
                    'message' => 'Please try signing in again.',
                ])->toResponse($request)->setStatusCode(401);
            }

            return redirect('/login');
        });

        $exceptions->render(function (ApiSuspended $e) {
            SignOut::run();

            return redirect('/login')->with('error', 'Your account has been suspended');
        });

        $exceptions->render(fn (ApiDomainError $e) => back()->with('error', $e->getMessage()));

        $exceptions->render(fn (ApiNotFound $e, Request $request) => Inertia::render('Error', [
            'status' => 404,
            'message' => "We couldn't find what you were looking for.",
        ])->toResponse($request)->setStatusCode(404));

        // Offline is a GET-able page; a failed mutation goes back to where it came from with a toast.
        $exceptions->render(fn (ApiUnavailable $e, Request $request) => $request->isMethod('GET')
            ? Inertia::render('Offline', ['retry_url' => $request->fullUrl()])->toResponse($request)
            : back()->with('error', "Can't reach the store right now — try again"));

        $exceptions->render(fn (ApiForbidden $e, Request $request) => Inertia::render('Error', [
            'status' => 403,
            'message' => "You don't have access to that.",
        ])->toResponse($request)->setStatusCode(403));

        // Catch-all for any other API failure; registered last so the specific handlers above win.
        $exceptions->render(fn (ApiException $e, Request $request) => Inertia::render('Error', [
            'status' => 502,
            'message' => 'Something went wrong talking to the store.',
        ])->toResponse($request)->setStatusCode(502));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
