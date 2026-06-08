<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\MessengerController;
use App\Http\Controllers\WhatsappController;
use App\Http\Controllers\FacebookController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::view('privacy', 'privacy');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Facebook routes
    Route::prefix('facebook')->name('facebook.')->group(function () {
        Route::get('/connect', [FacebookController::class, 'connect'])->name('connect');
        Route::get('/callback', [FacebookController::class, 'callback'])->name('callback');
        Route::get('/pages', [FacebookController::class, 'pages'])->name('pages');
        Route::post('/pages/{page}/webhook', [FacebookController::class, 'setupWebhook'])->name('webhook.setup');
        Route::delete('/disconnect', [FacebookController::class, 'disconnect'])->name('disconnect');
    });

    // Settings routes
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::post('/update', [SettingsController::class, 'update'])->name('update');
        Route::post('/pages/{page}/toggle-auto-reply', [SettingsController::class, 'toggleAutoReply'])->name('toggle-auto-reply');
    });

    // Messenger routes
    Route::prefix('messenger-facebook')->name('messenger.')->group(function () {
        Route::get('/', [MessengerController::class, 'index'])->name('index');
        Route::get('/customer/{customer}', [MessengerController::class, 'show'])->name('show');
        Route::post('/customer/{customer}/send-message', [MessengerController::class, 'sendMessage'])->name('send-message');
        Route::get('/customer/{customer}/messages', [MessengerController::class, 'getMessages'])->name('get-messages');
        Route::post('/customer/{customer}/toggle-auto-reply', [MessengerController::class, 'toggleAutoReply'])->name('toggle-customer-auto-reply');
    });

    Route::prefix('messenger-whatsapp')->name('messenger.whatsapp.')->group(function () {
       Route::get('/', [WhatsappController::class, 'index'])->name('index');
       Route::post('/customer/toggle-auto-reply/{user_id}', [WhatsappController::class, 'toggleAutoReply'])->name('toggle-customer-auto-reply');
    });

    // API routes for messenger
    Route::prefix('api')->group(function () {
        Route::get('/customers/{customer}', [MessengerController::class, 'getCustomer'])->name('api.customer');
    });
});

// Facebook webhook (public route)
Route::get('/facebook/webhook', [FacebookController::class, 'webhook'])->name('facebook.webhook');
Route::post('/facebook/webhook', [FacebookController::class, 'webhook'])->withoutMiddleware([
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
]);

require __DIR__.'/auth.php';
