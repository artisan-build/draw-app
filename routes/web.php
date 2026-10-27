<?php

declare(strict_types=1);

use App\Http\Controllers\BoardSceneController;
use App\Http\Controllers\BoardShareController;
use App\Http\Controllers\PublicBoardShareController;
use App\Livewire\Boards\Index;
use App\Livewire\Boards\SharedWithMe;
use App\Livewire\Boards\Show;
use App\Livewire\Shares\Index as SharesIndex;
use ArtisanBuild\BuiltForCloud\Http\Middleware\EnsureUiAuthority;
use ArtisanBuild\BuiltForCloud\Http\Middleware\EnsureUserIsAuthenticated;
use Illuminate\Support\Facades\Route;

Route::get('/s/{share}', [PublicBoardShareController::class, 'show'])
    ->whereUuid('share')
    ->name('board-shares.public.show');
Route::get('/s/{share}/scene', [PublicBoardShareController::class, 'scene'])
    ->whereUuid('share')
    ->name('board-shares.public.scene');

Route::middleware([EnsureUiAuthority::class, EnsureUserIsAuthenticated::class])->group(function (): void {
    Route::get('/boards', Index::class)->name('boards.index');
    Route::get('/shared-with-me', SharedWithMe::class)->name('boards.shared');
    Route::get('/shares', SharesIndex::class)->name('board-shares.index');
    Route::get('/boards/{board}', Show::class)->whereUuid('board')->name('boards.show');
    Route::get('/boards/{board}/scene', [BoardSceneController::class, 'show'])
        ->whereUuid('board')
        ->name('boards.scene.show');
    Route::put('/boards/{board}/scene', [BoardSceneController::class, 'update'])
        ->whereUuid('board')
        ->name('boards.scene.update');
    Route::post('/boards/{board}/shares', [BoardShareController::class, 'store'])
        ->whereUuid('board')
        ->name('board-shares.store');
    Route::delete('/shares/{share}', [BoardShareController::class, 'destroy'])
        ->whereUuid('share')
        ->name('board-shares.destroy');
});
