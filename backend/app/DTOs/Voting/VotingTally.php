<?php

namespace App\DTOs\Voting;

class VotingTally
{
    public function __construct(
        public int $approveCount,
        public int $rejectCount,
        public int $abstainCount,
        public int $totalCast,
        public bool $isDecided,
        public string $result
    ) {
    }
}
