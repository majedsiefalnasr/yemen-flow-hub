<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Merchant;
use App\Models\User;
use Database\Seeders\Catalog\SeederCatalog;
use Database\Seeders\Concerns\GuardsDemoSeedEnvironment;
use Database\Seeders\Support\EngineRequestScenarioBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Seeds the 250 bulk engine requests (125 per bank) from
 * database/seeders/catalog/engine-request-scenarios.php.
 *
 * Skipped entirely under DEMO_SEED_SIZE=minimal — only
 * EngineRequestAnchorSeeder's 56 anchors run in that mode.
 *
 * Spec: backend/docs/superpowers/specs/2026-07-07-engine-demo-seeder-redesign-design.md
 */
class EngineRequestBulkSeeder extends Seeder
{
    use GuardsDemoSeedEnvironment;

    public function run(EngineRequestScenarioBuilder $builder): void
    {
        $this->ensureDemoSeedAllowed();

        if (config('demo.seed_size') === 'minimal') {
            $this->command?->info('DEMO_SEED_SIZE=minimal — skipping bulk engine request seeding.');

            return;
        }

        $matrix = require base_path('database/seeders/catalog/engine-request-scenarios.php');

        $banks = Bank::query()->whereIn('code', ['YBRD', 'TIIB'])->get()->keyBy('code');
        $merchantsByBank = Merchant::query()
            ->whereIn('bank_id', $banks->pluck('id'))
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->get()
            ->groupBy('bank_id');
        $creatorsByBank = $this->resolveCreators($banks);

        $this->command?->info(sprintf('Seeding demo engine requests (56 anchors, %d bulk)…', SeederCatalog::BULK_COUNT));

        $seeded = 0;

        foreach (['YBRD', 'TIIB'] as $bankCode) {
            $bank = $banks[$bankCode] ?? null;
            if ($bank === null) {
                continue;
            }

            $merchants = ($merchantsByBank->get($bank->id) ?? collect())->values();
            if ($merchants->isEmpty()) {
                continue;
            }

            $creator = $creatorsByBank[$bank->id];
            $seq = 0;

            // The matrix sums to 250 across both banks (125 per bank); split
            // each scenario's count in half so B001..B125 lands per bank.
            foreach ($matrix as [$scenarioKey, $count, $daysAgoMin, $daysAgoMax]) {
                $perBankCount = intdiv($count, 2);

                for ($i = 0; $i < $perBankCount; $i++) {
                    $seq++;
                    $reference = SeederCatalog::bulkRef($bankCode, $seq);
                    $merchant = $merchants[($seq - 1) % $merchants->count()];
                    $daysAgo = $daysAgoMin + (($seq - 1) % max(1, $daysAgoMax - $daysAgoMin + 1));
                    $at = Carbon::now()->subDays($daysAgo);

                    $builder->buildBulk($reference, $scenarioKey, $bank, $merchant, $creator, $at);
                    $seeded++;
                }
            }
        }

        $this->command?->info(sprintf('Seeded %d demo engine request bulk rows.', $seeded));
    }

    /**
     * @param  Collection<string, Bank>  $banks
     * @return array<int, User>
     */
    private function resolveCreators(Collection $banks): array
    {
        $creators = [];

        foreach ($banks as $bank) {
            $creators[$bank->id] = User::query()
                ->where('bank_id', $bank->id)
                ->withUserRole(UserRole::DATA_ENTRY)
                ->firstOrFail();
        }

        return $creators;
    }
}
