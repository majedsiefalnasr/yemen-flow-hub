<?php

namespace Tests\Feature\Notifications;

use App\Enums\OrganizationClassification;
use App\Enums\WorkflowVersionState;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Notifications\EngineNotificationDispatcher;
use App\Services\Workflow\StagePermissionAudience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAudienceScopeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $ncOrg;

    private Organization $bankOrg;

    private Bank $bankA;

    private Bank $bankB;

    private User $ncUser;

    private User $bankAUser;

    private User $bankBUser;

    private WorkflowVersion $version;

    private WorkflowStage $stage;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organizations
        $this->ncOrg = Organization::create([
            'code' => 'nc_org',
            'name' => 'National Committee',
            'classification' => OrganizationClassification::NATIONAL_COMMITTEE,
            'is_active' => true,
        ]);

        $this->bankOrg = Organization::create([
            'code' => 'bank_org',
            'name' => 'Banking Sector',
            'classification' => OrganizationClassification::BANKING_SECTOR,
            'is_active' => true,
        ]);

        // Create banks
        $this->bankA = Bank::create([
            'organization_id' => $this->bankOrg->id,
            'code' => 'BANKA',
            'name' => 'Bank A',
        ]);

        $this->bankB = Bank::create([
            'organization_id' => $this->bankOrg->id,
            'code' => 'BANKB',
            'name' => 'Bank B',
        ]);

        // Create users
        $this->ncUser = User::create([
            'organization_id' => $this->ncOrg->id,
            'name' => 'NC User',
            'email' => 'nc@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->bankAUser = User::create([
            'organization_id' => $this->bankOrg->id,
            'bank_id' => $this->bankA->id,
            'name' => 'Bank A User',
            'email' => 'banka@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->bankBUser = User::create([
            'organization_id' => $this->bankOrg->id,
            'bank_id' => $this->bankB->id,
            'name' => 'Bank B User',
            'email' => 'bankb@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Create workflow structures
        $definition = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'code' => 'test_wf',
        ]);

        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
        ]);

        $this->stage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'Test Stage',
            'code' => 'test_stage',
        ]);
    }

    public function test_nc_users_receive_all_notifications_while_bank_users_are_scoped(): void
    {
        // Create a request for Bank A
        $request = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REQ-A',
            'bank_id' => $this->bankA->id,
            'status' => 'ACTIVE',
            'created_by' => $this->bankAUser->id,
        ]);

        $audienceMock = $this->mock(StagePermissionAudience::class);
        $audienceMock->shouldReceive('executeHolderIds')->andReturn([
            $this->ncUser->id,
            $this->bankAUser->id,
            $this->bankBUser->id,
        ]);

        $dispatcher = \Mockery::mock(EngineNotificationDispatcher::class, [$audienceMock])->makePartial();
        $dispatcher->shouldAllowMockingProtectedMethods();
        $dispatcher->shouldReceive('dispatchAfterCommit')->once()->with(
            'transition',
            'info',
            \Mockery::any(),
            \Mockery::any(),
            'engine_request',
            \Mockery::any(),
            \Mockery::any(),
            \Mockery::on(function ($recipients) {
                // NC User and Bank A User should receive it. Bank B User should NOT.
                return count($recipients) === 2
                    && in_array($this->ncUser->id, $recipients)
                    && in_array($this->bankAUser->id, $recipients)
                    && ! in_array($this->bankBUser->id, $recipients);
            })
        );

        // Trigger a transition notification
        $dispatcher->afterTransition(
            $request->id,
            $request->reference,
            $this->stage,
            'From Stage',
            'To Stage',
            'Action'
        );
    }

    public function test_duplicate_invoice_notification_is_scoped(): void
    {
        // Create a request for Bank B
        $request = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REQ-B',
            'bank_id' => $this->bankB->id,
            'status' => 'ACTIVE',
            'created_by' => $this->bankBUser->id,
        ]);

        $dispatcher = \Mockery::mock(EngineNotificationDispatcher::class, [app(StagePermissionAudience::class)])->makePartial();
        $dispatcher->shouldAllowMockingProtectedMethods();
        $dispatcher->shouldReceive('resolveAuditViewers')->andReturn([
            $this->ncUser->id,
            $this->bankAUser->id,
            $this->bankBUser->id,
        ]);

        // afterDuplicateInvoice splits by classification (WP-7 S-8 masking): a
        // separate dispatchAfterCommit call for the NC audience (full detail)
        // and another for the bank-scoped audience (masked cross-bank refs) —
        // unlike afterSlaSignal/afterTransition, which send one unified call.
        $dispatcher->shouldReceive('dispatchAfterCommit')->once()->with(
            'compliance.duplicate_invoice',
            'warning',
            \Mockery::any(),
            \Mockery::any(),
            'engine_request',
            $request->id,
            \Mockery::any(),
            \Mockery::on(function ($recipients) {
                // NC branch: NC User only.
                return count($recipients) === 1 && in_array($this->ncUser->id, $recipients);
            })
        );

        $dispatcher->shouldReceive('dispatchAfterCommit')->once()->with(
            'compliance.duplicate_invoice',
            'warning',
            \Mockery::any(),
            \Mockery::any(),
            'engine_request',
            $request->id,
            \Mockery::any(),
            \Mockery::on(function ($recipients) {
                // Bank branch: Bank B User only (own-bank scoped, masked cross-bank).
                return count($recipients) === 1
                    && in_array($this->bankBUser->id, $recipients)
                    && ! in_array($this->bankAUser->id, $recipients);
            })
        );

        $dispatcher->afterDuplicateInvoice(
            $request->id,
            $request->reference,
            'INV-123',
            [['id' => 1, 'reference' => 'OLD-1', 'bank_id' => $this->bankB->id]]
        );
    }

    public function test_sla_signal_notification_is_scoped(): void
    {
        // Create a request for Bank A
        $request = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'REQ-A-SLA',
            'bank_id' => $this->bankA->id,
            'status' => 'ACTIVE',
            'created_by' => $this->bankAUser->id,
        ]);

        $dispatcher = \Mockery::mock(EngineNotificationDispatcher::class, [app(StagePermissionAudience::class)])->makePartial();
        $dispatcher->shouldAllowMockingProtectedMethods();
        $dispatcher->shouldReceive('resolveAuditViewers')->andReturn([
            $this->ncUser->id,
            $this->bankAUser->id,
            $this->bankBUser->id,
        ]);

        $dispatcher->shouldReceive('dispatchAfterCommit')->once()->with(
            'sla.breached',
            'critical',
            \Mockery::any(),
            \Mockery::any(),
            'engine_request',
            $request->id,
            \Mockery::any(),
            \Mockery::on(function ($recipients) {
                // NC User and Bank A User should receive it. Bank B User should NOT.
                return count($recipients) === 2
                    && in_array($this->ncUser->id, $recipients)
                    && in_array($this->bankAUser->id, $recipients)
                    && ! in_array($this->bankBUser->id, $recipients);
            })
        );

        $dispatcher->afterSlaSignal(
            $request->id,
            $request->reference,
            'breached',
            'Current Stage'
        );
    }
}
