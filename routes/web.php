<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\LiteratureController;
use App\Http\Controllers\ReadingDiaryController;
use App\Http\Controllers\ReadingListController;
use App\Http\Controllers\ReviewController;
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
    Route::put('/literatures/{literature}/review', [ReviewController::class, 'update'])
        ->name('reviews.update');
    Route::post('/literatures/{literature}/discussions', [DiscussionController::class, 'store'])
        ->name('discussions.store');
    Route::post('/discussions/{discussion}/comments', [CommentController::class, 'store'])
        ->name('discussions.comments.store');
    Route::get('/diary', [ReadingDiaryController::class, 'index'])->name('diary.index');
});
