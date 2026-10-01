<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\SettlementController;

/*
|--------------------------------------------------------------------------
| Splitwise-Like Expense Sharing API Routes
|--------------------------------------------------------------------------
*/

// Public Authentication Endpoints
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected Endpoints (Bearer Random Token via custom auth.token middleware)
Route::middleware('auth.token')->group(function () {
    // Auth & User Profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Groups & Members
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::get('/groups/{group}', [GroupController::class, 'show']);
    Route::get('/groups/{group}/members', [GroupController::class, 'members']);
    Route::post('/groups/{group}/members', [GroupController::class, 'addMember']);
    Route::delete('/groups/{group}/members/{user}', [GroupController::class, 'removeMember']);

    // Group Expenses
    Route::get('/groups/{group}/expenses', [ExpenseController::class, 'index']);
    Route::post('/groups/{group}/expenses', [ExpenseController::class, 'store']);

    // Individual Expense Details & Operations
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show']);
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);
    Route::patch('/expenses/{expense}', [ExpenseController::class, 'update']);
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);

    // Group Balances & Debt Simplification
    Route::get('/groups/{group}/balances', [SettlementController::class, 'balances']);

    // Group Settlements
    Route::get('/groups/{group}/settlements', [SettlementController::class, 'index']);
    Route::post('/groups/{group}/settlements', [SettlementController::class, 'store']);
});
