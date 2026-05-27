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

        // ~1400 requests distributed across all 21 canonical RequestStatus values.
        // Each scenario carries its own creation-date window so completed requests
        // are old (good for analytics/report charts) and drafts are recent.
        // Includes BANK_RETURNED, SUPPORT_RETURNED, BANK_REJECTED, and eligible_voter_ids.
        //
        // Columns: [scenario, count, daysAgoMin, daysAgoMax]
        $plan = [
            ['draft',                              120,   1,  14],
            ['draft_rejected_internal',             50,   5,  25],
            ['submitted',                          100,   3,  21],
            ['bank_review',                        120,   5,  28],
            ['bank_returned',                       40,   7,  35],
            ['bank_rejected',                       25,  20,  90],
            ['bank_approved',                       40,  14,  45],
            ['support_review_pending',             120,  14,  50],
            ['support_review_in_progress_claimed',  70,  14,  50],
            ['support_review_in_progress_expired',  25,  21,  60],
            ['support_approved',                    40,  21,  70],
            ['support_rejected',                    50,  30, 100],
            ['support_returned',                    25,  21,  60],
            ['waiting_for_swift',                  100,  21,  70],
            ['swift_uploaded',                      50,  30,  90],
            ['waiting_for_voting_open',             60,  35, 100],
            ['executive_voting_open',               80,  45, 120],
            ['executive_voting_open_tie',           15,  45, 120],
            ['executive_voting_closed',             40,  60, 150],
            ['executive_approved',                  40,  90, 210],
            ['executive_rejected',                  40,  90, 210],
            ['customs_declaration_issued',          40, 120, 300],
            ['completed',                           60, 180, 365],
            ['completed_with_revision',             20, 180, 365],
        ];

        $i = 0;
        $total = 0;
        foreach ($plan as [$scenario, $count, $daysMin, $daysMax]) {
            for ($c = 0; $c < $count; $c++) {
                $bank = $banks[$i % $banks->count()];
                $createdAt = now()
                    ->subDays(rand($daysMin, $daysMax))
                    ->subHours(rand(0, 23))
                    ->subMinutes(rand(0, 59));
                $builder->build($scenario, $bank, $createdAt);
                $i++;
                $total++;
            }
        }

        $this->command?->info("Seeded {$total} import requests covering all 21 canonical workflow statuses (date range: 1–365 days ago).");
    }
}
