<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Client;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Webhook\YooKassaWebhookController;
use Illuminate\Support\Facades\Route;

// Guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [Auth\LoginController::class, 'create'])->name('login');
    Route::post('/login', [Auth\LoginController::class, 'store']);
});

// Authenticated
Route::middleware('auth')->group(function () {
    Route::post('/logout', [Auth\LogoutController::class, 'destroy'])->name('logout');
    Route::put('/password', [Auth\PasswordController::class, 'update'])->name('password.update');

    // Files
    Route::middleware('is_active')->group(function () {
        Route::post('/files/upload', [FileController::class, 'upload'])->name('files.upload');
        Route::get('/files/{file}/download', [FileController::class, 'download'])->name('files.download');
    });

    // Admin routes
    Route::middleware(['is_active', 'is_admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Clients
        Route::get('/clients', [Admin\ClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/create', [Admin\ClientController::class, 'create'])->name('clients.create');
        Route::post('/clients', [Admin\ClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}', [Admin\ClientController::class, 'show'])->name('clients.show');
        Route::get('/clients/{client}/edit', [Admin\ClientController::class, 'edit'])->name('clients.edit');
        Route::put('/clients/{client}', [Admin\ClientController::class, 'update'])->name('clients.update');
        Route::patch('/clients/{client}/deactivate', [Admin\ClientController::class, 'deactivate'])->name('clients.deactivate');

        // Tasks
        Route::get('/tasks', [Admin\TaskController::class, 'index'])->name('tasks.index');
        Route::get('/tasks/create', [Admin\TaskController::class, 'create'])->name('tasks.create');
        Route::post('/tasks', [Admin\TaskController::class, 'store'])->name('tasks.store');
        Route::get('/tasks/{task}', [Admin\TaskController::class, 'show'])->name('tasks.show');
        Route::get('/tasks/{task}/edit', [Admin\TaskController::class, 'edit'])->name('tasks.edit');
        Route::put('/tasks/{task}', [Admin\TaskController::class, 'update'])->name('tasks.update');
        Route::patch('/tasks/{task}/status', [Admin\TaskController::class, 'updateStatus'])->name('tasks.status');
        Route::post('/tasks/{task}/comments', [Admin\TaskCommentController::class, 'store'])->name('tasks.comments.store');
        Route::post('/tasks/{task}/reports', [Admin\TaskReportController::class, 'store'])->name('tasks.reports.store');

        // Invoices
        Route::get('/invoices', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/create', [Admin\InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [Admin\InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/invoices/{invoice}', [Admin\InvoiceController::class, 'show'])->name('invoices.show');
        Route::patch('/invoices/{invoice}/cancel', [Admin\InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('/invoices/{invoice}/refund', [Admin\InvoiceController::class, 'refund'])->name('invoices.refund');

        // Settings
        Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    });

    // Client routes
    Route::middleware('is_active')->group(function () {
        Route::get('/dashboard', [Client\DashboardController::class, 'index'])->name('client.dashboard');

        Route::get('/tasks', [Client\TaskController::class, 'index'])->name('client.tasks.index');
        Route::get('/tasks/create', [Client\TaskController::class, 'create'])->name('client.tasks.create');
        Route::post('/tasks', [Client\TaskController::class, 'store'])->name('client.tasks.store');
        Route::get('/tasks/{task}', [Client\TaskController::class, 'show'])->name('client.tasks.show');
        Route::patch('/tasks/{task}/review', [Client\TaskController::class, 'review'])->name('client.tasks.review');
        Route::post('/tasks/{task}/comments', [Client\TaskCommentController::class, 'store'])->name('client.tasks.comments.store');

        Route::get('/invoices', [Client\InvoiceController::class, 'index'])->name('client.invoices.index');
        Route::get('/invoices/{invoice}', [Client\InvoiceController::class, 'show'])->name('client.invoices.show');
        Route::get('/invoices/{invoice}/pay', [Client\InvoiceController::class, 'pay'])->name('client.invoices.pay');

        Route::get('/profile', [Client\ProfileController::class, 'edit'])->name('client.profile.edit');
        Route::put('/profile', [Client\ProfileController::class, 'update'])->name('client.profile.update');
    });
});

// Webhook (no auth, verify middleware)
Route::post('/webhooks/yookassa', [YooKassaWebhookController::class, 'handle'])
    ->middleware('yookassa_webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.yookassa');

// Redirect root
Route::get('/', fn () => redirect()->route('login'));
