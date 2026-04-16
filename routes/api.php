<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BudgetsAlertController;
use App\Http\Controllers\BudgetsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashbordController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('me', [AuthController::class, 'me']);

    Route::apiResource('users', UserController::class);
    Route::apiResource('accounts', AccountController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::get('dashboard/monthly', [DashbordController::class, 'monthly']);
    Route::get('reports/summary', [ReportController::class, 'summary']);
    Route::get('transactions/dashboard', [TransactionController::class, 'dashboard']);
    Route::get('transactions/monthly-activities', [TransactionController::class, 'monthlyActivities']);
    Route::apiResource('transactions', TransactionController::class);
    Route::apiResource('budgets', BudgetsController::class);
    Route::apiResource('budgets-alerts', BudgetsAlertController::class)
        ->parameters(['budgets-alerts' => 'budgetsAlert']);
    Route::apiResource('notifications', NotificationController::class);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
