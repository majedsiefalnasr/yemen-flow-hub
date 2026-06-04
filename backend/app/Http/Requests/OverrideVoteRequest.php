<?php

namespace App\Http\Requests;

use App\Enums\VoteType;
use Illuminate\Validation\Rules\Enum;

class OverrideVoteRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', new Enum(VoteType::class), 'in:APPROVE,REJECT'],
            'justification' => ['required', 'string', 'min:3', 'max:3000'],
        ];
    }
}
