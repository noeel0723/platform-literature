<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LiteratureController;
use App\Http\Controllers\ReadingDiaryController;
use App\Http\Controllers\ReadingListController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LiteratureController::class, 'index'])->name('home');
Route::get('/catalog', [LiteratureController::class, 'index'])->name('literatures.index');
Route::get('/literatures/{literature}', [LiteratureController::class, 'show'])->name('literatures.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::put('/literatures/{literature}/reading-list', [ReadingListController::class, 'update'])
        ->name('reading-list.update');
    Route::get('/diary', [ReadingDiaryController::class, 'index'])->name('diary.index');
});
