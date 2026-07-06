<?php

namespace App\Support;

/**
 * Simulates governance deactivation when computing effective stage executors.
 *
 * @phpstan-type SimulationArray array{
 *     deactivating_organization_id?: int,
 *     deactivating_team_id?: int,
 *     deactivating_role_id?: int,
 *     deactivating_user_id?: int,
 * }
 */
final class GovernanceExecutorSimulation
{
    /**
     * @param  SimulationArray  $simulation
     */
    public function __construct(private readonly array $simulation) {}

    public static function forOrganization(int $organizationId): self
    {
        return new self(['deactivating_organization_id' => $organizationId]);
    }

    public static function forTeam(int $teamId): self
    {
        return new self(['deactivating_team_id' => $teamId]);
    }

    public static function forRole(int $roleId): self
    {
        return new self(['deactivating_role_id' => $roleId]);
    }

    public static function forUser(int $userId): self
    {
        return new self(['deactivating_user_id' => $userId]);
    }

    public function isDeactivatingOrganization(int $organizationId): bool
    {
        return ($this->simulation['deactivating_organization_id'] ?? null) === $organizationId;
    }

    public function isDeactivatingTeam(int $teamId): bool
    {
        return ($this->simulation['deactivating_team_id'] ?? null) === $teamId;
    }

    public function isDeactivatingRole(int $roleId): bool
    {
        return ($this->simulation['deactivating_role_id'] ?? null) === $roleId;
    }

    public function isDeactivatingUser(int $userId): bool
    {
        return ($this->simulation['deactivating_user_id'] ?? null) === $userId;
    }

    public function deactivatingOrganizationId(): ?int
    {
        return $this->simulation['deactivating_organization_id'] ?? null;
    }
}
