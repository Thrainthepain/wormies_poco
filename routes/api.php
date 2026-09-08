<?php

declare(strict_types=1);

use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\MapSolarsystemController;
use App\Http\Controllers\Api\PocoController;
use App\Http\Controllers\Api\SovereigntyController;
use Illuminate\Support\Facades\Route;

Route::get('sovereignties', [SovereigntyController::class, 'index'])->name('api.sovereignties.index');

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::resource('maps', MapController::class)->only(['update', 'show', 'index']);
    Route::resource('map-solarsystems', MapSolarsystemController::class)->only(['show', 'update', 'store', 'destroy']);
    Route::get('maps/{map}/pocos', [PocoController::class, 'index'])->name('maps.pocos.index');
    Route::resource('map-solarsystems.pocos', PocoController::class)->only(['store', 'update', 'destroy'])->shallow();
});
