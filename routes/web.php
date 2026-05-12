<?php


use App\Http\Controllers\FacebookController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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
});

// Facebook webhook (public route)
Route::get('/facebook/webhook', [FacebookController::class, 'webhook'])->name('facebook.webhook');
Route::post('/facebook/webhook', [FacebookController::class, 'webhook'])->withoutMiddleware([
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
]);

require __DIR__.'/auth.php';
