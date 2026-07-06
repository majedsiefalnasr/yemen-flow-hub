<?php

namespace App\Services\Workflow\Engine;

use App\DTOs\Authorization\DataScopeContext;
use App\Exceptions\FinancingLimitExceededException;
use App\Exceptions\FinancingLockTimeoutException;
use App\Models\EngineRequest;
use App\Services\Authorization\DataScope;
use App\Support\EngineRequestStatus;
use App\Support\InvoiceKey;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Engine-side global cross-bank financing ledger, derived directly from
 * engine_requests (the dynamic-workflow analogue of FinancingLedgerService, which
 * sums the retiring import_requests table).
 *
 * Per Epic-18 architecture §1 (retire import_requests → engine instances) the engine
 * replaces the legacy flow for financing; this ledger sums engine_requests, never
 * import_requests. During legacy↔engine coexistence the two caps are separate.
 *
 * Concurrency (Epic-17 ledger D2): NEVER SELECT SUM(...) FOR UPDATE. The required
 * protocol is named invoice-key lock → row-lock matching rows → sum AFTER row lock →
 * validate, all in one transaction — closing the empty-set / phantom-insert race.
 *
 * Hybrid columns (DI-2): percentage reads the indexed engine_requests.request_percentage
 * projection; the trader tax key is resolved via the indexed merchant_id → merchants.tax_number
 * join (no separate trader-tax column per the locked DI-2 column set); invoice reads the
 * indexed engine_requests.invoice_number.
 */
class EngineFinancingLedger
{
    /**
     * Engine statuses that FREE financing capacity (terminal non-approved outcomes).
     *
     * @var list<string>
     */
    public const NOT_ELIGIBLE_STATUSES = EngineRequestStatus::CAPACITY_FREEING;

    private const LOCK_TIMEOUT_SECONDS = 10;

    public function reserveCapacity(
        string $taxNumber,
        string $invoiceNumber,
        float $requestedPercent,
        callable $onSuccess,
        ?int $excludeRequestId = null,
        ?DataScopeContext $scope = null,
    ): mixed {
        // Acquire the named lock OUTSIDE the transaction (Laravel retries the closure on
        // deadlock; acquiring inside would re-enter GET_LOCK while the single finally
        // release only decrements once — leaking the lock; carried from code-review 17-D).
        $lockName = $this->lockName($taxNumber, $invoiceNumber);
        $cacheLock = null;
        $this->acquireNamedLock($lockName, $cacheLock);

        try {
            return DB::transaction(function () use ($taxNumber, $invoiceNumber, $requestedPercent, $onSuccess, $excludeRequestId, $scope): mixed {
                $usedPercent = $this->sumUsedPercentAfterRowLock($taxNumber, $invoiceNumber, $excludeRequestId, $scope);

                if ($usedPercent + $requestedPercent > 100) {
                    throw new FinancingLimitExceededException(
                        usedPercent: $usedPercent,
                        requestedPercent: $requestedPercent,
                    );
                }

                return $onSuccess();
            });
        } finally {
            $this->releaseNamedLock($lockName, $cacheLock);
        }
    }

    public function assertWithinLimit(
        string $taxNumber,
        string $invoiceNumber,
        float $requestedPercent,
        ?int $excludeRequestId = null,
        ?DataScopeContext $scope = null,
    ): void {
        $this->reserveCapacity(
            $taxNumber,
            $invoiceNumber,
            $requestedPercent,
            static fn (): null => null,
            $excludeRequestId,
            $scope,
        );
    }

    public function usedPercent(string $taxNumber, string $invoiceNumber, ?int $excludeRequestId = null, ?DataScopeContext $scope = null): float
    {
        return $this->sumEligiblePercent($taxNumber, $invoiceNumber, $excludeRequestId, false, $scope);
    }

    public function remainingPercent(string $taxNumber, string $invoiceNumber, ?int $excludeRequestId = null, ?DataScopeContext $scope = null): float
    {
        return max(0.0, 100.0 - $this->usedPercent($taxNumber, $invoiceNumber, $excludeRequestId, $scope));
    }

    public static function normalizeKey(string $value): string
    {
        return InvoiceKey::normalize($value);
    }

    /**
     * @param  bool  $rowLock  when true, pessimistically locks the matched rows before summing
     */
    private function sumEligiblePercent(
        string $taxNumber,
        string $invoiceNumber,
        ?int $excludeRequestId,
        bool $rowLock,
        ?DataScopeContext $scope = null,
    ): float {
        $query = EngineRequest::query()
            ->join('merchants', 'merchants.id', '=', 'engine_requests.merchant_id')
            ->where('merchants.tax_number', self::normalizeKey($taxNumber))
            ->where('engine_requests.invoice_number_normalized', self::normalizeKey($invoiceNumber))
            ->when($excludeRequestId !== null, fn ($q) => $q->where('engine_requests.id', '!=', $excludeRequestId))
            ->whereNotIn('engine_requests.status', self::NOT_ELIGIBLE_STATUSES);

        if ($scope) {
            DataScope::applyTo($query, $scope, 'engine_requests.bank_id');
        }

        if (! $rowLock) {
            return round((float) $query->sum('engine_requests.request_percentage'), 2);
        }

        $rows = $query->lockForUpdate()->get(['engine_requests.request_percentage as request_percentage']);

        return round($rows->sum(static fn ($r): float => (float) ($r->request_percentage ?? 0)), 2);
    }

    private function sumUsedPercentAfterRowLock(string $taxNumber, string $invoiceNumber, ?int $excludeRequestId, ?DataScopeContext $scope = null): float
    {
        return $this->sumEligiblePercent($taxNumber, $invoiceNumber, $excludeRequestId, true, $scope);
    }

    private function lockName(string $taxNumber, string $invoiceNumber): string
    {
        // Hash the normalized key so the lock name stays within MySQL's 64-char GET_LOCK
        // limit and cannot collide via a raw delimiter (code-review 17-D). Engine-prefixed
        // so it never collides with a legacy 'financing:' lock during coexistence.
        $key = self::normalizeKey($taxNumber).'|'.self::normalizeKey($invoiceNumber);

        return 'engine_financing:'.hash('sha256', $key);
    }

    private function acquireNamedLock(string $lockName, mixed &$cacheLock): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $result = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, self::LOCK_TIMEOUT_SECONDS]);
            if ((int) ($result->acquired ?? 0) !== 1) {
                throw new FinancingLockTimeoutException;
            }

            return;
        }

        $cacheLock = Cache::lock($lockName, self::LOCK_TIMEOUT_SECONDS);

        try {
            $cacheLock->block(self::LOCK_TIMEOUT_SECONDS);
        } catch (LockTimeoutException) {
            throw new RuntimeException('Unable to acquire engine financing invoice-key lock.');
        }
    }

    private function releaseNamedLock(string $lockName, mixed $cacheLock): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SELECT RELEASE_LOCK(?)', [$lockName]);

            return;
        }

        optional($cacheLock)->release();
    }
}
