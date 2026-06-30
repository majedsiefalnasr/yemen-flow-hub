<?php

namespace Tests\Unit\Workflow;

use App\Enums\StageAccessLevel;
use App\Models\StagePermission;
use App\Services\Workflow\StagePermissionResolver;
use PHPUnit\Framework\TestCase;

class StagePermissionResolverTest extends TestCase
{
    private StagePermissionResolver $resolver;

    /** @var array{organization_id: int|null, team_ids: array<int>, role_ids: array<int>, user_id: int} */
    private array $identity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new StagePermissionResolver;
        $this->identity = [
            'organization_id' => 1,
            'team_ids' => [10, 11],
            'role_ids' => [20],
            'user_id' => 5,
        ];
    }

    private function row(array $attributes): StagePermission
    {
        $permission = new StagePermission;
        $permission->forceFill(array_merge([
            'organization_id' => null,
            'team_id' => null,
            'role_id' => null,
            'user_id' => null,
            'access_level' => StageAccessLevel::VIEW,
        ], $attributes));

        return $permission;
    }

    public function test_all_null_row_matches_everyone(): void
    {
        $rows = [$this->row(['access_level' => StageAccessLevel::VIEW])];

        $this->assertTrue($this->resolver->identityMatchesAny($this->identity, $rows, StageAccessLevel::VIEW));
    }

    public function test_set_fields_within_a_row_are_anded(): void
    {
        // Org matches but role does not → row fails (AND).
        $rows = [$this->row(['organization_id' => 1, 'role_id' => 99, 'access_level' => StageAccessLevel::EXECUTE])];
        $this->assertFalse($this->resolver->identityMatchesAny($this->identity, $rows, StageAccessLevel::VIEW));

        // Org + role both match → row passes.
        $rows = [$this->row(['organization_id' => 1, 'role_id' => 20, 'access_level' => StageAccessLevel::EXECUTE])];
        $this->assertTrue($this->resolver->identityMatchesAny($this->identity, $rows, StageAccessLevel::VIEW));
    }

    public function test_rows_are_ored(): void
    {
        $rows = [
            $this->row(['organization_id' => 2]), // does not match
            $this->row(['role_id' => 20]),        // matches
        ];

        $this->assertTrue($this->resolver->identityMatchesAny($this->identity, $rows, StageAccessLevel::VIEW));
    }

    public function test_execute_implies_view(): void
    {
        $rows = [$this->row(['role_id' => 20, 'access_level' => StageAccessLevel::EXECUTE])];

        // Requesting VIEW is satisfied by an EXECUTE row.
        $this->assertTrue($this->resolver->identityMatchesAny($this->identity, $rows, StageAccessLevel::VIEW));
        // Requesting EXECUTE is also satisfied.
        $this->assertTrue($this->resolver->identityMatchesAny($this->identity, $rows, StageAccessLevel::EXECUTE));
    }

    public function test_view_row_does_not_grant_execute(): void
    {
        $rows = [$this->row(['role_id' => 20, 'access_level' => StageAccessLevel::VIEW])];

        $this->assertTrue($this->resolver->identityMatchesAny($this->identity, $rows, StageAccessLevel::VIEW));
        $this->assertFalse($this->resolver->identityMatchesAny($this->identity, $rows, StageAccessLevel::EXECUTE));
    }

    public function test_user_id_exception_override(): void
    {
        // A user-specific EXECUTE row grants access even when role/org differ.
        $rows = [$this->row(['user_id' => 5, 'access_level' => StageAccessLevel::EXECUTE])];
        $this->assertTrue($this->resolver->identityMatchesAny($this->identity, $rows, StageAccessLevel::EXECUTE));

        // A row scoped to a different user does not match.
        $rows = [$this->row(['user_id' => 999, 'access_level' => StageAccessLevel::EXECUTE])];
        $this->assertFalse($this->resolver->identityMatchesAny($this->identity, $rows, StageAccessLevel::EXECUTE));
    }

    public function test_no_rows_means_no_access(): void
    {
        $this->assertFalse($this->resolver->identityMatchesAny($this->identity, [], StageAccessLevel::VIEW));
    }
}
