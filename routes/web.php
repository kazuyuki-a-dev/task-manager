<?php

use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\OrderAssigneeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Middleware\EnsureAdminSession;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// トップは進み具合のボード（R-01）
Route::get('/', BoardController::class)->name('board');

// 指示の内容を直す（R-03 の編集）。だれでもできる（D-027）
Route::patch('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');

// 指示の状態を変える（R-04）
Route::patch('/orders/{order}/status', [OrderStatusController::class, 'update'])->name('orders.status.update');

// 指示の担当を足す・外す（R-11）
Route::post('/orders/{order}/assignees', [OrderAssigneeController::class, 'store'])->name('orders.assignees.store');
Route::delete('/orders/{order}/assignees/{user}', [OrderAssigneeController::class, 'destroy'])->name('orders.assignees.destroy');

// 完了の履歴（R-10）。中身は R-10 で作る。今はボードのボタンの行き先だけ用意する
Route::get('/history', fn () => Inertia::render('History'))->name('history');

// 管理者（R-06）。パスワードを入れた管理者だけが使える
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware(EnsureAdminSession::class)->group(function () {
        Route::get('/', fn () => Inertia::render('admin/Home'))->name('home');

        // 製品の一覧と検索（R-12）
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::patch('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });
});
