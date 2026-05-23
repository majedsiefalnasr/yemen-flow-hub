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

        // Heavy active-queue distribution — 1000 total requests across all 18 canonical RequestStatus values.
        // Skewed toward in-progress states so every role dashboard has substantial load for demo/testing.
        $plan = [
            ['draft', 100],
            ['draft_rejected_internal', 50],
            ['submitted', 80],
            ['bank_review', 100],
            ['bank_approved', 30],
            ['support_review_pending', 100],
            ['support_review_in_progress_claimed', 60],
            ['support_review_in_progress_expired', 20],
            ['support_approved', 30],
            ['support_rejected', 50],
            ['waiting_for_swift', 80],
            ['swift_uploaded', 40],
            ['waiting_for_voting_open', 50],
            ['executive_voting_open', 60],
            ['executive_voting_open_tie', 10],
            ['executive_voting_closed', 30],
            ['executive_approved', 30],
            ['executive_rejected', 30],
            ['customs_declaration_issued', 30],
            ['completed', 15],
            ['completed_with_revision', 5],
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
