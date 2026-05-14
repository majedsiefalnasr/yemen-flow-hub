<?php

use App\Http\Controllers\TestApiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return "welcome to CBY Imports Platform API";
});

Route::get('/test-api', [TestApiController::class, 'index'])->name('test_api');
