<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Log;

class OperationalAlertLogger
{
    public static function failure(string $surface, \Throwable $e, array $context = []): void
    {
        Log::error("ops.{$surface}.failed", array_merge($context, [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
        ]));
    }
}
