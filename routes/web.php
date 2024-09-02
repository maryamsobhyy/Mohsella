<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SallaController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return ['Laravel' => app()->version()];
// });
Route::get('/auth',[SallaController::class,'auth'])->name('auth');
Route::get('/auth/callback',[SallaController::class,'callback'])->name('callback');
