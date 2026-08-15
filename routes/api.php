<?php

use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Http\Request;
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

    // Public catalog
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{slug}', [ProductController::class, 'show']);

    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{slug}', [CategoryController::class, 'show']);

    Route::get('brands', [BrandController::class, 'index']);
    Route::get('brands/{slug}', [BrandController::class, 'show']);

    // Authenticated customer
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', fn (Request $request) => $request->user());
    });
});
