<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SitemapController;
use App\Http\Controllers\Api\V1\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer API (v1)
|--------------------------------------------------------------------------
|
| The storefront is API-driven. Filtering, sorting and eager-loading follow
| spatie/laravel-query-builder conventions:
|
|   filter[search]=macbook&filter[brand]=apple&sort=-price&include=brand,images
|
| Response language comes from ?lang=km or the Accept-Language header.
|
*/

Route::prefix('v1')->middleware('api.locale')->group(function () {

    // Storefront chrome — home feed, navigation, settings, filter metadata
    Route::get('home', [StorefrontController::class, 'home']);
    Route::get('categories-tree', [StorefrontController::class, 'categoryTree']);
    Route::get('settings', [StorefrontController::class, 'settings']);
    Route::get('filters', [StorefrontController::class, 'filters']);
    Route::get('sitemap', SitemapController::class);

    // Public catalog
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{slug}', [ProductController::class, 'show']);

    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{slug}', [CategoryController::class, 'show']);

    Route::get('brands', [BrandController::class, 'index']);
    Route::get('brands/{slug}', [BrandController::class, 'show']);

    /*
     * Cart — works for guests and signed-in customers alike.
     * Guests carry an `X-Cart-Token` header; the server mints one on the
     * first write and returns it on the response.
     */
    Route::get('cart', [CartController::class, 'show']);
    Route::post('cart', [CartController::class, 'store']);
    Route::patch('cart/{item}', [CartController::class, 'update']);
    Route::delete('cart/{item}', [CartController::class, 'destroy']);
    Route::delete('cart', [CartController::class, 'clear']);

    Route::post('cart/merge', [CartController::class, 'merge'])->middleware('auth:sanctum');

    // Checkout — COD. Guests and signed-in customers both.
    Route::post('checkout/quote', [CheckoutController::class, 'quote']);
    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout');
    Route::get('orders/{number}', [CheckoutController::class, 'track']);

    // Customer auth — Sanctum tokens. Throttled, since these take credentials.
    Route::middleware('throttle:customer-auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    // Authenticated customer
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);

        // Account — order history, profile, wishlist
        Route::get('account/orders', [AccountController::class, 'orders']);
        Route::post('account/orders/{number}/cancel', [AccountController::class, 'cancelOrder']);
        Route::put('account/profile', [AccountController::class, 'updateProfile']);
        Route::put('account/password', [AccountController::class, 'updatePassword']);

        Route::get('wishlist', [AccountController::class, 'wishlist']);
        Route::post('wishlist', [AccountController::class, 'addToWishlist']);
        Route::delete('wishlist/{product}', [AccountController::class, 'removeFromWishlist']);

        // Address book
        Route::get('addresses', [AddressController::class, 'index']);
        Route::post('addresses', [AddressController::class, 'store']);
        Route::put('addresses/{address}', [AddressController::class, 'update']);
        Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
    });
});
