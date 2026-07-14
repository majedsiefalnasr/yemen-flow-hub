<?php

namespace App\DTOs;

use App\Models\IdempotencyKey;

class SubmissionResult
{
    public function __construct(
        public string $status,
        public int $httpStatus,
        public array $body,
        public array $headers = [],
    ) {}

    public static function created(array $body): self
    {
        return new self('created', 201, $body);
    }

    public static function fromStored(IdempotencyKey $key): self
    {
        return new self('replay', (int) $key->response_status, (array) $key->response_body);
    }

    public static function inProgress(int $retryAfterSeconds): self
    {
        return new self('in_progress', 202, ['status' => 'processing'], ['Retry-After' => (string) $retryAfterSeconds]);
    }

    public function toResponseArray(): array
    {
        return $this->body;
    }
}
