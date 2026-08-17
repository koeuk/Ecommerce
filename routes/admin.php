<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Loaded from routes/web.php inside the ['auth', 'admin'] middleware group
| with the "admin." name prefix. Each resource is gated on the spatie
| permissions seeded by RoleSeeder.
|
*/

Route::get('dashboard', [AdminController::class, 'index'])->name('dashboard');

// Brands
Route::middleware('can:view brand')->group(function () {
    Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
});

Route::middleware('can:create brand')->group(function () {
    Route::get('brands/create', [BrandController::class, 'create'])->name('brands.create');
    Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
});

Route::middleware('can:update brand')->group(function () {
    Route::get('brands/{brand:id}/edit', [BrandController::class, 'edit'])->name('brands.edit');
    Route::put('brands/{brand:id}', [BrandController::class, 'update'])->name('brands.update');
});

Route::delete('brands/{brand:id}', [BrandController::class, 'destroy'])
    ->middleware('can:delete brand')
    ->name('brands.destroy');

// Categories
Route::middleware('can:view category')->group(function () {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
});

Route::middleware('can:create category')->group(function () {
    Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
});

Route::middleware('can:update category')->group(function () {
    Route::get('categories/{category:id}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('categories/{category:id}', [CategoryController::class, 'update'])->name('categories.update');
});

Route::delete('categories/{category:id}', [CategoryController::class, 'destroy'])
    ->middleware('can:delete category')
    ->name('categories.destroy');

// Products
Route::middleware('can:view product')->group(function () {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
});

Route::middleware('can:create product')->group(function () {
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::post('products/{product:id}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
});

Route::middleware('can:update product')->group(function () {
    Route::get('products/{product:id}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('products/{product:id}', [ProductController::class, 'update'])->name('products.update');

    // Image sub-resources
    Route::delete('products/{product:id}/images/{image}', [ProductController::class, 'deleteImage'])->name('products.images.destroy');
    Route::put('products/{product:id}/images/reorder', [ProductController::class, 'reorderImages'])->name('products.images.reorder');
    Route::put('products/{product:id}/images/{image}/primary', [ProductController::class, 'setPrimaryImage'])->name('products.images.primary');
});

Route::delete('products/{product:id}', [ProductController::class, 'destroy'])
    ->middleware('can:delete product')
    ->name('products.destroy');

// Orders
Route::middleware('can:view order')->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order:id}', [OrderController::class, 'show'])->name('orders.show');
});

Route::middleware('can:update order')->group(function () {
    Route::put('orders/{order:id}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::put('orders/{order:id}/paid', [OrderController::class, 'markPaid'])->name('orders.paid');
    Route::put('orders/{order:id}/note', [OrderController::class, 'updateNote'])->name('orders.note');
});

// Inventory
Route::middleware('can:view inventory')->group(function () {
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory/{variant:id}/history', [InventoryController::class, 'history'])->name('inventory.history');
});

Route::put('inventory/{variant:id}/adjust', [InventoryController::class, 'adjust'])
    ->middleware('can:update inventory')
    ->name('inventory.adjust');
