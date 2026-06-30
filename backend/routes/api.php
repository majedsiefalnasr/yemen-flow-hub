<?php

use App\Http\Controllers\Api\Admin\NotificationTemplateController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\CustomsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentTemplateController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\FinancingController;
use App\Http\Controllers\Api\ImportRequestController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportPresetsController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TraderController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\BankController as V1BankController;
use App\Http\Controllers\Api\V1\ComplianceController;
use App\Http\Controllers\Api\V1\EngineRequestController;
use App\Http\Controllers\Api\V1\FieldDefinitionController;
use App\Http\Controllers\Api\V1\FieldGroupController;
use App\Http\Controllers\Api\V1\MerchantController as V1MerchantController;
use App\Http\Controllers\Api\V1\NotificationInboxController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ReferenceTableController;
use App\Http\Controllers\Api\V1\ReferenceValueController;
use App\Http\Controllers\Api\V1\ReportController as V1ReportController;
use App\Http\Controllers\Api\V1\ReportExportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\RoleScreenPermissionController;
use App\Http\Controllers\Api\V1\ScreenController;
use App\Http\Controllers\Api\V1\StageFieldRuleController;
use App\Http\Controllers\Api\V1\StagePermissionController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\UserController as V1UserController;
use App\Http\Controllers\Api\V1\WorkflowActionController;
use App\Http\Controllers\Api\V1\WorkflowDefinitionController;
use App\Http\Controllers\Api\V1\WorkflowStageController;
use App\Http\Controllers\Api\V1\WorkflowTransitionController;
use App\Http\Controllers\Api\V1\WorkflowVersionController;
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
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('me/permissions', [AuthController::class, 'permissions']);
        Route::post('switch-demo-role', [AuthController::class, 'switchDemoRole'])->middleware('throttle:20,1');
    });
});

Route::prefix('v1')->middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('organizations', [OrganizationController::class, 'index']);
    Route::post('organizations', [OrganizationController::class, 'store']);
    Route::get('organizations/{organization}', [OrganizationController::class, 'show']);
    Route::put('organizations/{organization}', [OrganizationController::class, 'update']);
    Route::post('organizations/{organization}/activate', [OrganizationController::class, 'activate']);
    Route::post('organizations/{organization}/deactivate', [OrganizationController::class, 'deactivate']);
    Route::delete('organizations/{organization}', [OrganizationController::class, 'destroy']);
    Route::get('teams', [TeamController::class, 'index']);
    Route::post('teams', [TeamController::class, 'store']);
    Route::get('teams/{team}', [TeamController::class, 'show']);
    Route::put('teams/{team}', [TeamController::class, 'update']);
    Route::post('teams/{team}/activate', [TeamController::class, 'activate']);
    Route::post('teams/{team}/deactivate', [TeamController::class, 'deactivate']);
    Route::delete('teams/{team}', [TeamController::class, 'destroy']);
    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::get('roles/{role}', [RoleController::class, 'show']);
    Route::put('roles/{role}', [RoleController::class, 'update']);
    Route::post('roles/{role}/activate', [RoleController::class, 'activate']);
    Route::post('roles/{role}/deactivate', [RoleController::class, 'deactivate']);
    Route::delete('roles/{role}', [RoleController::class, 'destroy']);
    Route::get('screens', [ScreenController::class, 'index']);
    Route::get('screen-permissions/matrix', [RoleScreenPermissionController::class, 'matrix']);
    Route::get('roles/{role}/screen-permissions', [RoleScreenPermissionController::class, 'show']);
    Route::put('roles/{role}/screen-permissions', [RoleScreenPermissionController::class, 'update']);
    Route::get('reference-tables', [ReferenceTableController::class, 'index']);
    Route::post('reference-tables', [ReferenceTableController::class, 'store']);
    Route::get('reference-tables/{reference_table}', [ReferenceTableController::class, 'show']);
    Route::put('reference-tables/{reference_table}', [ReferenceTableController::class, 'update']);
    Route::post('reference-tables/{reference_table}/activate', [ReferenceTableController::class, 'activate']);
    Route::post('reference-tables/{reference_table}/deactivate', [ReferenceTableController::class, 'deactivate']);
    Route::delete('reference-tables/{reference_table}', [ReferenceTableController::class, 'destroy']);
    Route::get('reference-values', [ReferenceValueController::class, 'index']);
    Route::post('reference-values', [ReferenceValueController::class, 'store']);
    Route::get('reference-values/{reference_value}', [ReferenceValueController::class, 'show']);
    Route::put('reference-values/{reference_value}', [ReferenceValueController::class, 'update']);
    Route::post('reference-values/{reference_value}/activate', [ReferenceValueController::class, 'activate']);
    Route::post('reference-values/{reference_value}/deactivate', [ReferenceValueController::class, 'deactivate']);
    Route::delete('reference-values/{reference_value}', [ReferenceValueController::class, 'destroy']);
    Route::get('workflow-definitions', [WorkflowDefinitionController::class, 'index']);
    Route::post('workflow-definitions', [WorkflowDefinitionController::class, 'store']);
    Route::get('workflow-definitions/{workflowDefinition}', [WorkflowDefinitionController::class, 'show']);
    Route::get('workflow-versions/{workflowVersion}', [WorkflowVersionController::class, 'show']);
    Route::put('workflow-versions/{workflowVersion}', [WorkflowVersionController::class, 'update']);
    Route::post('workflow-versions/{workflowVersion}/clone', [WorkflowVersionController::class, 'clone']);
    Route::post('workflow-versions/{workflowVersion}/validate', [WorkflowVersionController::class, 'validate']);
    Route::post('workflow-versions/{workflowVersion}/publish', [WorkflowVersionController::class, 'publish']);
    Route::post('workflow-versions/{workflowVersion}/archive', [WorkflowVersionController::class, 'archive']);
    Route::get('workflow-versions/{workflowVersion}/graph', [WorkflowVersionController::class, 'graph']);
    Route::get('workflow-versions/{workflowVersion}/stages', [WorkflowStageController::class, 'index']);
    Route::post('workflow-versions/{workflowVersion}/stages', [WorkflowStageController::class, 'store']);
    Route::get('workflow-versions/{workflowVersion}/stages/{workflowStage}', [WorkflowStageController::class, 'show']);
    Route::put('workflow-versions/{workflowVersion}/stages/{workflowStage}', [WorkflowStageController::class, 'update']);
    Route::delete('workflow-versions/{workflowVersion}/stages/{workflowStage}', [WorkflowStageController::class, 'destroy']);
    Route::get('workflow-actions', [WorkflowActionController::class, 'index']);
    Route::post('workflow-actions', [WorkflowActionController::class, 'store']);
    Route::get('workflow-actions/{workflowAction}', [WorkflowActionController::class, 'show']);
    Route::put('workflow-actions/{workflowAction}', [WorkflowActionController::class, 'update']);
    Route::post('workflow-actions/{workflowAction}/activate', [WorkflowActionController::class, 'activate']);
    Route::post('workflow-actions/{workflowAction}/deactivate', [WorkflowActionController::class, 'deactivate']);
    Route::delete('workflow-actions/{workflowAction}', [WorkflowActionController::class, 'destroy']);
    Route::get('workflow-versions/{workflowVersion}/transitions', [WorkflowTransitionController::class, 'index']);
    Route::post('workflow-versions/{workflowVersion}/transitions', [WorkflowTransitionController::class, 'store']);
    Route::get('workflow-versions/{workflowVersion}/transitions/{workflowTransition}', [WorkflowTransitionController::class, 'show']);
    Route::put('workflow-versions/{workflowVersion}/transitions/{workflowTransition}', [WorkflowTransitionController::class, 'update']);
    Route::delete('workflow-versions/{workflowVersion}/transitions/{workflowTransition}', [WorkflowTransitionController::class, 'destroy']);
    Route::get('workflow-stages/{workflowStage}/permissions', [StagePermissionController::class, 'index']);
    Route::post('workflow-stages/{workflowStage}/permissions', [StagePermissionController::class, 'store']);
    Route::get('workflow-stages/{workflowStage}/permissions/{stagePermission}', [StagePermissionController::class, 'show']);
    Route::put('workflow-stages/{workflowStage}/permissions/{stagePermission}', [StagePermissionController::class, 'update']);
    Route::delete('workflow-stages/{workflowStage}/permissions/{stagePermission}', [StagePermissionController::class, 'destroy']);
    Route::get('workflow-versions/{workflowVersion}/field-groups', [FieldGroupController::class, 'index']);
    Route::post('workflow-versions/{workflowVersion}/field-groups', [FieldGroupController::class, 'store']);
    Route::put('workflow-versions/{workflowVersion}/field-groups/{fieldGroup}', [FieldGroupController::class, 'update']);
    Route::delete('workflow-versions/{workflowVersion}/field-groups/{fieldGroup}', [FieldGroupController::class, 'destroy']);
    Route::get('workflow-versions/{workflowVersion}/fields', [FieldDefinitionController::class, 'index']);
    Route::post('workflow-versions/{workflowVersion}/fields', [FieldDefinitionController::class, 'store']);
    Route::put('workflow-versions/{workflowVersion}/fields/{fieldDefinition}', [FieldDefinitionController::class, 'update']);
    Route::delete('workflow-versions/{workflowVersion}/fields/{fieldDefinition}', [FieldDefinitionController::class, 'destroy']);
    Route::get('workflow-versions/{workflowVersion}/fields/{fieldDefinition}/options', [FieldDefinitionController::class, 'options']);
    Route::get('workflow-stages/{workflowStage}/field-rules', [StageFieldRuleController::class, 'index']);
    Route::post('workflow-stages/{workflowStage}/field-rules', [StageFieldRuleController::class, 'store']);
    Route::delete('workflow-stages/{workflowStage}/field-rules/{stageFieldRule}', [StageFieldRuleController::class, 'destroy']);
    Route::get('banks', [V1BankController::class, 'index']);
    Route::post('banks', [V1BankController::class, 'store']);
    Route::get('banks/{bank}', [V1BankController::class, 'show']);
    Route::put('banks/{bank}', [V1BankController::class, 'update']);
    Route::post('banks/{bank}/activate', [V1BankController::class, 'activate']);
    Route::post('banks/{bank}/deactivate', [V1BankController::class, 'deactivate']);
    Route::delete('banks/{bank}', [V1BankController::class, 'destroy']);
    Route::get('users', [V1UserController::class, 'index']);
    Route::post('users', [V1UserController::class, 'store']);
    Route::get('users/{user}', [V1UserController::class, 'show']);
    Route::put('users/{user}', [V1UserController::class, 'update']);
    Route::post('users/{user}/deactivate', [V1UserController::class, 'deactivate']);
    Route::post('users/{user}/reset-password', [V1UserController::class, 'resetPassword']);
    Route::post('users/{user}/reset-mfa', [V1UserController::class, 'resetMfa']);
    Route::apiResource('merchants', V1MerchantController::class);

    // ─── Engine Requests (Epic 18.5) ─────────────────────────────────────
    Route::get('engine-requests', [EngineRequestController::class, 'index']);
    Route::get('engine-requests/my-queue', [EngineRequestController::class, 'myQueue']);
    Route::post('engine-requests', [EngineRequestController::class, 'store']);
    Route::get('engine-requests/available-workflows', [EngineRequestController::class, 'availableWorkflows']);
    Route::get('engine-requests/{engineRequest}', [EngineRequestController::class, 'show']);
    Route::get('engine-requests/{engineRequest}/form-schema', [EngineRequestController::class, 'formSchema']);
    Route::post('engine-requests/{engineRequest}/actions', [EngineRequestController::class, 'executeAction']);
    Route::patch('engine-requests/{engineRequest}/draft', [EngineRequestController::class, 'draft']);
    Route::get('engine-requests/{engineRequest}/history', [EngineRequestController::class, 'history']);
    Route::get('engine-requests/{engineRequest}/graph', [EngineRequestController::class, 'graph']);
    Route::get('engine-requests/{engineRequest}/documents', [EngineRequestController::class, 'listDocuments']);
    Route::post('engine-requests/{engineRequest}/documents', [EngineRequestController::class, 'uploadDocument'])->middleware('throttle:10,1');
    Route::get('engine-requests/{engineRequest}/documents/{document}/download', [EngineRequestController::class, 'downloadDocument']);
    Route::delete('engine-requests/{engineRequest}/documents/{document}', [EngineRequestController::class, 'deleteDocument']);
    Route::post('engine-requests/{engineRequest}/claim', [EngineRequestController::class, 'claim']);
    Route::post('engine-requests/{engineRequest}/claim/heartbeat', [EngineRequestController::class, 'heartbeatClaim']);
    Route::delete('engine-requests/{engineRequest}/claim', [EngineRequestController::class, 'releaseClaim']);

    // ─── Audit Logs (Epic 18.6) ─────────────────────────────────────────
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/export', [AuditLogController::class, 'export']);
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show']);

    // ─── Compliance (Epic 18.6) ─────────────────────────────────────────
    Route::get('compliance/duplicate-invoices', [ComplianceController::class, 'duplicateInvoices']);
    Route::get('compliance/expired-documents', [ComplianceController::class, 'expiredDocuments']);
    Route::get('compliance/sla-breaches', [ComplianceController::class, 'slaBreaches']);

    // ─── Reports (Epic 18.6) ────────────────────────────────────────────
    Route::get('reports/summary', [V1ReportController::class, 'summary']);
    Route::get('reports/requests-over-time', [V1ReportController::class, 'requestsOverTime']);
    Route::get('reports/by-workflow-stage', [V1ReportController::class, 'byWorkflowStage']);
    Route::get('reports/by-bank', [V1ReportController::class, 'byBank']);
    Route::get('reports/by-merchant', [V1ReportController::class, 'byMerchant']);
    Route::get('reports/by-sector', [V1ReportController::class, 'bySector']);
    Route::get('reports/by-currency', [V1ReportController::class, 'byCurrency']);
    Route::get('reports/stage-duration', [V1ReportController::class, 'stageDuration']);
    Route::get('reports/sla', [V1ReportController::class, 'sla']);
    Route::get('reports/team-performance', [V1ReportController::class, 'teamPerformance']);

    // ─── Report Exports (Epic 18.6) ─────────────────────────────────────
    Route::get('reports/exports', [ReportExportController::class, 'index']);
    Route::post('reports/exports', [ReportExportController::class, 'store']);
    Route::get('reports/exports/{reportExport}', [ReportExportController::class, 'show']);
    Route::get('reports/exports/{reportExport}/download', [ReportExportController::class, 'download']);

    // ─── Notification Inbox (Epic 18.7) ─────────────────────────────────
    Route::get('notifications', [NotificationInboxController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationInboxController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationInboxController::class, 'readAll']);
    Route::post('notifications/{id}/read', [NotificationInboxController::class, 'read']);
    Route::post('notifications/{id}/unread', [NotificationInboxController::class, 'unread']);
    Route::post('notifications/{id}/archive', [NotificationInboxController::class, 'archive']);
});

Route::get('settings/public', [SettingsController::class, 'publicSettings']);

Route::middleware(['auth:sanctum', 'active'])->group(function () {
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

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::apiResource('banks', BankController::class);
    Route::post('banks/{bank}/admin/reset-password', [BankController::class, 'resetAdminPassword'])->middleware('throttle:10,1');
    Route::apiResource('users', UserController::class);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('throttle:10,1');
    Route::post('users/{user}/reset-mfa', [UserController::class, 'resetMfa'])->middleware('throttle:10,1');
    Route::post('users/{user}/reset-pin', [UserController::class, 'resetPin'])->middleware('throttle:10,1');
    Route::get('traders/lookup', [TraderController::class, 'lookup']);
    Route::get('traders', [TraderController::class, 'index']);
    Route::post('traders', [TraderController::class, 'store']);
    Route::get('traders/{trader}', [TraderController::class, 'show']);
    Route::put('traders/{trader}', [TraderController::class, 'update']);
    Route::patch('traders/{trader}', [TraderController::class, 'update']);
    Route::get('document-types', [DocumentTypeController::class, 'index']);
    Route::post('document-types', [DocumentTypeController::class, 'store']);
    Route::put('document-types/{documentType}', [DocumentTypeController::class, 'update']);
    Route::delete('document-types/{documentType}', [DocumentTypeController::class, 'destroy']);

    Route::get('financing/utilization', [FinancingController::class, 'utilization']);

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
    Route::post('documents/upload', [DocumentController::class, 'upload'])->middleware('throttle:10,1');
    // @deprecated — use POST /api/documents/upload; kept for backward compat during Epic 2 stabilization
    Route::post('requests/{importRequest}/documents', [DocumentController::class, 'uploadRequestDocument'])->middleware('throttle:10,1');
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
    Route::post('workflow/{importRequest}/support-forward-to-executive', [WorkflowController::class, 'supportForwardToExecutive'])->name('workflow.support-forward-to-executive');
    Route::post('workflow/{importRequest}/bank-return-after-support-reject', [WorkflowController::class, 'bankReturnAfterSupportReject'])->name('workflow.bank-return-after-support-reject');
    Route::post('workflow/{importRequest}/bank-finalize-rejection', [WorkflowController::class, 'bankFinalizeRejection'])->name('workflow.bank-finalize-rejection');
    Route::post('workflow/{importRequest}/bank-reject-terminal', [WorkflowController::class, 'bankRejectTerminal'])->name('workflow.bank-reject-terminal');
    Route::post('workflow/{importRequest}/bank-return', [WorkflowController::class, 'bankReturn'])->name('workflow.bank-return');
    Route::post('workflow/{importRequest}/support-return', [WorkflowController::class, 'supportReturn'])->name('workflow.support-return');
    Route::post('workflow/{importRequest}/swift-upload', [DocumentController::class, 'uploadSwift'])->middleware('throttle:10,1');
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
    Route::post('admin/settings/email/test', [AdminSettingsController::class, 'testEmail'])->middleware('throttle:5,1');
    Route::put('admin/settings/{key}', [AdminSettingsController::class, 'update'])->middleware('throttle:10,60');
    Route::post('admin/settings/{key}/reset', [AdminSettingsController::class, 'reset'])->middleware('throttle:10,60');
    Route::get('admin/notification-templates', [NotificationTemplateController::class, 'index']);
    Route::get('admin/notification-templates/{type}', [NotificationTemplateController::class, 'show']);
    Route::put('admin/notification-templates/{type}', [NotificationTemplateController::class, 'update'])->middleware('throttle:10,60');
    Route::post('admin/notification-templates/{type}/preview', [NotificationTemplateController::class, 'preview'])->middleware('throttle:30,60');

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
