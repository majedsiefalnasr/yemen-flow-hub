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

        $plan = [
            ['draft', 4],
            ['submitted', 3],
            ['bank_approved', 2],
            ['bank_rejected_terminal', 1],
            ['returned_to_entry_once', 2],
            ['support_under_review_claimed', 2],
            ['support_claim_expired', 1],
            ['support_approved_waiting_swift', 2],
            ['support_rejected_pending_reviewer', 1],
            ['executive_voting_pending', 3],
            ['executive_voting_tie', 1],
            ['executive_approved_no_customs_yet', 2],
            ['executive_rejected_returned', 1],
            ['customs_issued', 1],
            ['completed', 3],
            ['completed_with_revision', 1],
        ];

        $i = 0;
        foreach ($plan as [$scenario, $count]) {
            for ($c = 0; $c < $count; $c++) {
                $bank = $banks[$i % $banks->count()];
                $builder->build($scenario, $bank);
                $i++;
            }
        }

        $this->command?->info('Seeded scenario summary: 27 base + 3 claim scenarios = ~30 requests.');
    }
}
