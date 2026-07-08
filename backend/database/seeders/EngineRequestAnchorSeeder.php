<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\User;
use Database\Seeders\Catalog\SeederCatalog;
use Database\Seeders\Concerns\GuardsDemoSeedEnvironment;
use Database\Seeders\Support\EngineRequestScenarioBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seeds the 56 fixed engine request anchors (28 per bank) from
 * database/seeders/catalog/anchor-catalog.php.
 *
 * Runs unconditionally in every demo-enabled mode (minimal and full) — only
 * EngineRequestBulkSeeder is skipped under DEMO_SEED_SIZE=minimal.
 *
 * Spec: backend/docs/superpowers/specs/2026-07-07-engine-demo-seeder-redesign-design.md
 */
class EngineRequestAnchorSeeder extends Seeder
{
    use GuardsDemoSeedEnvironment;

    public function run(EngineRequestScenarioBuilder $builder): void
    {
        $this->ensureDemoSeedAllowed();

        $catalog = require base_path('database/seeders/catalog/anchor-catalog.php');

        $banks = Bank::query()->whereIn('code', ['YBRD', 'TIIB'])->get()->keyBy('code');
        $merchantsByBank = Merchant::query()
            ->whereIn('bank_id', $banks->pluck('id'))
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->get()
            ->groupBy('bank_id');
        $creatorsByBank = $this->resolveCreators($banks);

        $this->command?->info(sprintf('Seeding demo engine requests (%d anchors, 0 bulk)…', SeederCatalog::ANCHOR_COUNT));

        $seqPerBank = [];
        $specsByReference = [];

        foreach ($catalog as $spec) {
            $bank = $banks[$spec['bank']] ?? null;
            if ($bank === null) {
                continue;
            }

            $merchants = ($merchantsByBank->get($bank->id) ?? collect())->values();
            if ($merchants->isEmpty()) {
                continue;
            }

            $seq = $seqPerBank[$spec['bank']] = ($seqPerBank[$spec['bank']] ?? 0) + 1;
            $merchant = $merchants[($seq - 1) % $merchants->count()];
            $creator = $creatorsByBank[$bank->id];

            $request = $builder->buildAnchor($spec, $bank, $merchant, $creator);
            $specsByReference[$spec['reference']] = $request;
        }

        $this->applyDuplicatePair($builder, $specsByReference);

        $this->command?->info(sprintf('Seeded %d demo engine request anchors.', count($specsByReference)));
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

    /**
     * A023 anchors intentionally share a normalized invoice key across banks.
     *
     * @param  array<string, EngineRequest>  $specsByReference
     */
    private function applyDuplicatePair(EngineRequestScenarioBuilder $builder, array $specsByReference): void
    {
        $ybrd = $specsByReference[SeederCatalog::ANCHOR_DUPLICATE_YBRD] ?? null;
        $tiib = $specsByReference[SeederCatalog::ANCHOR_DUPLICATE_TIIB] ?? null;

        if ($ybrd === null || $tiib === null) {
            return;
        }

        $builder->applyDuplicatePair($ybrd, $tiib, 'INV-DUP-SEED-001');
    }
}
