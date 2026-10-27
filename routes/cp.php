<?php

declare(strict_types=1);

use Bpmore\Sweep\Http\Controllers\HistoryController;
use Bpmore\Sweep\Http\Controllers\SearchController;
use Bpmore\Sweep\Http\Controllers\SweepController;
use Illuminate\Support\Facades\Route;

// Same idiom Statamic core uses for its own control panel routes. Every name
// starts statamic.cp.sweep., which no other bpmore addon uses.
Route::prefix('sweep')->name('sweep.')->group(function (): void {
    Route::get('/', [SearchController::class, 'index'])->name('index');
    Route::get('history', [HistoryController::class, 'index'])->name('history');
    Route::post('sweeps', [SearchController::class, 'store'])->name('sweeps.store');
    Route::get('sweeps/{sweep}', [SweepController::class, 'show'])->name('sweeps.show');
    Route::patch('sweeps/{sweep}/selection', [SweepController::class, 'select'])->name('sweeps.select');
    Route::post('sweeps/{sweep}/apply', [SweepController::class, 'apply'])->name('sweeps.apply');
    Route::post('sweeps/{sweep}/undo', [SweepController::class, 'undo'])->name('sweeps.undo');
    Route::get('sweeps/{sweep}/again', [SweepController::class, 'again'])->name('sweeps.again');
});
