<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealerController;
use App\Http\Controllers\DeliveryPointController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\TransportController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductUnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SalesmanCommissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Protected routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Product Categories
    Route::resource('product-categories', ProductCategoryController::class);

    // Product Units
    Route::resource('product-units', ProductUnitController::class);

    // Products
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::resource('products', ProductController::class);

    // Stock Management
    Route::get('stock-history', [\App\Http\Controllers\StockController::class, 'history'])->name('stocks.history');
    Route::get('stock-history/export', [\App\Http\Controllers\StockController::class, 'export'])->name('stocks.history.export');
    Route::get('stocks/export', [\App\Http\Controllers\StockController::class, 'exportStocks'])->name('stocks.export');
    Route::resource('stocks', \App\Http\Controllers\StockController::class);

    // Customers
    Route::resource('customers', CustomerController::class);

    // Accounts
    Route::resource('accounts', AccountController::class);

    // Dealers
    Route::resource('dealers', DealerController::class);

    // Users
    Route::resource('users', \App\Http\Controllers\UserController::class);

    // Salesman Commissions
    Route::resource('salesman-commissions', SalesmanCommissionController::class);
    Route::get('salesman-commissions-export', [SalesmanCommissionController::class, 'export'])->name('salesman-commissions.export');

    // Delivery Points
    Route::resource('delivery-points', DeliveryPointController::class);

    // Transports
    Route::resource('transports', TransportController::class);
});

