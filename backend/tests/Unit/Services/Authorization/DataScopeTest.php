<?php

namespace Tests\Unit\Services\Authorization;

use App\DTOs\Authorization\DataScopeContext;
use App\Enums\OrganizationClassification;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\User;
use App\Services\Authorization\DataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DataScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_user_national_committee_returns_system_wide_scope(): void
    {
        $organization = Organization::factory()->create([
            'classification' => OrganizationClassification::NATIONAL_COMMITTEE,
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'bank_id' => null,
        ]);

        $scope = DataScope::forUser($user);

        $this->assertTrue($scope->systemWide);
        $this->assertNull($scope->ownBankId);
    }

    public function test_for_user_banking_sector_with_bank_returns_bank_scoped_scope(): void
    {
        $organization = Organization::factory()->create([
            'classification' => OrganizationClassification::BANKING_SECTOR,
        ]);

        $bank = Bank::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'bank_id' => $bank->id,
        ]);

        $scope = DataScope::forUser($user);

        $this->assertFalse($scope->systemWide);
        $this->assertEquals($bank->id, $scope->ownBankId);
    }

    public function test_for_user_banking_sector_without_bank_returns_restricted_scope(): void
    {
        $organization = Organization::factory()->create([
            'classification' => OrganizationClassification::BANKING_SECTOR,
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'bank_id' => null,
        ]);

        $scope = DataScope::forUser($user);

        $this->assertFalse($scope->systemWide);
        $this->assertNull($scope->ownBankId);
    }

    public function test_for_user_other_returns_restricted_scope(): void
    {
        $organization = Organization::factory()->create([
            'classification' => OrganizationClassification::OTHER,
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'bank_id' => null,
        ]);

        $scope = DataScope::forUser($user);

        $this->assertFalse($scope->systemWide);
        $this->assertNull($scope->ownBankId);
    }

    public function test_for_user_without_organization_returns_restricted_scope(): void
    {
        $user = User::factory()->create([
            'organization_id' => null,
            'bank_id' => null,
        ]);

        $scope = DataScope::forUser($user);

        $this->assertFalse($scope->systemWide);
        $this->assertNull($scope->ownBankId);
    }

    public function test_apply_to_system_wide_does_not_filter(): void
    {
        $query = Mockery::mock(Builder::class);
        $query->shouldNotReceive('where');
        $query->shouldNotReceive('whereRaw');

        $scope = new DataScopeContext(systemWide: true, ownBankId: null);

        $result = DataScope::applyTo($query, $scope);

        $this->assertSame($query, $result);
    }

    public function test_apply_to_bank_scoped_filters_by_bank_id(): void
    {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')->with('bank_id', 123)->andReturnSelf()->once();

        $scope = new DataScopeContext(systemWide: false, ownBankId: 123);

        $result = DataScope::applyTo($query, $scope);

        $this->assertSame($query, $result);
    }

    public function test_apply_to_bank_scoped_with_custom_column_filters_by_custom_column(): void
    {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')->with('custom_bank_id', 123)->andReturnSelf()->once();

        $scope = new DataScopeContext(systemWide: false, ownBankId: 123);

        $result = DataScope::applyTo($query, $scope, 'custom_bank_id');

        $this->assertSame($query, $result);
    }

    public function test_apply_to_restricted_scope_adds_impossible_where(): void
    {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('whereRaw')->with('1 = 0')->andReturnSelf()->once();

        $scope = new DataScopeContext(systemWide: false, ownBankId: null);

        $result = DataScope::applyTo($query, $scope);

        $this->assertSame($query, $result);
    }
}
