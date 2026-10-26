<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ClientErrorController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\Teacher\AssignmentController;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('landing');
})->middleware('auth.home')->name('home');

// Escape hatch for admin "view as" previews. Deliberately OUTSIDE the
// student (not.staff) and admin/teacher (role) groups so it is reachable from
// anywhere in a previewed session — including the immersive reader — and can
// hand the real admin back to their console.
Route::middleware('auth')->post('/preview/return', [PreviewController::class, 'stop'])->name('preview.return');

// Shared-assignment link: records the click for the teacher's rollup, then
// hands the student straight to the assigned story's reader. Requires login
// (the teacher dashboard shares these codes while signed in).
Route::middleware('auth')->get('/a/{code}', [AssignmentController::class, 'openShare'])->name('assignment.share');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
    Route::post('/login/restore', [AuthController::class, 'restoreScheduled'])->middleware('throttle:deletion-restore')->name('login.restore');
    Route::get('/login/restore', fn () => redirect()->route('login'));
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register')->name('register.submit');

    Route::get('/forgot', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::post('/client-error', [ClientErrorController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('client-error.store');

Route::get('/about', function () {
    return view('footer.about-us');
})->name('about');

Route::get('/contact', function () {
    return view('footer.contact-us');
})->name('contact');

Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,10')
    ->name('contact.submit');

Route::get('/terms', function () {
    return view('footer.terms-conditions');
})->name('terms');

