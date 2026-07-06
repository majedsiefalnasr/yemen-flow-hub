<?php

use App\Http\Controllers\Api\Admin\NotificationTemplateController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\FinancingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportPresetsController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\BankController as V1BankController;
use App\Http\Controllers\Api\V1\ComplianceController;
use App\Http\Controllers\Api\V1\EngineFxConfirmationController;
use App\Http\Controllers\Api\V1\EngineRequestClaimController;
use App\Http\Controllers\Api\V1\EngineRequestController;
use App\Http\Controllers\Api\V1\EngineRequestDocumentController;
use App\Http\Controllers\Api\V1\GovernanceImpactController;
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
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::get('demo-users', [AuthController::class, 'demoUsers'])->middleware('throttle:20,1');
    Route::post('switch-demo-user', [AuthController::class, 'switchDemoUser'])->middleware('throttle:20,1');
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
    Route::get('governance/impact', [GovernanceImpactController::class, 'show']);
    Route::get('banks/{bank}/lifecycle-impact', [GovernanceImpactController::class, 'bank']);
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
    Route::put('workflow-definitions/{workflowDefinition}', [WorkflowDefinitionController::class, 'update']);
    Route::delete('workflow-definitions/{workflowDefinition}', [WorkflowDefinitionController::class, 'destroy']);
    Route::get('workflow-versions/{workflowVersion}', [WorkflowVersionController::class, 'show']);
    Route::put('workflow-versions/{workflowVersion}', [WorkflowVersionController::class, 'update']);
    Route::delete('workflow-versions/{workflowVersion}', [WorkflowVersionController::class, 'destroy']);
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
    Route::get('workflow-stages/{workflowStage}/effective-executors', [WorkflowStageController::class, 'effectiveExecutors']);
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
    Route::post('users/{user}/reset-pin', [V1UserController::class, 'resetPin']);
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
    Route::post('engine-requests/{engineRequest}/abandon', [EngineRequestController::class, 'abandon']);
    Route::get('engine-requests/{engineRequest}/history', [EngineRequestController::class, 'history']);
    Route::get('engine-requests/{engineRequest}/graph', [EngineRequestController::class, 'graph']);
    Route::get('engine-requests/{engineRequest}/documents', [EngineRequestDocumentController::class, 'listDocuments']);
    Route::post('engine-requests/{engineRequest}/documents', [EngineRequestDocumentController::class, 'uploadDocument'])->middleware('throttle:10,1');
    Route::get('engine-requests/{engineRequest}/documents/{document}/download', [EngineRequestDocumentController::class, 'downloadDocument']);
    Route::delete('engine-requests/{engineRequest}/documents/{document}', [EngineRequestDocumentController::class, 'deleteDocument']);
    Route::post('engine-requests/{engineRequest}/fx-confirmation-signed', [EngineFxConfirmationController::class, 'uploadSignedFx'])->middleware('throttle:10,1');
    Route::get('engine-requests/{engineRequest}/customs-declaration/download', [EngineFxConfirmationController::class, 'downloadCustomsDeclaration']);
    Route::get('engine-requests/{engineRequest}/customs-declaration/signed-fx-download', [EngineFxConfirmationController::class, 'downloadSignedFxDoc']);
    Route::post('engine-requests/{engineRequest}/claim', [EngineRequestClaimController::class, 'claim']);
    Route::post('engine-requests/{engineRequest}/claim/heartbeat', [EngineRequestClaimController::class, 'heartbeatClaim']);
    Route::delete('engine-requests/{engineRequest}/claim', [EngineRequestClaimController::class, 'releaseClaim']);

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
    Route::post('profile/mfa/recovery-codes/regenerate', [ProfileController::class, 'regenerateRecoveryCodes']);
    Route::post('profile/mfa/step-up/initiate', [ProfileController::class, 'initiateStepUp']);
    Route::post('profile/mfa/step-up/verify', [ProfileController::class, 'verifyStepUp']);
    Route::get('profile/sessions', [ProfileController::class, 'listSessions']);
    Route::delete('profile/sessions/{tokenId}', [ProfileController::class, 'revokeSession']);
    Route::post('profile/sessions/revoke-all', [ProfileController::class, 'revokeAllSessions']);
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
    Route::get('document-types', [DocumentTypeController::class, 'index']);
    Route::post('document-types', [DocumentTypeController::class, 'store']);
    Route::put('document-types/{documentType}', [DocumentTypeController::class, 'update']);
    Route::delete('document-types/{documentType}', [DocumentTypeController::class, 'destroy']);

    Route::get('financing/utilization', [FinancingController::class, 'utilization']);

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
