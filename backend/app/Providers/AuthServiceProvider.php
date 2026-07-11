<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\FieldGroup;
use App\Models\Merchant;
use App\Models\Organization;
use App\Models\ReferenceTable;
use App\Models\ReferenceValue;
use App\Models\Role;
use App\Models\StageFieldRule;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Policies\AuditLogPolicy;
use App\Policies\BankPolicy;
use App\Policies\CustomsDeclarationPolicy;
use App\Policies\EngineRequestPolicy;
use App\Policies\FieldDefinitionPolicy;
use App\Policies\FieldGroupPolicy;
use App\Policies\MerchantPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ReferenceTablePolicy;
use App\Policies\ReferenceValuePolicy;
use App\Policies\RolePolicy;
use App\Policies\StageFieldRulePolicy;
use App\Policies\StagePermissionPolicy;
use App\Policies\TeamPolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkflowActionPolicy;
use App\Policies\WorkflowDefinitionPolicy;
use App\Policies\WorkflowStagePolicy;
use App\Policies\WorkflowTransitionPolicy;
use App\Policies\WorkflowVersionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Bank::class, BankPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Merchant::class, MerchantPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(CustomsDeclaration::class, CustomsDeclarationPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(ReferenceTable::class, ReferenceTablePolicy::class);
        Gate::policy(ReferenceValue::class, ReferenceValuePolicy::class);
        Gate::policy(WorkflowDefinition::class, WorkflowDefinitionPolicy::class);
        Gate::policy(WorkflowVersion::class, WorkflowVersionPolicy::class);
        Gate::policy(WorkflowStage::class, WorkflowStagePolicy::class);
        Gate::policy(WorkflowAction::class, WorkflowActionPolicy::class);
        Gate::policy(WorkflowTransition::class, WorkflowTransitionPolicy::class);
        Gate::policy(StagePermission::class, StagePermissionPolicy::class);
        Gate::policy(FieldGroup::class, FieldGroupPolicy::class);
        Gate::policy(FieldDefinition::class, FieldDefinitionPolicy::class);
        Gate::policy(StageFieldRule::class, StageFieldRulePolicy::class);
        Gate::policy(EngineRequest::class, EngineRequestPolicy::class);

        // OBS-001: the Pulse dashboard (per-endpoint p50/p95/p99 latency,
        // slow queries, exceptions) is operational infrastructure detail, not
        // appropriate for any authenticated user — restricted to system admins
        // (CBY_ADMIN), mirroring the viewHorizon gate in HorizonServiceProvider.
        // Without this, Pulse's default gate only allows the 'local' env, which
        // would lock the dashboard out entirely in staging/production.
        Gate::define('viewPulse', function ($user = null) {
            return $user !== null && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin();
        });
    }
}
