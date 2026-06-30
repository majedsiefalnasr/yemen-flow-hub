<?php

namespace App\Events;

use App\Models\ImportRequest;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequestTransitioned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ImportRequest $requestModel,
        public string $action,
        public User $actor,
        public ?string $reason = null,
    ) {}
}
