<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Exceptions\FinancingLimitExceededException;
use App\Models\ImportRequest;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
     * Terminal non-approved outcomes that free financing capacity.
     *
     * Cross-reference RequestStatus::isTerminal(): only the rejected/dead-end cases
     * below can never reach COMPLETED and must not keep consuming global capacity.
     * Returned-for-correction statuses (BANK_RETURNED, SUPPORT_RETURNED) stay in
     * the sum because the request may still complete.
     *
     * @var list<RequestStatus>
     */
    public const NOT_ELIGIBLE_STATUSES = [
        RequestStatus::BANK_REJECTED,          // terminal bank rejection
        RequestStatus::SUPPORT_REJECTED,       // terminal support rejection
        RequestStatus::EXECUTIVE_REJECTED,     // terminal executive rejection
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
        return DB::transaction(function () use ($taxNumber, $invoiceNumber, $requestedPercent, $onSuccess, $excludeRequestId): mixed {
            $lockName = $this->lockName($taxNumber, $invoiceNumber);
            $cacheLock = null;

            $this->acquireNamedLock($lockName, $cacheLock);

            try {
                $usedPercent = $this->sumUsedPercentAfterRowLock($taxNumber, $invoiceNumber, $excludeRequestId);

                if ($usedPercent + $requestedPercent > 100) {
                    throw new FinancingLimitExceededException(
                        usedPercent: $usedPercent,
                        requestedPercent: $requestedPercent,
                    );
                }

                return $onSuccess();
            } finally {
                $this->releaseNamedLock($lockName, $cacheLock);
            }
        });
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

    private function sumEligiblePercent(string $taxNumber, string $invoiceNumber, ?int $excludeRequestId = null): float
    {
        $total = ImportRequest::query()
            ->where('trader_snapshot_tax_number', $taxNumber)
            ->where('invoice_number', $invoiceNumber)
            ->when($excludeRequestId !== null, fn ($query) => $query->where('id', '!=', $excludeRequestId))
            ->whereNotIn('status', self::notEligibleStatusValues())
            ->sum('request_percentage');

        return round((float) $total, 2);
    }

    private function sumUsedPercentAfterRowLock(string $taxNumber, string $invoiceNumber, ?int $excludeRequestId = null): float
    {
        $lockedRows = ImportRequest::query()
            ->where('trader_snapshot_tax_number', $taxNumber)
            ->where('invoice_number', $invoiceNumber)
            ->when($excludeRequestId !== null, fn ($query) => $query->where('id', '!=', $excludeRequestId))
            ->whereNotIn('status', self::notEligibleStatusValues())
            ->lockForUpdate()
            ->get(['request_percentage']);

        $total = $lockedRows->sum(static fn (ImportRequest $request): float => (float) ($request->request_percentage ?? 0));

        return round($total, 2);
    }

    private function lockName(string $taxNumber, string $invoiceNumber): string
    {
        return 'financing:'.$taxNumber.':'.$invoiceNumber;
    }

    private function acquireNamedLock(string $lockName, mixed &$cacheLock): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $result = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, self::LOCK_TIMEOUT_SECONDS]);
            if ((int) ($result->acquired ?? 0) !== 1) {
                throw new RuntimeException('Unable to acquire financing invoice-key lock.');
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
