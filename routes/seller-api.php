<?php

use App\Http\Controllers\Api\Seller\SellerAuthApiController;
use App\Http\Controllers\Api\Seller\SellerAttributeApiController;
use App\Http\Controllers\Api\Seller\SellerAttributeValueApiController;
use App\Http\Controllers\Api\Seller\SellerProductApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('seller')->name('seller-api.')->group(function () {
    Route::post('register', [SellerAuthApiController::class, 'createSeller'])->name('register');
    Route::post('login', [SellerAuthApiController::class, 'login'])->name('login');
});

Route::middleware('auth:sanctum')->prefix('seller')->name('seller.api')->group(function () {
    Route::post('logout', [SellerAuthApiController::class, 'logout']);

    // Attributes CRUD
    Route::get('attributes', [SellerAttributeApiController::class, 'index'])->name('attributes.index');
    Route::get('attributes/{id}', [SellerAttributeApiController::class, 'show'])->name('attributes.show');
    Route::post('attributes', [SellerAttributeApiController::class, 'store'])->name('attributes.store');
    Route::post('attributes/{id}', [SellerAttributeApiController::class, 'update'])->name('attributes.update');
    Route::delete('attributes/{id}', [SellerAttributeApiController::class, 'destroy'])->name('attributes.destroy');

    // Attribute values CRUD
    Route::get('attribute-values', [SellerAttributeValueApiController::class, 'index'])->name('attribute_values.index');
    Route::get('attribute-values/{id}', [SellerAttributeValueApiController::class, 'show'])->name('attribute_values.show');
    Route::post('attribute-values', [SellerAttributeValueApiController::class, 'store'])->name('attribute_values.store');
    Route::post('attribute-values/{id}', [SellerAttributeValueApiController::class, 'update'])->name('attribute_values.update');
    Route::delete('attribute-values/{id}', [SellerAttributeValueApiController::class, 'destroy'])->name('attribute_values.destroy');

    // Products CRUD
    Route::get('products', [SellerProductApiController::class, 'index'])->name('products.index');
    Route::get('products/{id}', [SellerProductApiController::class, 'show'])->name('products.show');
    Route::post('products', [SellerProductApiController::class, 'store'])->name('products.store');
    Route::post('products/{id}', [SellerProductApiController::class, 'update'])->name('products.update');
    Route::delete('products/{id}', [SellerProductApiController::class, 'destroy'])->name('products.destroy');
});
