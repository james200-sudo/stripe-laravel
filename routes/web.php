<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StripeController;

// ========================================
// PAGE D'ACCUEIL
// ========================================
Route::get('/', function () {
    return view('welcome');
});

