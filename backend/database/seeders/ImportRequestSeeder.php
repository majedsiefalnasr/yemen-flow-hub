<?php

namespace Database\Seeders;

use App\Models\Bank;
use Database\Seeders\Support\RequestScenarioBuilder;
use Illuminate\Database\Seeder;

class ImportRequestSeeder extends Seeder
{
    public function run(): void
    {
        $builder = new RequestScenarioBuilder;
        $banks = Bank::query()->where('is_active', true)->orderBy('id')->get()->values();

        // ~300 demo requests — all 21 canonical statuses represented with enough
        // volume for every role dashboard and analytics chart, without flooding the
        // DB (notifications + documents + stage history multiply per request).
        //
        // Date windows ensure completed/terminal requests are old (analytics charts
        // show 6–12 months of history) while active pipeline entries are recent.
        //
        // Columns: [scenario, count, daysAgoMin, daysAgoMax]
        $plan = [
            // ── Active pipeline: recent entries ──────────────────────────────
            ['draft',                              24,   1,  14],
            ['draft_rejected_internal',            10,   5,  25],
            ['submitted',                          20,   3,  21],
            ['bank_review',                        24,   5,  28],
            ['bank_returned',                       8,   7,  35],
            ['bank_rejected',                       8,  20,  90],
            ['bank_approved',                       8,  14,  45],
            // ── Support stage ────────────────────────────────────────────────
            ['support_review_pending',             24,  14,  50],
            ['support_review_in_progress_claimed', 14,  14,  50],
            ['support_review_in_progress_expired',  4,  21,  60],
            ['support_approved',                    8,  21,  70],
            ['support_rejected',                   10,  30, 100],
            ['support_returned',                    6,  21,  60],
            // ── SWIFT / Voting ────────────────────────────────────────────────
            ['waiting_for_swift',                  20,  21,  70],
            ['swift_uploaded',                     10,  30,  90],
            ['waiting_for_voting_open',            12,  35, 100],
            ['executive_voting_open',              16,  45, 120],
            ['executive_voting_open_tie',           4,  45, 120],
            ['executive_voting_closed',             8,  60, 150],
            // ── Terminal / historical: spread for reports ─────────────────────
            ['executive_approved',                  8,  90, 210],
            ['executive_rejected',                  8,  90, 210],
            ['customs_declaration_issued',          8, 120, 300],
            ['completed',                          12, 180, 365],
            ['completed_with_revision',             4, 180, 365],
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

        $this->command?->info("Seeded {$total} demo requests covering all 21 canonical workflow statuses (date range: 1–365 days ago).");
    }
}
