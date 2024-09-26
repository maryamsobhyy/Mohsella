<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SallaController;
use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
//salla api.php
Route::prefix('salla')->group(function () {
    Route::get('/auth', [SallaController::class, 'auth'])->name('salla.auth');
    Route::post('/callback', [SallaController::class, 'callback'])->name('salla.callback');
});
// Google api.php
    Route::get('/auth/google', [SocialiteController::class, 'redirectToGoogle']);
    Route::get('/auth/google/callback', [SocialiteController::class, 'handleGoogleCallback']);



require __DIR__ . '/auth.php';
