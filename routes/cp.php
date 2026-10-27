<?php

declare(strict_types=1);

use Bpmore\Sweep\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// Same idiom Statamic core uses for its own control panel routes. Every name
// starts statamic.cp.sweep., which no other bpmore addon uses.
Route::prefix('sweep')->name('sweep.')->group(function (): void {
    Route::get('/', [SearchController::class, 'index'])->name('index');
});
