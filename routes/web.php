<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\EventProductController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'role'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', fn () => redirect()->route(auth()->user()->isCashier() ? 'pos.index' : 'dashboard'))->name('home');

    Route::get('/akun/password', [AccountController::class, 'editPassword'])->name('account.password');
    Route::put('/akun/password', [AccountController::class, 'updatePassword']);

    // Kasir / POS (kasir dan admin)
    Route::middleware('role:admin,kasir')->group(function () {
        Route::get('/kasir', [PosController::class, 'index'])->name('pos.index');
        Route::get('/kasir/{event}', [PosController::class, 'show'])->name('pos.show');
        Route::get('/kasir/{event}/produk', [PosController::class, 'products'])->name('pos.products');
        Route::post('/kasir/{event}/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    });

    // Riwayat transaksi & struk (semua role; kasir dibatasi kegiatan yang ditugaskan)
    Route::get('/transaksi', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transaksi/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::get('/transaksi/{transaction}/struk', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::post('/transaksi/{transaction}/batal', [TransactionController::class, 'void'])->middleware('role:admin')->name('transactions.void');

    // Dashboard & laporan (admin dan pimpinan)
    Route::middleware('role:admin,pimpinan')->group(function () {
        Route::get('/dashboard', [ReportController::class, 'dashboard'])->name('dashboard');
        Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/ekspor', [ReportController::class, 'export'])->name('reports.export');
    });

    // Administrasi
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update']);
        Route::resource('products', ProductController::class)->except(['destroy']);

        Route::resource('events', EventController::class);
        Route::post('events/{event}/activate', [EventController::class, 'activate'])->name('events.activate');
        Route::post('events/{event}/close', [EventController::class, 'close'])->name('events.close');
        Route::put('events/{event}/cashiers', [EventController::class, 'syncCashiers'])->name('events.cashiers');

        Route::post('events/{event}/products', [EventProductController::class, 'store'])->name('event-products.store');
        Route::scopeBindings()->group(function () {
            Route::put('events/{event}/products/{eventProduct}', [EventProductController::class, 'update'])->name('event-products.update');
            Route::post('events/{event}/products/{eventProduct}/stock', [EventProductController::class, 'adjustStock'])->name('event-products.stock');
            Route::get('events/{event}/products/{eventProduct}/history', [EventProductController::class, 'history'])->name('event-products.history');
        });
    });
});
