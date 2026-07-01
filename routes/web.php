<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminBadgeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::view('/login', 'auth-login');
Route::view('/register', 'auth-register');
Route::view('/terms', 'terms');
Route::view('/account', 'auth-account');
Route::view('/account/users', 'auth-account-users');
Route::get('/parent/users/{user}', fn () => view('auth-parent-user-detail'));
Route::view('/account/loans', 'auth-account-loans');
Route::view('/account/domus-points', 'auth-account-points');
Route::view('/parent/domus-points', 'auth-parent-domus-points');
Route::view('/child/domus-points', 'auth-child-domus-points');
Route::view('/levels', 'auth-child-levels');
Route::view('/parent/transfers', 'auth-parent-transfers');
Route::view('/parent/withdrawals', 'auth-parent-withdrawals');
Route::view('/account/education', 'auth-account-education');
Route::get('/account/education/categories/{category}/courses', fn () => view('auth-account-education-courses'));
Route::get('/account/education/courses/{course}', fn () => view('auth-account-education-course'));
Route::view('/parent/loans', 'auth-parent-loans');
Route::view('/parent/allowances', 'auth-parent-allowances');
Route::view('/parent/savings-boxes', 'auth-parent-savings-boxes');
Route::view('/child/savings-boxes', 'auth-child-savings-boxes');
Route::view('/member/savings-boxes', 'auth-child-savings-boxes');
Route::view('/child/goals', 'auth-child-goals');
Route::view('/child/withdrawals', 'auth-child-withdrawals');
Route::view('/member/withdrawals', 'auth-child-withdrawals');
Route::view('/member/goals', 'auth-child-goals');
Route::view('/member/loans', 'auth-member-loans');
Route::view('/parent/tasks', 'auth-parent-tasks');
Route::view('/child/tasks', 'auth-child-tasks');

Route::get('/badges/{badge:slug}/image', [AdminBadgeController::class, 'showImage'])
    ->middleware('throttle:120,1')
    ->name('badges.image');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

Route::prefix('admin')->group(function () {
    Route::middleware('guest:admin_web')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login.store');
    });

    Route::middleware('auth:admin_web')->group(function () {
        Route::get('/dashboard', [AdminBadgeController::class, 'dashboard'])->name('admin.dashboard');
        Route::post('/badges/{badge}/image', [AdminBadgeController::class, 'updateImage'])->name('admin.badges.image.update');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
    });
});
