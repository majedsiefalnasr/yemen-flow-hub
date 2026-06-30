<?php

namespace App\Http\Requests;

use App\Enums\VoteType;
use Illuminate\Validation\Rules\Enum;

class CastVoteRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vote' => ['required', new Enum(VoteType::class)],
            'justification' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
