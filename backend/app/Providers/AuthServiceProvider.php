<?php

namespace App\Providers;

use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\FieldDefinition;
use App\Models\FieldGroup;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\Organization;
use App\Models\ReferenceTable;
use App\Models\ReferenceValue;
use App\Models\RequestDocument;
use App\Models\Role;
use App\Models\StageFieldRule;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\Trader;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Policies\BankPolicy;
use App\Policies\CustomsDeclarationPolicy;
use App\Policies\FieldDefinitionPolicy;
use App\Policies\FieldGroupPolicy;
use App\Policies\ImportRequestPolicy;
use App\Policies\MerchantPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ReferenceTablePolicy;
use App\Policies\ReferenceValuePolicy;
use App\Policies\RequestDocumentPolicy;
use App\Policies\RolePolicy;
use App\Policies\StageFieldRulePolicy;
use App\Policies\StagePermissionPolicy;
use App\Policies\TeamPolicy;
use App\Policies\TraderPolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkflowActionPolicy;
use App\Policies\WorkflowDefinitionPolicy;
use App\Policies\WorkflowStagePolicy;
use App\Policies\WorkflowTransitionPolicy;
use App\Policies\WorkflowVersionPolicy;
use App\Services\Authorization\PermissionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Bank::class, BankPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ImportRequest::class, ImportRequestPolicy::class);
        Gate::policy(Merchant::class, MerchantPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(RequestDocument::class, RequestDocumentPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(CustomsDeclaration::class, CustomsDeclarationPolicy::class);
        Gate::policy(Trader::class, TraderPolicy::class);
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

        Gate::before(function ($user, string $ability) {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return app(PermissionService::class)->userCan($user, $ability);
        });
    }
}
