<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Exceptions\FinancingLimitExceededException;
use App\Exceptions\FinancingLockTimeoutException;
use App\Models\ImportRequest;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Global cross-bank financing ledger derived directly from import_requests.
 *
 * Design (architecture D1): there is no separate ledger table. request_percentage
 * rows on import_requests are the single source of truth.
 *
 * Org-scope exception (architecture Authentication & Security): this is the only
 * service that intentionally bypasses ImportRequest::scopeForUser() to compute a
 * GLOBAL total for duplicate/financing validation and utilization indicators.
 * Public methods return scalar aggregates only and never expose foreign request
 * rows, reference numbers, amounts, or other per-row identifying fields.
 *
 * Concurrency (architecture D2): never use SELECT SUM(...) FOR UPDATE. An aggregate
 * result row is not the matched request rows, locks nothing, and cannot lock rows
 * that do not yet exist (empty-set / phantom-insert race). The required protocol is:
 * named invoice-key lock -> row-lock matching rows -> sum AFTER row lock -> validate.
 */
class FinancingLedgerService
{
    /**
     * Terminal non-approved (Not-Eligible) outcomes that free financing capacity.
     *
     * Business rule (code-review 17-D decision #10): capacity is consumed by every
     * active in-flight request and released only by Not-Eligible outcomes (the
     * rejected/dead-end states below) and cancellation (soft-delete, already
     * excluded by the query). DRAFT and the returned-for-correction states
     * (BANK_RETURNED, SUPPORT_RETURNED) intentionally STAY in the sum: an
     * unsubmitted draft created under reserveCapacity must hold its reservation,
     * and a returned request may still be corrected and completed — releasing its
     * slot would let another bank consume the capacity and block the resubmission.
     *
     * @var list<RequestStatus>
     */
    public const NOT_ELIGIBLE_STATUSES = [
        RequestStatus::BANK_REJECTED,           // terminal bank rejection
        RequestStatus::SUPPORT_REJECTED,        // terminal support rejection
        RequestStatus::EXECUTIVE_REJECTED,      // terminal executive rejection
        RequestStatus::DRAFT_REJECTED_INTERNAL, // returned internally, dead-end for financing
    ];

    private const LOCK_TIMEOUT_SECONDS = 10;

    public function reserveCapacity(
        string $taxNumber,
        string $invoiceNumber,
        float $requestedPercent,
        callable $onSuccess,
        ?int $excludeRequestId = null,
    ): mixed {
        // Acquire the named lock OUTSIDE the transaction. Laravel retries the
        // transaction closure on deadlock; acquiring inside would re-enter
        // GET_LOCK (re-entrant counter) while the single finally release only
        // decrements once, leaking the lock (code-review 17-D).
        $lockName = $this->lockName($taxNumber, $invoiceNumber);
        $cacheLock = null;
        $this->acquireNamedLock($lockName, $cacheLock);

        try {
            return DB::transaction(function () use ($taxNumber, $invoiceNumber, $requestedPercent, $onSuccess, $excludeRequestId): mixed {
                $usedPercent = $this->sumUsedPercentAfterRowLock($taxNumber, $invoiceNumber, $excludeRequestId);

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
    ): void {
        $this->reserveCapacity(
            $taxNumber,
            $invoiceNumber,
            $requestedPercent,
            static fn (): null => null,
            $excludeRequestId,
        );
    }

    public function usedPercent(string $taxNumber, string $invoiceNumber, ?int $excludeRequestId = null): float
    {
        return $this->sumEligiblePercent($taxNumber, $invoiceNumber, $excludeRequestId);
    }

    public function remainingPercent(string $taxNumber, string $invoiceNumber, ?int $excludeRequestId = null): float
    {
        return max(0, 100 - $this->usedPercent($taxNumber, $invoiceNumber, $excludeRequestId));
    }

    public function wouldExceed(
        string $taxNumber,
        string $invoiceNumber,
        float $newPercent,
        ?int $excludeRequestId = null,
    ): bool {
        return ($this->usedPercent($taxNumber, $invoiceNumber, $excludeRequestId) + $newPercent) > 100;
    }

    /**
     * @return list<string>
     */
    public static function notEligibleStatusValues(): array
    {
        return array_map(
            static fn (RequestStatus $status): string => $status->value,
            self::NOT_ELIGIBLE_STATUSES,
        );
    }

    /**
     * Canonical invoice-key normalization (code-review 17-D): trim surrounding
     * whitespace so " INV-1 " and "INV-1" resolve to the same ledger key and the
     * 100% cap cannot be bypassed by reformatting.
     */
    public static function normalizeKey(string $value): string
    {
        return trim($value);
    }

    private function sumEligiblePercent(string $taxNumber, string $invoiceNumber, ?int $excludeRequestId = null): float
    {
        $total = ImportRequest::query()
            ->where('trader_snapshot_tax_number', self::normalizeKey($taxNumber))
            ->where('invoice_number', self::normalizeKey($invoiceNumber))
            ->when($excludeRequestId !== null, fn ($query) => $query->where('id', '!=', $excludeRequestId))
            ->whereNotIn('status', self::notEligibleStatusValues())
            ->sum('request_percentage');

        return round((float) $total, 2);
    }

    private function sumUsedPercentAfterRowLock(string $taxNumber, string $invoiceNumber, ?int $excludeRequestId = null): float
    {
        $lockedRows = ImportRequest::query()
            ->where('trader_snapshot_tax_number', self::normalizeKey($taxNumber))
            ->where('invoice_number', self::normalizeKey($invoiceNumber))
            ->when($excludeRequestId !== null, fn ($query) => $query->where('id', '!=', $excludeRequestId))
            ->whereNotIn('status', self::notEligibleStatusValues())
            ->lockForUpdate()
            ->get(['request_percentage']);

        $total = $lockedRows->sum(static fn (ImportRequest $request): float => (float) ($request->request_percentage ?? 0));

        return round($total, 2);
    }

    private function lockName(string $taxNumber, string $invoiceNumber): string
    {
        // Hash the normalized key so the lock name stays within MySQL's 64-char
        // GET_LOCK limit and cannot collide via a raw delimiter (code-review 17-D).
        $key = self::normalizeKey($taxNumber).'|'.self::normalizeKey($invoiceNumber);

        return 'financing:'.hash('sha256', $key);
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
            throw new RuntimeException('Unable to acquire financing invoice-key lock.');
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

    public function isNamedLockFree(string $taxNumber, string $invoiceNumber): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return true;
        }

        $lockName = $this->lockName($taxNumber, $invoiceNumber);
        $result = DB::selectOne('SELECT IS_FREE_LOCK(?) AS is_free', [$lockName]);

        return (int) ($result->is_free ?? 0) === 1;
    }
}
