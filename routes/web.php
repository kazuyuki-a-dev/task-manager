<?php

use App\Http\Controllers\Admin\CalendarController as AdminCalendarController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\HistoryController;
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

// 完了の履歴（R-10）。だれでも見られる
Route::get('/history', HistoryController::class)->name('history');

// 会社カレンダー（R-13）。だれでも見られる
Route::get('/calendar', CalendarController::class)->name('calendar');

// 管理者（R-06）。パスワードを入れた管理者だけが使える
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware(EnsureAdminSession::class)->group(function () {
        Route::get('/', fn () => Inertia::render('admin/Home'))->name('home');

        // 会社カレンダーに休みと行事を足す・取り消す（R-13）。カレンダーの日付の小さな画面から使う
        Route::post('/absences', [AdminCalendarController::class, 'storeAbsence'])->name('absences.store');
        Route::delete('/absences/{absence}', [AdminCalendarController::class, 'destroyAbsence'])->name('absences.destroy');
        Route::post('/calendar-events', [AdminCalendarController::class, 'storeEvent'])->name('calendar-events.store');
        Route::delete('/calendar-events/{event}', [AdminCalendarController::class, 'destroyEvent'])->name('calendar-events.destroy');

        // 社員の登録（R-07）
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::patch('/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

        // 取引会社（R-14）
        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::patch('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');

        // 指示の登録と削除（R-03）。削除はボードの小さな画面から使う
        Route::get('/orders/create', [AdminOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [AdminOrderController::class, 'store'])->name('orders.store');
        Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');

        // 製品の一覧と検索（R-12）
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::patch('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });
});
