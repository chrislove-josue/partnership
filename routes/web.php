<?php

use App\Http\Controllers\MagicLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/magic', MagicLoginController::class)->name('magic.login');
