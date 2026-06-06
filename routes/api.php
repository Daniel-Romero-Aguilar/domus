<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AllowanceController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\FamilyMemberController;
use App\Http\Controllers\Api\EducationController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\SavingsBoxController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/balance/add', [BalanceController::class, 'add']);
    Route::get('/transfers', [TransferController::class, 'index']);
    Route::post('/transfers', [TransferController::class, 'store']);
    Route::get('/family-members', [FamilyMemberController::class, 'index']);
    Route::post('/family-members', [FamilyMemberController::class, 'store']);
    Route::get('/allowances', [AllowanceController::class, 'index']);
    Route::post('/allowances', [AllowanceController::class, 'store']);
    Route::post('/allowances/{allowance}/execute', [AllowanceController::class, 'execute']);
    Route::get('/savings-boxes', [SavingsBoxController::class, 'index']);
    Route::post('/savings-boxes', [SavingsBoxController::class, 'store']);
    Route::post('/savings-boxes/{savingsBox}/deposit', [SavingsBoxController::class, 'deposit']);
    Route::post('/savings-boxes/{savingsBox}/withdraw', [SavingsBoxController::class, 'withdraw']);
    Route::get('/loans', [LoanController::class, 'index']);
    Route::get('/loans/waiting', [LoanController::class, 'waiting']);
    Route::get('/loans/active-total', [LoanController::class, 'activeTotal']);
    Route::post('/loans', [LoanController::class, 'store']);
    Route::post('/loans/{loan}/approve', [LoanController::class, 'approve']);
    Route::post('/loans/{loan}/respond', [LoanController::class, 'respondToOffer']);
    Route::get('/education/courses', [EducationController::class, 'index']);
    Route::get('/parent/tasks', [TaskController::class, 'parentIndex']);
    Route::post('/parent/tasks', [TaskController::class, 'parentStore']);
    Route::get('/child/tasks', [TaskController::class, 'childIndex']);
    Route::post('/child/tasks/{task}/accept', [TaskController::class, 'childAccept']);
    Route::post('/tasks/member/completed/{task}', [TaskController::class, 'memberMarkCompleted']);
});
