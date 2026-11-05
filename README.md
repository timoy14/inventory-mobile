# Inventory (mobile)

A mobile shop built with NativePHP Mobile v4 (Laravel 13, Inertia 3, Vue 3, Tailwind v4).
It has two roles:

- **Customers** browse the shop, manage a cart, check out, pay (test mode only) and view their orders.
- **Sellers** manage their products (with photos) and see the orders that contain their items.

The app has no database of shop data. It is a thin client for the Phase 1 Laravel API in the
`inventory-admin` project, and talks to it over HTTP using a bearer token.

## Requirements

- PHP 8.4 and Composer
- Node 20+ and npm
- The Phase 1 API (`inventory-admin`) running somewhere the app can reach
- For Android: JDK 17, the Android SDK (platform 35) and an emulator or device.
  Android Studio is not required; Gradle downloads the extra SDK pieces it needs (the NDK) on the first build.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

`.env` settings that matter:

| Key                 | Purpose                                                                                                                |
| ------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| `INVENTORY_API_URL` | Phase 1 API base URL. Browser default: `http://127.0.0.1:8799/api/v1`. Android emulator: `http://10.0.2.2:8799/api/v1` |
| `NATIVEPHP_APP_ID`  | App id, default `com.inventory.marketplace`                                                                            |

`10.0.2.2` is how the Android emulator reaches the host machine's `127.0.0.1`. Image URLs that the API returns
for `127.0.0.1` are rewritten to the configured API host so the emulator can load them.

## Running the Phase 1 API

From the `inventory-admin` project (do not edit it from here). To use a scratch database:

```bash
cd ../inventory-admin
DB_DATABASE=/abs/path/demo.sqlite php artisan migrate:fresh --seed --force
cd public
DB_DATABASE=/abs/path/demo.sqlite APP_ENV=local php -S 127.0.0.1:8799 \
  ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Check it with `curl http://127.0.0.1:8799/api/v1/settings/public`. Do not use `php artisan serve` for
this, because it drops `DB_DATABASE`. Login and register are rate limited to 6 per minute per IP.

## Running in the browser

```bash
npm run build
php artisan serve --port=8800
```

Open http://127.0.0.1:8800. For front-end work use `npm run dev` alongside it.
There is a single signed-in device session, so signing in as one user replaces the previous one.

## Running on Android

Set `INVENTORY_API_URL=http://10.0.2.2:8799/api/v1` in `.env` first (the value is bundled into the app),
then:

```bash
export ANDROID_HOME=$HOME/Library/Android/sdk
export JAVA_HOME=$(/usr/libexec/java_home -v 17)
export PATH=$ANDROID_HOME/platform-tools:$ANDROID_HOME/emulator:$PATH

npm run build -- --mode=android
php artisan native:install android
emulator -avd Medium_Phone_API_35 -no-snapshot-save &
adb wait-for-device        # wait until: adb shell getprop sys.boot_completed  ->  1
php artisan native:run android emulator-5554 --no-tty
```

`adb devices` shows the serial to use. The first `native:run` is slow (about 10 minutes) because Gradle downloads the NDK (about 2.4 GB), SDK platform 36 and build-tools 35;
make sure the host has several GB free, because the emulator refuses to start with too little disk space.
The first app start in the emulator is also slow (the WebView showed "Loading..." for about 50 s).
The generated `nativephp/` directory, `native` wrapper and `nativephp.lock` are build output (git-ignored). Code edits are not picked up by pushing files to the device: the app is re-extracted from the bundle at each launch, so rebuild with `native:run`.
Restore the browser URL in `.env` afterwards.

## How the token is stored

On login the API token and the user are encrypted with Laravel's `Crypt` (the app key) and written to the `device_session` table in the app's SQLite database. If the stored value cannot
be decrypted (for example after a reinstall) the user is treated as logged out and the table is cleared.
Logging out calls the API and always clears the local session.

## Test-mode notice

Payments are simulated. The checkout, pay and payment-complete screens show an amber banner with the message that
the API supplies ("This is a testing site only. No real payment will be taken."). A successful test payment
gets a `TEST-` reference. The app never computes prices or totals; it shows what the API returns.

## Tests and checks

```bash
composer lint
npm run check:fix
composer types:check   # PHPStan
php artisan test
```

## Known limits

- **No iOS.** There is no Xcode on the development machine, so iOS has not been built or run.
- Photo picking uses the NativePHP camera plugin when running natively (gallery or camera) and a normal file input in a browser.
  Picked file paths are kept in the session, confined to the app's media folders, sent with the product and then deleted.
  See the Android verification notes for what was and was not checked on a device.
- **Persistent runtime.** NativePHP boots Laravel once and reuses it for every request (`NATIVEPHP_RUNTIME_MODE`, default `persistent`),
  so state kept in singletons leaks between requests. A customer logout once ended in "500 - Too many redirects" on Android for exactly this reason:
  the customer-only `cart_count` shared prop outlived the request and called the API anonymously on the login page.
  `AppServiceProvider` now registers a `Runtime::onReset` hook (`App\Support\ResetRequestState`) that flushes Inertia shared props and scoped instances,
  `cartCount()` makes no call without a token, and a 401 on `/login` or `/register` renders an error page instead of redirecting to itself.
  Never keep per-request state in a singleton or static.
- Verified on the emulator (API on `10.0.2.2`): sign in, shop, product, cart, checkout banner, test payment with a `TEST-` reference,
  seller product list and form, native gallery picker (events share the web session, picked file accepted and uploaded, image URL rewritten and displayed).
  Not verified: camera capture, deleting or editing a product on the device, seller orders, iOS.
- The app is online only. If the API cannot be reached, screens show an "Offline" page with a retry button.
