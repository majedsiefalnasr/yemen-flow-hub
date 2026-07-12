<?php

namespace Tests\Feature\Audit;

use App\Enums\StageAccessLevel;
use App\Enums\WorkflowVersionState;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\Authorization\PermissionService;
use App\Services\Workflow\StagePermissionResolver;
use App\Support\RoleCodes;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * REGRESSION GATE (Phase E1) — RBAC probes for RBAC-001..004.
 *
 * Each test asserts the SECURE / EXPECTED behavior. Originated as Phase 2
 * audit probes (candidate findings CF-1..CF-4 from
 * docs/audit-functional/00-discovery.md, plus the demo-switch and
 * cross-org/cross-bank probes) that intentionally failed pre-fix; now that
 * RBAC-001..004 are fixed, this suite is permanent regression coverage — a
 * failing test means a security-boundary regression, not an open finding.
 * Runs on the isolated in-memory SQLite test DB (phpunit.xml), never the
 * dev/staging MySQL.
 */
class Phase2RbacProbeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $bankOrg;

    private Organization $cbyOrg;

    private Organization $otherOrg;

    private Bank $bankA;

    private Bank $bankB;

    private WorkflowVersion $version;

    private WorkflowStage $initialStage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $this->bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $this->cbyOrg = Organization::where('code', 'national_committee')->firstOrFail();
        $this->otherOrg = Organization::where('code', 'system_administration')->firstOrFail();

        $this->bankA = Bank::create(['name' => 'Bank A', 'code' => 'BKA', 'is_active' => true, 'organization_id' => $this->bankOrg->id]);
        $this->bankB = Bank::create(['name' => 'Bank B', 'code' => 'BKB', 'is_active' => true, 'organization_id' => $this->bankOrg->id]);

        $def = WorkflowDefinition::create(['code' => 'WF', 'name' => 'WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id, 'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED, 'published_at' => now(), 'version' => 1,
        ]);
        $this->initialStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'INIT', 'name' => 'Init',
            'sort_order' => 1, 'is_initial' => true, 'is_final' => false, 'version' => 1,
        ]);
    }

    private function makeUser(string $email, ?int $bankId, ?int $orgId, ?string $roleCode, bool $roleActive = true, bool $active = true): User
    {
        $u = User::create([
            'name' => $email, 'email' => $email, 'password' => bcrypt('password'),
            'bank_id' => $bankId, 'organization_id' => $orgId, 'is_active' => $active,
        ]);
        if ($roleCode !== null) {
            $role = Role::where('code', $roleCode)->firstOrFail();
            $u->roles()->attach($role, ['is_active' => $roleActive]);
        }

        return $u->fresh();
    }

    private function makeRequest(int $bankId): EngineRequest
    {
        $creator = User::create([
            'name' => 'creator-'.uniqid(), 'email' => 'creator-'.uniqid().'@bank',
            'password' => bcrypt('password'), 'bank_id' => $bankId,
            'organization_id' => $this->bankOrg->id, 'is_active' => true,
        ]);

        return EngineRequest::create([
            'reference' => 'ENG-'.uniqid(),
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->initialStage->id,
            'status' => 'ACTIVE', 'created_by' => $creator->id, 'bank_id' => $bankId,
            'data' => [], 'version' => 1,
        ]);
    }

    // ── CF-1: demoted / inactive-role administrator privilege retention ──────

    public function test_cf1_is_system_admin_ignores_inactive_role_pivot(): void
    {
        // A user whose ONLY system_admin pivot is inactive must NOT be treated as admin.
        $u = $this->makeUser('demoted@cby', null, $this->cbyOrg->id, RoleCodes::SYSTEM_ADMIN, roleActive: false);

        $this->assertFalse(
            $u->isSystemAdmin(),
            'CF-1: isSystemAdmin() returned true for a user with only an INACTIVE system_admin pivot.'
        );
    }

    public function test_cf1_has_role_code_ignores_inactive_role_pivot(): void
    {
        $u = $this->makeUser('demoted2@cby', null, $this->cbyOrg->id, RoleCodes::SYSTEM_ADMIN, roleActive: false);

        $this->assertFalse(
            $u->hasRoleCode(RoleCodes::SYSTEM_ADMIN),
            'CF-1: hasRoleCode(system_admin) returned true for an INACTIVE pivot.'
        );
    }

    public function test_cf1_reassignment_then_admin_endpoint_denied(): void
    {
        // Simulate the real demotion path: user was admin, assignActiveRole() moves
        // them to a non-admin role (old pivot deactivated but retained).
        $u = $this->makeUser('reassigned@cby', null, $this->cbyOrg->id, RoleCodes::SYSTEM_ADMIN);
        $support = Role::where('code', RoleCodes::SUPPORT)->firstOrFail();
        $u->assignActiveRole($support->id);
        $u->refresh();

        // Admin-only settings endpoint must reject the demoted user.
        $resp = $this->actingAs($u->fresh())->getJson('/api/admin/settings');
        $this->assertNotSame(
            200,
            $resp->status(),
            'CF-1: demoted admin (now support) still reached /api/admin/settings (status 200).'
        );
    }

    public function test_cf1_demoted_admin_audit_scope_not_systemwide(): void
    {
        // isSystemAdmin gates system-wide audit-log visibility. A demoted admin must
        // not retain it.
        $u = $this->makeUser('reassigned3@cby', null, $this->cbyOrg->id, RoleCodes::SYSTEM_ADMIN);
        $support = Role::where('code', RoleCodes::SUPPORT)->firstOrFail();
        $u->assignActiveRole($support->id);

        $this->assertFalse(
            $u->fresh()->isSystemAdmin(),
            'CF-1: demoted admin still reports isSystemAdmin() == true (system-wide audit read retained).'
        );
    }

    public function test_cf1_inactive_only_admin_cannot_reach_admin_settings(): void
    {
        $u = $this->makeUser('inactive-admin-settings@cby', null, $this->otherOrg->id, RoleCodes::SYSTEM_ADMIN, roleActive: false);

        $response = $this->actingAs($u)->getJson('/api/admin/settings');

        $this->assertNotSame(
            200,
            $response->status(),
            'RBAC-001: a user with only an INACTIVE historical system_admin role reached /api/admin/settings.'
        );
    }

    public function test_cf1_inactive_only_admin_cannot_open_request_by_id(): void
    {
        $request = $this->makeRequest($this->bankA->id);
        $u = $this->makeUser('inactive-admin-request@cby', null, $this->otherOrg->id, RoleCodes::SYSTEM_ADMIN, roleActive: false);

        $response = $this->actingAs($u)->getJson("/api/v1/engine-requests/{$request->id}");

        $this->assertNotSame(
            200,
            $response->status(),
            'RBAC-001: a user with only an INACTIVE historical system_admin role opened an engine request by ID.'
        );
    }

    public function test_cf1_reassigned_admin_in_other_org_cannot_gain_systemwide_request_list(): void
    {
        $supportRole = Role::where('code', RoleCodes::SUPPORT)->firstOrFail();
        StagePermission::create([
            'stage_id' => $this->initialStage->id,
            'organization_id' => $this->otherOrg->id,
            'role_id' => $supportRole->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'other org support view', 'version' => 1,
        ]);

        $this->makeRequest($this->bankA->id);
        $this->makeRequest($this->bankB->id);

        $u = $this->makeUser('reassigned-other@cby', null, $this->otherOrg->id, RoleCodes::SYSTEM_ADMIN);
        $u->assignActiveRole($supportRole->id);

        $response = $this->actingAs($u->fresh())->getJson('/api/v1/engine-requests')->assertOk();
        $visibleCount = count($response->json('data') ?? []);

        $this->assertSame(
            0,
            $visibleCount,
            "RBAC-001: a demoted admin in an OTHER-classification org saw {$visibleCount} engine requests; DataScope should deny all."
        );
    }

    // ── CF-2: admin-only screen grant via screen-permissions update ──────────

    public function test_cf2_admin_only_screen_grant_rejected(): void
    {
        // Caller: a real system admin (only role that can reach the update endpoint).
        $admin = $this->makeUser('admin@cby', null, $this->cbyOrg->id, RoleCodes::SYSTEM_ADMIN);
        $targetRole = Role::where('code', RoleCodes::SUPPORT)->firstOrFail();

        // Attempt to grant workflow_designer MANAGE to the support role — an
        // ADMIN_ONLY_SCREEN that the matrix UI never exposes.
        $resp = $this->actingAs($admin)->putJson("/api/v1/roles/{$targetRole->id}/screen-permissions", [
            'grants' => ['workflow_designer' => ['MANAGE']],
        ]);

        // If the contract is "admin-only screens are not customizable", the API must
        // reject this (422). If it accepts (200), the grant becomes live and the
        // capability-driven WorkflowDefinitionPolicy will honor it.
        $accepted = $resp->status() === 200;

        if ($accepted) {
            // Prove the grant is now enforced: support user gains designer access.
            (new PermissionService(app(StagePermissionResolver::class)))
                ->clearScreenPermissionCache($targetRole->id);
            $supportUser = $this->makeUser('sup@cby', null, $this->cbyOrg->id, RoleCodes::SUPPORT);
            $designerResp = $this->actingAs($supportUser)->getJson('/api/v1/workflow-definitions');
            $this->fail(
                'CF-2: screen-permissions update ACCEPTED a workflow_designer MANAGE grant on an admin-only screen. '
                .'Support user workflow-definitions access status after grant: '.$designerResp->status()
            );
        }

        $this->assertSame(422, $resp->status(), 'CF-2 expected: admin-only screen grant rejected (422).');
    }

    public function test_cf2_every_admin_only_screen_key_is_rejected_by_update_api(): void
    {
        $admin = $this->makeUser('admin-matrix@cby', null, $this->cbyOrg->id, RoleCodes::SYSTEM_ADMIN);
        $targetRole = Role::where('code', RoleCodes::SUPPORT)->firstOrFail();
        $adminOnlyScreens = [
            'workflow_designer',
            'users',
            'teams',
            'roles',
            'screen_permissions',
            'reference_data',
            'organizations',
            'banks',
        ];
        $accepted = [];

        foreach ($adminOnlyScreens as $screenKey) {
            $response = $this->actingAs($admin)->putJson("/api/v1/roles/{$targetRole->id}/screen-permissions", [
                'grants' => [$screenKey => ['MANAGE']],
            ]);

            if ($response->status() === 200) {
                $accepted[] = $screenKey;
            }
        }

        $this->assertSame(
            [],
            $accepted,
            'RBAC-002: update API accepted admin-only screen keys: '.implode(', ', $accepted)
        );
    }

    public function test_cf2_delegated_screen_permission_manager_cannot_grant_itself_designer_access(): void
    {
        $admin = $this->makeUser('admin-delegation@cby', null, $this->cbyOrg->id, RoleCodes::SYSTEM_ADMIN);
        $supportRole = Role::where('code', RoleCodes::SUPPORT)->firstOrFail();

        $delegation = $this->actingAs($admin)
            ->putJson("/api/v1/roles/{$supportRole->id}/screen-permissions", [
                'grants' => ['screen_permissions' => ['MANAGE']],
            ]);

        if ($delegation->status() !== 200) {
            $this->assertSame(422, $delegation->status());

            return;
        }

        app(PermissionService::class)->clearScreenPermissionCache($supportRole->id);
        $support = $this->makeUser('delegated-support@cby', null, $this->cbyOrg->id, RoleCodes::SUPPORT);

        $escalation = $this->actingAs($support)
            ->putJson("/api/v1/roles/{$supportRole->id}/screen-permissions", [
                'grants' => ['workflow_designer' => ['MANAGE']],
            ]);

        $this->assertNotSame(
            200,
            $escalation->status(),
            'RBAC-002: Support used delegated screen_permissions:MANAGE to grant itself workflow_designer:MANAGE.'
        );
    }

    // ── CF-3: list vs detail scope asymmetry ─────────────────────────────────

    public function test_cf3_other_classification_user_cannot_view_request_by_id(): void
    {
        // User in an OTHER-classification org with bank_id = null. DataScope denies
        // their lists (1=0). Detail policy inScope() returns true for bank_id===null.
        // Give the initial stage an org-wide VIEW row that names the OTHER org, so
        // stage VIEW passes — isolating the DataScope-vs-inScope divergence.
        StagePermission::create([
            'stage_id' => $this->initialStage->id,
            'organization_id' => $this->otherOrg->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'org-wide view', 'version' => 1,
        ]);

        $req = $this->makeRequest($this->bankA->id);
        $outsider = $this->makeUser('other@sys', null, $this->otherOrg->id, null);
        // Ensure the OTHER-org user is NOT a system admin (no admin bypass); they are
        // a plain org member with only a stage VIEW grant.
        $this->assertFalse($outsider->isSystemAdmin());

        // List: expect empty (DataScope 1=0).
        $list = $this->actingAs($outsider)->getJson('/api/v1/engine-requests');
        $listCount = is_array($list->json('data')) ? count($list->json('data')) : 0;

        // Detail: SECURE expectation = 403/404 (not visible). If 200, list/detail
        // disagree and cross-scope detail access leaks.
        $detail = $this->actingAs($outsider)->getJson("/api/v1/engine-requests/{$req->id}");

        $this->assertNotSame(
            200,
            $detail->status(),
            "CF-3: OTHER-classification user (empty list, count={$listCount}) still opened request {$req->id} by ID (status 200)."
        );
    }

    public function test_cf3_cross_bank_detail_denied(): void
    {
        // Baseline cross-bank isolation: a Bank B user must not open a Bank A request.
        StagePermission::create([
            'stage_id' => $this->initialStage->id,
            'organization_id' => $this->bankOrg->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'bank-org view', 'version' => 1,
        ]);

        $reqA = $this->makeRequest($this->bankA->id);
        $bankBUser = $this->makeUser('bkb@bank', $this->bankB->id, $this->bankOrg->id, RoleCodes::INTAKE);

        $detail = $this->actingAs($bankBUser)->getJson("/api/v1/engine-requests/{$reqA->id}");
        $this->assertNotSame(200, $detail->status(), 'Cross-bank: Bank B user opened a Bank A request by ID.');
    }

    public function test_cf3_other_classification_user_cannot_access_request_subresources(): void
    {
        StagePermission::create([
            'stage_id' => $this->initialStage->id,
            'organization_id' => $this->otherOrg->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'other org view', 'version' => 1,
        ]);

        $request = $this->makeRequest($this->bankA->id);
        $outsider = $this->makeUser('other-subresources@sys', null, $this->otherOrg->id, null);
        $endpoints = [
            'detail' => "/api/v1/engine-requests/{$request->id}",
            'form-schema' => "/api/v1/engine-requests/{$request->id}/form-schema",
            'history' => "/api/v1/engine-requests/{$request->id}/history",
            'graph' => "/api/v1/engine-requests/{$request->id}/graph",
            'documents' => "/api/v1/engine-requests/{$request->id}/documents",
        ];
        $accepted = [];

        foreach ($endpoints as $label => $endpoint) {
            $response = $this->actingAs($outsider)->getJson($endpoint);
            if ($response->status() === 200) {
                $accepted[] = $label;
            }
        }

        $this->assertSame(
            [],
            $accepted,
            'RBAC-004: OTHER-classification user accessed out-of-scope request subresources: '.implode(', ', $accepted)
        );
    }

    public function test_cf3_other_classification_executor_cannot_transition_bank_request(): void
    {
        $nextStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'NEXT',
            'name' => 'Next',
            'sort_order' => 2,
            'is_initial' => false,
            'is_final' => false,
            'version' => 1,
        ]);
        $action = WorkflowAction::create([
            'code' => 'AUDIT_ADVANCE',
            'name' => 'Audit advance',
            'kind' => 'APPROVE',
            'is_active' => true,
            'version' => 1,
        ]);
        $transition = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $this->initialStage->id,
            'to_stage_id' => $nextStage->id,
            'action_id' => $action->id,
            'requires_comment' => false,
            'version' => 1,
        ]);
        StagePermission::create([
            'stage_id' => $this->initialStage->id,
            'organization_id' => $this->otherOrg->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'other org execute',
            'version' => 1,
        ]);

        $request = $this->makeRequest($this->bankA->id);
        $outsider = $this->makeUser('other-executor@sys', null, $this->otherOrg->id, null);
        $response = $this->actingAs($outsider)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $transition->id,
            'version' => $request->version,
        ]);

        $this->assertNotSame(
            200,
            $response->status(),
            'RBAC-004: OTHER-classification user executed a transition on a bank request outside its DataScope.'
        );
        $this->assertSame($this->initialStage->id, $request->fresh()->current_stage_id);
    }

    // ── CF-4: /auth/me capability overlay ignores is_active on teams/roles ───

    public function test_cf4_auth_me_requests_capability_uses_active_roles_only(): void
    {
        // Grant EXECUTE on the initial stage to the SUPPORT role (org+role scoped).
        $supportRole = Role::where('code', RoleCodes::SUPPORT)->firstOrFail();
        StagePermission::create([
            'stage_id' => $this->initialStage->id,
            'organization_id' => $this->cbyOrg->id,
            'role_id' => $supportRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'support exec', 'version' => 1,
        ]);

        // User holds support role but INACTIVE. Runtime resolver (is_active filter)
        // denies; /auth/me overlay must agree (no ghost 'requests' access).
        $u = $this->makeUser('inactiverole@cby', null, $this->cbyOrg->id, RoleCodes::SUPPORT, roleActive: false);

        $me = $this->actingAs($u)->getJson('/api/auth/me');
        $screens = $me->json('data.screen_permissions') ?? $me->json('screen_permissions') ?? [];
        $requestsCaps = $screens['requests'] ?? [];

        $this->assertEmpty(
            $requestsCaps,
            'CF-4: /auth/me exposed requests capability '.json_encode($requestsCaps).' for a user whose only support role is INACTIVE (runtime resolver denies).'
        );
    }

    // ── Demo-switch endpoint gate (M2) ───────────────────────────────────────

    public function test_demo_switch_user_forbidden_when_flag_off(): void
    {
        config(['demo.allow_role_switch' => false]);
        $caller = $this->makeUser('caller@cby', null, $this->cbyOrg->id, RoleCodes::SUPPORT);
        $victim = $this->makeUser('victim@cby', null, $this->cbyOrg->id, RoleCodes::SYSTEM_ADMIN);

        $resp = $this->actingAs($caller)->postJson('/api/auth/switch-demo-user', ['user_id' => $victim->id]);
        // Route may be absent (404) in non-demo env or forbidden (403) — either is safe.
        $this->assertNotSame(
            200,
            $resp->status(),
            'DEMO: switch-demo-user succeeded with the demo flag OFF — arbitrary identity assumption.'
        );
    }
}
