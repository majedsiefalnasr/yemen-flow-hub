<?php

namespace Database\Seeders;

use App\Models\Bank;
use Database\Seeders\Support\RequestScenarioBuilder;
use Illuminate\Database\Seeder;

class ImportRequestSeeder extends Seeder
{
    public function run(): void
    {
        $builder = new RequestScenarioBuilder();
        $banks = Bank::query()->where('is_active', true)->orderBy('id')->get()->values();

        // Covers all 18 canonical RequestStatus values with representative scenarios
        $plan = [
            // DRAFT (1)
            ['draft', 3],
            // DRAFT_REJECTED_INTERNAL (2)
            ['draft_rejected_internal', 2],
            // SUBMITTED (3)
            ['submitted', 2],
            // BANK_REVIEW (4)
            ['bank_review', 2],
            // BANK_APPROVED (5)
            ['bank_approved', 1],
            // SUPPORT_REVIEW_PENDING (6)
            ['support_review_pending', 2],
            // SUPPORT_REVIEW_IN_PROGRESS (7) — active claim
            ['support_review_in_progress_claimed', 2],
            // SUPPORT_REVIEW_IN_PROGRESS (7) — expired claim
            ['support_review_in_progress_expired', 1],
            // SUPPORT_APPROVED (8)
            ['support_approved', 1],
            // SUPPORT_REJECTED (9)
            ['support_rejected', 1],
            // WAITING_FOR_SWIFT (10)
            ['waiting_for_swift', 2],
            // SWIFT_UPLOADED (11)
            ['swift_uploaded', 1],
            // WAITING_FOR_VOTING_OPEN (12)
            ['waiting_for_voting_open', 1],
            // EXECUTIVE_VOTING_OPEN (13)
            ['executive_voting_open', 2],
            // EXECUTIVE_VOTING_OPEN tie scenario
            ['executive_voting_open_tie', 1],
            // EXECUTIVE_VOTING_CLOSED (14)
            ['executive_voting_closed', 1],
            // EXECUTIVE_APPROVED (15)
            ['executive_approved', 1],
            // EXECUTIVE_REJECTED (16)
            ['executive_rejected', 1],
            // CUSTOMS_DECLARATION_ISSUED (17)
            ['customs_declaration_issued', 1],
            // COMPLETED (18)
            ['completed', 2],
            ['completed_with_revision', 1],
        ];

        $i = 0;
        $total = 0;
        foreach ($plan as [$scenario, $count]) {
            for ($c = 0; $c < $count; $c++) {
                $bank = $banks[$i % $banks->count()];
                $builder->build($scenario, $bank);
                $i++;
                $total++;
            }
        }

        $this->command?->info("Seeded {$total} import requests covering all 18 canonical workflow statuses.");
    }
}
