<?php

namespace App\Rules;

use App\Models\Role;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RoleBelongsToOrganization implements ValidationRule
{
    public function __construct(private readonly int $organizationId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Role::query()->whereKey($value)->where('organization_id', $this->organizationId)->exists()) {
            $fail('The selected role must belong to the selected organization.');
        }
    }
}
