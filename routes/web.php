<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\SellerProductController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Guest pages: always renderable, so a stale token can never cause a redirect loop.
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store']);

Route::middleware('app.auth')->group(function () {
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::get('/account', AccountController::class)->name('account');

    // Browsing is open to both roles: sellers have a Shop tab too.
    Route::get('/shop', [ShopController::class, 'index'])->name('shop');
    Route::get('/shop/{id}', [ShopController::class, 'show'])->whereNumber('id')->name('shop.show');

    Route::middleware('app.role:customer')->group(function () {
        Route::get('/cart', [CartController::class, 'show'])->name('cart');
        Route::post('/cart/items', [CartController::class, 'store']);
        Route::patch('/cart/items/{product}', [CartController::class, 'update'])->whereNumber('product');
        Route::delete('/cart/items/{product}', [CartController::class, 'destroy'])->whereNumber('product');

        Route::post('/checkout', [CheckoutController::class, 'store']);
        Route::get('/checkout/{order}', [CheckoutController::class, 'show'])->whereNumber('order');
        Route::post('/orders/{order}/pay', [CheckoutController::class, 'pay'])->whereNumber('order');
        Route::get('/orders/{order}/paid', [CheckoutController::class, 'paid'])->whereNumber('order');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->whereNumber('order');
    });

    Route::middleware('app.role:seller')->prefix('seller')->group(function () {
        Route::get('/products', [SellerProductController::class, 'index']);
        Route::get('/products/create', [SellerProductController::class, 'create']);
        Route::post('/products', [SellerProductController::class, 'store']);
        Route::get('/products/{id}/edit', [SellerProductController::class, 'edit'])->whereNumber('id');
        Route::put('/products/{id}', [SellerProductController::class, 'update'])->whereNumber('id');
        Route::delete('/products/{id}', [SellerProductController::class, 'destroy'])->whereNumber('id');
        Route::post('/photos/pick', [SellerProductController::class, 'pick']);
        Route::post('/photos/discard', [SellerProductController::class, 'discardPicked']);
        Route::get('/orders', [SellerOrderController::class, 'index']);
    });
});
