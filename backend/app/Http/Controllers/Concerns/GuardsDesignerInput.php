<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

trait GuardsDesignerInput
{
    /**
     * Run a create/update callback and convert a concurrent unique-constraint race
     * (passed the Form Request's SELECT-based uniqueness check, lost at INSERT/UPDATE)
     * into a clean 422 instead of a raw 500.
     */
    private function withUniqueViolationGuard(callable $callback, string $field, string $message): mixed
    {
        try {
            return $callback();
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw ValidationException::withMessages([$field => $message]);
            }

            throw $exception;
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        // SQLSTATE 23000 = integrity constraint violation (unique/duplicate key)
        // across MySQL and SQLite.
        return (string) ($exception->errorInfo[0] ?? '') === '23000';
    }

    /**
     * Escape LIKE wildcards (% and _) so user search input is matched literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }
}
