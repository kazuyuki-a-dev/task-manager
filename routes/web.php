<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\OrderAssigneeController;
use App\Http\Controllers\OrderStatusController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// トップは進み具合のボード（R-01）
Route::get('/', BoardController::class)->name('board');

// 指示の状態を変える（R-04）
Route::patch('/orders/{order}/status', [OrderStatusController::class, 'update'])->name('orders.status.update');

// 指示の担当を足す・外す（R-11）
Route::post('/orders/{order}/assignees', [OrderAssigneeController::class, 'store'])->name('orders.assignees.store');
Route::delete('/orders/{order}/assignees/{user}', [OrderAssigneeController::class, 'destroy'])->name('orders.assignees.destroy');

// 完了の履歴（R-10）。中身は R-10 で作る。今はボードのボタンの行き先だけ用意する
Route::get('/history', fn () => Inertia::render('History'))->name('history');
