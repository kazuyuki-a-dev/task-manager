<?php

use App\Http\Controllers\BoardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// トップは進み具合のボード（R-01）
Route::get('/', BoardController::class)->name('board');

// 完了の履歴（R-10）。中身は R-10 で作る。今はボードのボタンの行き先だけ用意する
Route::get('/history', fn () => Inertia::render('History'))->name('history');
