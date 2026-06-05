<?php

use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\CustomsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentTemplateController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\ImportRequestController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportPresetsController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VotingController;
use App\Http\Controllers\Api\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('login-pin', [AuthController::class, 'loginWithPin'])->middleware('throttle:10,1');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
    Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('password/verify', [AuthController::class, 'verifyPasswordResetOtp'])->middleware('throttle:10,1');
    Route::post('password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('switch-demo-role', [AuthController::class, 'switchDemoRole'])->middleware('throttle:20,1');
    });
});

Route::get('settings/public', [SettingsController::class, 'publicSettings']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::post('profile/pin', [ProfileController::class, 'setPin'])->middleware('throttle:10,1');
    Route::delete('profile/pin', [ProfileController::class, 'disablePin'])->middleware('throttle:10,1');
    Route::post('profile/mfa/toggle', [ProfileController::class, 'toggleMfa']);
    Route::post('profile/mfa/setup', [ProfileController::class, 'setupTotp']);
    Route::post('profile/mfa/setup/verify', [ProfileController::class, 'verifyTotpSetup']);
    Route::post('profile/mfa/disable', [ProfileController::class, 'disableTotp']);
    Route::post('profile/mfa/disable-with-password', [ProfileController::class, 'disableTotpWithPassword']);
    Route::post('profile/change-password', [ProfileController::class, 'changePassword'])->middleware('throttle:3,60');
    Route::post('profile/change-temporary-password', [ProfileController::class, 'changeTemporaryPassword'])->middleware('throttle:5,1');
    Route::get('settings', [SettingsController::class, 'show']);
    Route::put('settings', [SettingsController::class, 'update']);
    Route::post('settings/reset', [SettingsController::class, 'reset']);
    Route::post('settings/save-section', [SettingsController::class, 'saveSection']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('banks', BankController::class);
    Route::post('banks/{bank}/admin/reset-password', [BankController::class, 'resetAdminPassword'])->middleware('throttle:10,1');
    Route::apiResource('users', UserController::class);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('throttle:10,1');
    Route::post('users/{user}/reset-mfa', [UserController::class, 'resetMfa'])->middleware('throttle:10,1');
    Route::post('users/{user}/reset-pin', [UserController::class, 'resetPin'])->middleware('throttle:10,1');
    Route::apiResource('merchants', MerchantController::class);
    Route::get('document-types', [DocumentTypeController::class, 'index']);
    Route::post('document-types', [DocumentTypeController::class, 'store']);
    Route::put('document-types/{documentType}', [DocumentTypeController::class, 'update']);
    Route::delete('document-types/{documentType}', [DocumentTypeController::class, 'destroy']);

    Route::get('requests', [ImportRequestController::class, 'index']);
    Route::post('requests', [ImportRequestController::class, 'store']);
    Route::get('requests/{importRequest}', [ImportRequestController::class, 'show']);
    Route::put('requests/{importRequest}', [ImportRequestController::class, 'update']);
    Route::delete('requests/{importRequest}', [ImportRequestController::class, 'destroy']);
    Route::get('requests/{importRequest}/history', [ImportRequestController::class, 'history']);
    Route::post('requests/{importRequest}/clone', [ImportRequestController::class, 'clone']);
    Route::get('requests/{importRequest}/customs-preview', [CustomsController::class, 'preview']);
    Route::get('requests/{importRequest}/confirmation-request-template', [DocumentTemplateController::class, 'confirmationRequest']);
    Route::get('requests/{importRequest}/confirmation-request-preview', [DocumentTemplateController::class, 'confirmationRequestPreview']);
    Route::get('requests/{importRequest}/fx-confirmation-template', [DocumentTemplateController::class, 'fxConfirmation']);
    Route::post('requests/{importRequest}/fx-confirmation-upload', [CustomsController::class, 'uploadSignedFx']);
    Route::post('documents/upload', [DocumentController::class, 'upload']);
    // @deprecated — use POST /api/documents/upload; kept for backward compat during Epic 2 stabilization
    Route::post('requests/{importRequest}/documents', [DocumentController::class, 'uploadRequestDocument']);
    Route::delete('documents/{document}', [DocumentController::class, 'destroy']);
    Route::get('documents/{document}/download', [DocumentController::class, 'download']);

    Route::post('workflow/{importRequest}/submit', [WorkflowController::class, 'submit'])->name('workflow.submit');
    Route::post('workflow/{importRequest}/bank-review', [WorkflowController::class, 'bankBeginReview'])->name('workflow.bank-review');
    Route::post('workflow/{importRequest}/claim-bank-review', [WorkflowController::class, 'claimBankReview'])->name('workflow.claim-bank-review');
    Route::delete('workflow/{importRequest}/claim-bank-review', [WorkflowController::class, 'bankClaimRelease'])->name('workflow.bank-claim-release');
    Route::post('workflow/{importRequest}/claim-bank-review/heartbeat', [WorkflowController::class, 'bankClaimHeartbeat'])->name('workflow.bank-claim-heartbeat');
    Route::post('workflow/{importRequest}/bank-approve', [WorkflowController::class, 'bankApprove'])->name('workflow.bank-approve');
    Route::post('workflow/{importRequest}/bank-reject', [WorkflowController::class, 'bankReject'])->name('workflow.bank-reject');
    Route::post('workflow/{importRequest}/return-to-entry', [WorkflowController::class, 'returnToEntry'])->name('workflow.return-to-entry');
    Route::post('workflow/{importRequest}/claim-support-review', [WorkflowController::class, 'claimSupportReview'])->name('workflow.claim-support-review');
    Route::delete('workflow/{importRequest}/claim-support-review', [WorkflowController::class, 'claimRelease'])->name('workflow.claim-release');
    Route::post('workflow/{importRequest}/claim-support-review/heartbeat', [WorkflowController::class, 'claimHeartbeat'])->name('workflow.claim-heartbeat');
    Route::post('workflow/{importRequest}/support-claim', [WorkflowController::class, 'supportClaim'])->name('workflow.support-claim');
    Route::post('workflow/{importRequest}/support-release', [WorkflowController::class, 'supportRelease'])->name('workflow.support-release');
    Route::post('workflow/{importRequest}/support-approve', [WorkflowController::class, 'supportApprove'])->name('workflow.support-approve');
    Route::post('workflow/{importRequest}/support-reject', [WorkflowController::class, 'supportReject'])->name('workflow.support-reject');
    Route::post('workflow/{importRequest}/bank-return-after-support-reject', [WorkflowController::class, 'bankReturnAfterSupportReject'])->name('workflow.bank-return-after-support-reject');
    Route::post('workflow/{importRequest}/bank-finalize-rejection', [WorkflowController::class, 'bankFinalizeRejection'])->name('workflow.bank-finalize-rejection');
    Route::post('workflow/{importRequest}/bank-reject-terminal', [WorkflowController::class, 'bankRejectTerminal'])->name('workflow.bank-reject-terminal');
    Route::post('workflow/{importRequest}/bank-return', [WorkflowController::class, 'bankReturn'])->name('workflow.bank-return');
    Route::post('workflow/{importRequest}/support-return', [WorkflowController::class, 'supportReturn'])->name('workflow.support-return');
    Route::post('workflow/{importRequest}/swift-upload', [DocumentController::class, 'uploadSwift']);
    Route::post('workflow/{importRequest}/finalize-decision', [WorkflowController::class, 'finalizeDecision'])->name('workflow.finalize-decision');

    Route::get('voting', [VotingController::class, 'index']);
    Route::get('voting/{importRequest}', [VotingController::class, 'show']);
    Route::post('voting/{importRequest}/open', [VotingController::class, 'openSession'])->name('voting.open');
    Route::post('voting/{importRequest}/close', [VotingController::class, 'closeSession'])->name('voting.close');
    Route::post('voting/{importRequest}/vote', [VotingController::class, 'vote']);
    Route::post('voting/{importRequest}/director-decide', [VotingController::class, 'directorDecide']);
    Route::post('voting/{importRequest}/override', [VotingController::class, 'override']);

    Route::post('customs/{importRequest}/generate', [CustomsController::class, 'generate']);
    Route::get('customs/{customsDeclaration}', [CustomsController::class, 'show']);
    Route::get('customs/{customsDeclaration}/download', [CustomsController::class, 'download']);
    Route::get('customs/{customsDeclaration}/signed-fx-download', [CustomsController::class, 'downloadSignedFx']);

    Route::get('audit', [AuditController::class, 'index']);
    Route::get('audit/stats', [AuditController::class, 'stats']);
    Route::get('audit/duplicates', [AuditController::class, 'duplicates']);
    Route::get('audit/risk-indicators', [AuditController::class, 'riskIndicators']);

    Route::get('admin/settings', [AdminSettingsController::class, 'index']);
    Route::get('admin/settings/smtp', [AdminSettingsController::class, 'getSmtp']);
    Route::put('admin/settings/smtp', [AdminSettingsController::class, 'updateSmtp']);
    Route::post('admin/settings/email/test', [AdminSettingsController::class, 'testEmail']);
    Route::put('admin/settings/{key}', [AdminSettingsController::class, 'update'])->middleware('throttle:10,60');
    Route::post('admin/settings/{key}/reset', [AdminSettingsController::class, 'reset'])->middleware('throttle:10,60');

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);

    Route::get('search/recent', [SearchController::class, 'recent']);
    Route::get('search', [SearchController::class, 'search']);

    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('reports/workflow', [ReportController::class, 'workflow']);
    Route::get('reports/voting', [ReportController::class, 'voting']);
    Route::get('reports/bank', [ReportController::class, 'bank']);
    Route::get('reports/workflow/export', [ReportController::class, 'exportWorkflow']);
    Route::get('reports/bank/export', [ReportController::class, 'exportBank']);
    Route::get('report-presets', [ReportPresetsController::class, 'index']);
    Route::post('report-presets', [ReportPresetsController::class, 'store']);
    Route::delete('report-presets/{id}', [ReportPresetsController::class, 'destroy']);
});
