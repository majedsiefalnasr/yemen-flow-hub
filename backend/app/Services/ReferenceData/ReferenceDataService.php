<?php

namespace App\Services\ReferenceData;

use App\Enums\AuditAction;
use App\Exceptions\ReferenceDataProtectionException;
use App\Exceptions\StaleResourceException;
use App\Models\ReferenceTable;
use App\Models\ReferenceValue;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;

class ReferenceDataService
{
    public function __construct(private readonly AuditService $auditService) {}

    public function createTable(User $actor, array $attributes): ReferenceTable
    {
        return DB::transaction(function () use ($actor, $attributes): ReferenceTable {
            $referenceTable = ReferenceTable::query()->create($attributes)->refresh();
            $this->auditService->log(
                AuditAction::GOVERNANCE_CREATED,
                $actor,
                $referenceTable,
                ['after' => $referenceTable->toArray()],
            );

            return $referenceTable;
        });
    }

    public function createValue(User $actor, array $attributes): ReferenceValue
    {
        return DB::transaction(function () use ($actor, $attributes): ReferenceValue {
            $referenceValue = ReferenceValue::query()->create($attributes)->refresh();
            $this->auditService->log(
                AuditAction::GOVERNANCE_CREATED,
                $actor,
                $referenceValue,
                ['after' => $referenceValue->toArray()],
            );

            return $referenceValue;
        });
    }

    public function updateTable(
        User $actor,
        ReferenceTable $referenceTable,
        array $attributes,
        int $expectedVersion,
    ): ReferenceTable {
        return DB::transaction(function () use ($actor, $referenceTable, $attributes, $expectedVersion): ReferenceTable {
            $locked = ReferenceTable::query()->lockForUpdate()->findOrFail($referenceTable->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);
            $before = $locked->toArray();
            $locked->update([
                ...$attributes,
                'version' => $locked->version + 1,
            ]);
            $this->auditService->log(
                AuditAction::GOVERNANCE_UPDATED,
                $actor,
                $locked,
                ['before' => $before, 'after' => $locked->toArray()],
            );

            return $locked->refresh();
        });
    }

    public function updateValue(
        User $actor,
        ReferenceValue $referenceValue,
        array $attributes,
        int $expectedVersion,
    ): ReferenceValue {
        return DB::transaction(function () use ($actor, $referenceValue, $attributes, $expectedVersion): ReferenceValue {
            $locked = ReferenceValue::query()->lockForUpdate()->findOrFail($referenceValue->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);
            $before = $locked->toArray();
            $locked->update([
                ...$attributes,
                'version' => $locked->version + 1,
            ]);
            $this->auditService->log(
                AuditAction::GOVERNANCE_UPDATED,
                $actor,
                $locked,
                ['before' => $before, 'after' => $locked->toArray()],
            );

            return $locked->refresh();
        });
    }

    public function setTableActive(
        User $actor,
        ReferenceTable $referenceTable,
        bool $active,
        int $expectedVersion,
    ): ReferenceTable {
        return DB::transaction(function () use ($actor, $referenceTable, $active, $expectedVersion): ReferenceTable {
            $locked = ReferenceTable::query()->lockForUpdate()->findOrFail($referenceTable->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);

            if ($locked->is_active === $active) {
                return $locked;
            }

            $before = $locked->only(['is_active', 'version']);
            $locked->update([
                'is_active' => $active,
                'version' => $locked->version + 1,
            ]);
            $this->auditService->log(
                AuditAction::GOVERNANCE_UPDATED,
                $actor,
                $locked,
                ['before' => $before, 'after' => $locked->only(['is_active', 'version'])],
            );

            return $locked->refresh();
        });
    }

    public function setValueActive(
        User $actor,
        ReferenceValue $referenceValue,
        bool $active,
        int $expectedVersion,
    ): ReferenceValue {
        return DB::transaction(function () use ($actor, $referenceValue, $active, $expectedVersion): ReferenceValue {
            $locked = ReferenceValue::query()->lockForUpdate()->findOrFail($referenceValue->getKey());
            $this->ensureCurrentVersion($locked->version, $expectedVersion);

            if ($locked->is_active === $active) {
                return $locked;
            }

            $before = $locked->only(['is_active', 'version']);
            $locked->update([
                'is_active' => $active,
                'version' => $locked->version + 1,
            ]);
            $this->auditService->log(
                AuditAction::GOVERNANCE_UPDATED,
                $actor,
                $locked,
                ['before' => $before, 'after' => $locked->only(['is_active', 'version'])],
            );

            return $locked->refresh();
        });
    }

    public function deleteTable(User $actor, ReferenceTable $referenceTable): void
    {
        $blocked = DB::transaction(function () use ($actor, $referenceTable): ?ReferenceTable {
            $locked = ReferenceTable::query()->lockForUpdate()->findOrFail($referenceTable->getKey());

            if ($locked->isProtected() || $locked->isInUse()) {
                return $locked;
            }

            $before = $locked->toArray();
            $locked->delete();
            $this->auditService->log(
                AuditAction::GOVERNANCE_DELETED,
                $actor,
                $locked,
                ['before' => $before],
            );

            return null;
        });

        if ($blocked !== null) {
            $this->auditBlockedMutation($actor, $blocked, 'reference_table_protected_or_in_use');
            throw new ReferenceDataProtectionException(
                'REFERENCE_TABLE_PROTECTED',
                'Reference table cannot be deleted while it has values or is a system table.',
            );
        }
    }

    public function deleteValue(User $actor, ReferenceValue $referenceValue): void
    {
        $blocked = DB::transaction(function () use ($actor, $referenceValue): ?ReferenceValue {
            $locked = ReferenceValue::query()->lockForUpdate()->findOrFail($referenceValue->getKey());

            if ($locked->isProtected() || $locked->isInUse()) {
                return $locked;
            }

            $before = $locked->toArray();
            $locked->delete();
            $this->auditService->log(
                AuditAction::GOVERNANCE_DELETED,
                $actor,
                $locked,
                ['before' => $before],
            );

            return null;
        });

        if ($blocked !== null) {
            $this->auditBlockedMutation($actor, $blocked, 'reference_value_protected_or_in_use');
            throw new ReferenceDataProtectionException(
                'REFERENCE_VALUE_PROTECTED',
                'Reference value cannot be deleted while in use or is a system value.',
            );
        }
    }

    private function ensureCurrentVersion(int $actualVersion, int $expectedVersion): void
    {
        if ($actualVersion !== $expectedVersion) {
            throw new StaleResourceException;
        }
    }

    private function auditBlockedMutation(User $actor, ReferenceTable|ReferenceValue $subject, string $reason): void
    {
        $this->auditService->log(
            AuditAction::AUTHORIZATION_FAILURE,
            $actor,
            $subject,
            ['reason' => $reason],
        );
    }
}
