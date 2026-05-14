<?php

namespace App\Http\Resources;

use App\DTOs\Voting\VotingTally;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VotingTallyResource extends JsonResource
{
    /** @var VotingTally */
    public $resource;

    public function toArray(Request $request): array
    {
        return [
            'approve_count' => $this->resource->approveCount,
            'reject_count' => $this->resource->rejectCount,
            'abstain_count' => $this->resource->abstainCount,
            'total_cast' => $this->resource->totalCast,
            'is_decided' => $this->resource->isDecided,
            'result' => $this->resource->result,
        ];
    }
}
