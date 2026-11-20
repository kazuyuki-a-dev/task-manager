<?php

use App\Http\Controllers\BoardController;
use Illuminate\Support\Facades\Route;

// トップは進み具合のボード（R-01）
Route::get('/', BoardController::class)->name('board');
