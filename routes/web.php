<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\RealtimeController;
use App\Livewire\Customer\ModulePage as CustomerModulePage;
use App\Livewire\SuperAdmin\Dashboard as SuperAdminDashboard;
use App\Livewire\SuperAdmin\ModulePage as SuperAdminModulePage;
use App\Livewire\Technician\ModulePage as TechnicianModulePage;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        if (auth()->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if (auth()->user()->isTechnician()) {
            return redirect()->route('technician.module');
        }

        return redirect()->route('customer.module');
    })->name('dashboard');
    Route::get('realtime/{scope}', [RealtimeController::class, 'snapshot'])
        ->where('scope', '[a-z0-9-]+')
        ->name('realtime.snapshot');
    Route::middleware('role:admin')->group(function () {
        Route::get('admin/dashboard', SuperAdminDashboard::class)->name('admin.dashboard');
        Route::get('admin/{module}', SuperAdminModulePage::class)
            ->where('module', '[a-z0-9-]+')
            ->name('admin.module');
    });
    Route::middleware('role:technician')->group(function () {
        Route::get('technician/{module?}', TechnicianModulePage::class)
            ->where('module', '[a-z0-9-]+')
            ->name('technician.module');
    });
    Route::middleware('role:customer')->group(function () {
        Route::get('customer/{module?}', CustomerModulePage::class)
            ->where('module', '[a-z0-9-]+')
            ->name('customer.module');
    });
});

require __DIR__.'/settings.php';
