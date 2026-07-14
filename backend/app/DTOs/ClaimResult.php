<?php

namespace App\DTOs;

use App\Models\IdempotencyKey;

class ClaimResult
{
    public function __construct(
        public string $status,
        public ?IdempotencyKey $key = null,
        public ?string $claimToken = null,
        public ?int $retryAfterSeconds = null,
    ) {}

    public static function claimed(IdempotencyKey $key, string $claimToken): self
    {
        return new self('claimed', $key, $claimToken);
    }

    public static function replay(IdempotencyKey $key): self
    {
        return new self('replay', $key);
    }

    public static function inProgress(int $retryAfterSeconds): self
    {
        return new self('in_progress', retryAfterSeconds: max(1, $retryAfterSeconds));
    }

    public function isClaimed(): bool
    {
        return $this->status === 'claimed';
    }

    public function isReplay(): bool
    {
        return $this->status === 'replay';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }
}
