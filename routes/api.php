<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Splitwise-like Expense Sharing Application API Routes
|
*/

Route::get('/ping', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running successfully.'
    ]);
});
