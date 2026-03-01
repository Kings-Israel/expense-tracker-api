<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\ExpenseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Currency routes
Route::get('/currencies', [CurrencyController::class, 'index']);
Route::post('/currencies/conversion-rate', [CurrencyController::class, 'conversionRate']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Currencies
    Route::put('/currencies/default', [CurrencyController::class, 'updateDefaultCurrency']);

    // Expenses
    Route::post('/expenses/parse', [ExpenseController::class, 'parseAndStore']);
    Route::get('/expenses/summary', [ExpenseController::class, 'summary']);
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::get('/expenses/{id}', [ExpenseController::class, 'show']);
    Route::put('/expenses/{id}', [ExpenseController::class, 'update']);
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);
});
