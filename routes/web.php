<?php

declare(strict_types=1);

use App\Http\Controllers\BoardSceneController;
use App\Livewire\Boards\Index;
use App\Livewire\Boards\Show;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/boards', Index::class)->name('boards.index');
    Route::get('/boards/{board}', Show::class)->whereUuid('board')->name('boards.show');
    Route::get('/boards/{board}/scene', [BoardSceneController::class, 'show'])
        ->whereUuid('board')
        ->name('boards.scene.show');
    Route::put('/boards/{board}/scene', [BoardSceneController::class, 'update'])
        ->whereUuid('board')
        ->name('boards.scene.update');
});
