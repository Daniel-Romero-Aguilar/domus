<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AllowanceController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\DomusNotificationController;
use App\Http\Controllers\Api\DomusPointsController;
use App\Http\Controllers\Api\ChildGoalController;
use App\Http\Controllers\Api\CashWithdrawalController;
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
    Route::get('/notifications', [DomusNotificationController::class, 'index']);
    Route::get('/domus-levels', [DomusPointsController::class, 'levelsIndex']);
    Route::get('/parent/domus-points', [DomusPointsController::class, 'parentIndex']);
    Route::post('/parent/domus-points/rewards', [DomusPointsController::class, 'parentStoreReward']);
    Route::post('/parent/domus-points/redemptions/{redemption}/pay', [DomusPointsController::class, 'parentMarkRedemptionPaid']);
    Route::get('/child/domus-points', [DomusPointsController::class, 'childIndex']);
    Route::post('/child/domus-points/rewards/{reward}/redeem', [DomusPointsController::class, 'childRedeemReward']);
    Route::get('/child/goals', [ChildGoalController::class, 'index']);
    Route::post('/child/goals', [ChildGoalController::class, 'store']);
    Route::post('/child/goals/{goal}/deposit', [ChildGoalController::class, 'deposit']);
    Route::post('/child/goals/{goal}/withdraw', [ChildGoalController::class, 'withdraw']);
    Route::post('/child/goals/{goal}/complete', [ChildGoalController::class, 'complete']);
    Route::post('/child/goals/{goal}/cancel', [ChildGoalController::class, 'cancel']);
    Route::post('/balance/add', [BalanceController::class, 'add']);
    Route::get('/withdrawals', [CashWithdrawalController::class, 'index']);
    Route::post('/parent/withdrawals', [CashWithdrawalController::class, 'parentStore']);
    Route::post('/child/withdrawals', [CashWithdrawalController::class, 'childStore']);
    Route::post('/withdrawals/{cashWithdrawal}/accept', [CashWithdrawalController::class, 'accept']);
    Route::post('/withdrawals/{cashWithdrawal}/cancel', [CashWithdrawalController::class, 'cancel']);
    Route::get('/transfers', [TransferController::class, 'index']);
    Route::post('/transfers', [TransferController::class, 'store']);
    Route::get('/family-members', [FamilyMemberController::class, 'index']);
    Route::get('/family-members-summary', [FamilyMemberController::class, 'summary']);
    Route::get('/family-members/{user}/summary', [FamilyMemberController::class, 'show']);
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
    Route::post('/loan-payments/{loanPayment}/pay', [LoanController::class, 'pay']);
    Route::get('/education/categories', [EducationController::class, 'categories']);
    Route::get('/education/categories/{category}/courses', [EducationController::class, 'categoryCourses']);
    Route::get('/education/courses/{course}', [EducationController::class, 'showCourse']);
    Route::post('/education/lessons/{lesson}/complete', [EducationController::class, 'completeLesson']);
    Route::post('/education/lesson-parts/{lessonPart}/submit', [EducationController::class, 'submitAssessment']);
    Route::get('/parent/tasks', [TaskController::class, 'parentIndex']);
    Route::post('/parent/tasks', [TaskController::class, 'parentStore']);
    Route::post('/parent/tasks/{task}/review', [TaskController::class, 'parentReview']);
    Route::get('/child/tasks', [TaskController::class, 'childIndex']);
    Route::post('/child/tasks/{task}/accept', [TaskController::class, 'childAccept']);
    Route::post('/tasks/member/completed/{task}', [TaskController::class, 'memberMarkCompleted']);
});
