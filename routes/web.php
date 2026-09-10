<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\LiteratureMetadataOverrideController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\LiteratureController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileLiteratureController;
use App\Http\Controllers\ProfileReviewController;
use App\Http\Controllers\QuickLogController;
use App\Http\Controllers\ReadingDiaryController;
use App\Http\Controllers\ReadingListController;
use App\Http\Controllers\ReadlistController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/search', SearchController::class)->name('search.index');
Route::get('/catalog', [LiteratureController::class, 'index'])->name('literatures.index');
Route::get('/catalog/latest', [LiteratureController::class, 'latest'])->name('literatures.latest');
Route::get('/authors/{author}', AuthorController::class)->name('authors.show');
Route::get('/literatures/{literature}', [LiteratureController::class, 'show'])->name('literatures.show');
Route::get('/members/{user}/followers', [ProfileController::class, 'followers'])->name('profiles.followers');
Route::get('/members/{user}/following', [ProfileController::class, 'following'])->name('profiles.following');
Route::get('/members/{user}/readlist', ReadlistController::class)->name('profiles.readlist');
Route::get('/members/{user}/reviews', ProfileReviewController::class)->name('profiles.reviews');
Route::get('/members/{user}/literature', ProfileLiteratureController::class)->name('profiles.literature');
Route::get('/members/{user}', [ProfileController::class, 'show'])->name('profiles.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/quick-log/literatures', QuickLogController::class)
        ->middleware('throttle:60,1')
        ->name('quick-log.literatures');
    Route::get('/activity', ActivityController::class)->name('activity.index');
    Route::put('/literatures/{literature}/reading-list', [ReadingListController::class, 'update'])
        ->name('reading-list.update');
    Route::delete('/literatures/{literature}/reading-list', [ReadingListController::class, 'destroy'])
        ->name('reading-list.destroy');
    Route::put('/literatures/{literature}/review', [ReviewController::class, 'update'])
        ->name('reviews.update');
    Route::delete('/literatures/{literature}/review', [ReviewController::class, 'destroy'])
        ->name('reviews.destroy');
    Route::post('/literatures/{literature}/discussions', [DiscussionController::class, 'store'])
        ->name('discussions.store');
    Route::post('/discussions/{discussion}/comments', [CommentController::class, 'store'])
        ->name('discussions.comments.store');
    Route::post('/reviews/{review}/like', [LikeController::class, 'storeReview'])
        ->name('reviews.likes.store');
    Route::delete('/reviews/{review}/like', [LikeController::class, 'destroyReview'])
        ->name('reviews.likes.destroy');
    Route::post('/discussions/{discussion}/like', [LikeController::class, 'storeDiscussion'])
        ->name('discussions.likes.store');
    Route::delete('/discussions/{discussion}/like', [LikeController::class, 'destroyDiscussion'])
        ->name('discussions.likes.destroy');
    Route::get('/diary', [ReadingDiaryController::class, 'index'])->name('diary.index');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profiles.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profiles.update');
    Route::post('/members/{user}/follow', [FollowController::class, 'store'])->name('profiles.follow.store');
    Route::delete('/members/{user}/follow', [FollowController::class, 'destroy'])->name('profiles.follow.destroy');
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function (): void {
        Route::get('/literatures/{literature}/metadata', [LiteratureMetadataOverrideController::class, 'edit'])
            ->name('literatures.metadata.edit');
        Route::put('/literatures/{literature}/metadata', [LiteratureMetadataOverrideController::class, 'update'])
            ->name('literatures.metadata.update');
        Route::get('/moderation', [ModerationController::class, 'index'])->name('moderation.index');
        Route::patch('/moderation/{report}', [ModerationController::class, 'update'])->name('moderation.update');
    });
});
