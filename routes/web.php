<?php

use App\Http\Controllers\LiteratureController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LiteratureController::class, 'index'])->name('home');
Route::get('/catalog', [LiteratureController::class, 'index'])->name('literatures.index');
Route::get('/literatures/{literature}', [LiteratureController::class, 'show'])->name('literatures.show');
