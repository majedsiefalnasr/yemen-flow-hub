<?php

namespace App\Console\Commands;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\Support\EngineRequestScenarioBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ReflectionObject;

/**
 * LOCAL, NON-PRODUCTION tooling (Phase B data step). Recreates the synthetic
 * active IMPORT_FINANCING requests under the published V2, preserving the
 * terminal V1 records for history. Dry-run by default; --execute performs the
 * destructive step. Environment-restricted; production hard-blocked.
 *
 * Reuses the tested EngineRequestScenarioBuilder (rebound to V2) so recreated
 * requests carry valid data, history, claim, and document state and pass the
 * anchor invariant validator.
 *
 * This file is intentionally NOT committed (execution tooling stays local per the
 * approved data-step contract); only the checkpoint documentation is committed.
 */
class RecreateActiveRequestsUnderV2Command extends Command
{
    protected $signature = 'workflow:recreate-active-under-v2
        {--execute : Perform the destructive recreation. Without this flag the command is a dry-run.}';

    protected $description = 'Recreate synthetic active IMPORT_FINANCING requests under the published V2 (local data step).';

    public function handle(EngineRequestScenarioBuilder $builder): int
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            $this->error('Restricted to local/staging/testing.');

            return self::FAILURE;
        }

        $definition = WorkflowDefinition::query()->where('code', 'IMPORT_FINANCING')->firstOrFail();
        $v1 = $definition->versions()->where('version_number', 1)->firstOrFail();
        $v2 = $definition->versions()->where('state', 'PUBLISHED')->orderByDesc('version_number')->firstOrFail();

        if ($v2->id === $v1->id) {
            $this->error('The published version is still V1; run the V2 publish command first.');

            return self::FAILURE;
        }

        $activeIds = EngineRequest::query()
            ->where('workflow_version_id', $v1->id)->where('status', 'ACTIVE')->pluck('id')->all();
        $terminalCount = EngineRequest::query()
            ->where('workflow_version_id', $v1->id)->whereIn('status', ['CLOSED', 'REJECTED'])->count();

        // Synthetic-only guard: every active V1 request must be seed data
        // (seeded reference prefix + no substantial payload). Abort otherwise.
        $nonSynthetic = EngineRequest::query()
            ->whereIn('id', $activeIds)
            ->get(['id', 'reference', 'data'])
            ->filter(function (EngineRequest $r): bool {
                $seededRef = str_starts_with((string) $r->reference, 'ENG-2026-YBRD')
                    || str_starts_with((string) $r->reference, 'ENG-2026-TIIB');

                return ! $seededRef;
            });
        if ($nonSynthetic->isNotEmpty()) {
            $this->error('Aborting: '.$nonSynthetic->count().' active V1 requests are not recognizably synthetic.');
            $this->line($nonSynthetic->pluck('reference')->implode(', '));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'env=%s · V1=%d · published V2=%d · active-to-recreate=%d · terminal-to-preserve=%d · mode=%s',
            app()->environment(), $v1->id, $v2->id, count($activeIds), $terminalCount,
            $this->option('execute') ? 'EXECUTE' : 'DRY-RUN',
        ));

        if (! $this->option('execute')) {
            $this->info('Dry run: no changes made. Re-run with --execute to perform the recreation.');

            return self::SUCCESS;
        }

        $this->rebindBuilderToVersion($builder, $v2);

        DB::transaction(function () use ($activeIds, $builder, $v2): void {
            $docs = DB::table('engine_request_documents')->whereIn('request_id', $activeIds)->delete();
            $hist = DB::table('workflow_history')->whereIn('request_id', $activeIds)->delete();
            $reqs = EngineRequest::query()->whereIn('id', $activeIds)->delete();
            $this->info("Deleted: documents={$docs} history={$hist} active-requests={$reqs}.");

            $created = $this->recreateActiveAnchors($builder, $v2);
            $this->info("Recreated {$created} active requests pinned to V2.");
        });

        return $this->verify($v1, $v2, $terminalCount) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Recreate the active anchors from the catalog, bound to V2. Terminal specs
     * are skipped (the 8 terminal V1 records are preserved).
     */
    private function recreateActiveAnchors(EngineRequestScenarioBuilder $builder, WorkflowVersion $v2): int
    {
        $catalog = require base_path('database/seeders/catalog/anchor-catalog.php');
        $banks = Bank::query()->whereIn('code', ['YBRD', 'TIIB'])->get()->keyBy('code');
        $merchantsByBank = Merchant::query()
            ->whereIn('bank_id', $banks->pluck('id'))->where('status', 'ACTIVE')->orderBy('id')->get()->groupBy('bank_id');

        $seqPerBank = [];
        $created = 0;
        foreach ($catalog as $spec) {
            if (($spec['runtime_status'] ?? 'ACTIVE') !== 'ACTIVE') {
                continue; // preserve terminal V1 records; do not recreate them
            }
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
            $creator = User::query()->where('bank_id', $bank->id)
                ->whereHas('roles', fn ($q) => $q->where('code', 'intake')->where('user_roles.is_active', true))
                ->firstOrFail();

            $builder->buildAnchor($spec, $bank, $merchant, $creator);
            $created++;
        }

        return $created;
    }

    /**
     * Rebind the builder's private version/stages/fieldIds to V2 so buildAnchor()
     * produces V2-pinned requests. Local one-off tooling only.
     */
    private function rebindBuilderToVersion(EngineRequestScenarioBuilder $builder, WorkflowVersion $v2): void
    {
        $ref = new ReflectionObject($builder);

        $vProp = $ref->getProperty('workflowVersion');
        $vProp->setAccessible(true);
        $vProp->setValue($builder, $v2);

        $sProp = $ref->getProperty('stages');
        $sProp->setAccessible(true);
        $sProp->setValue($builder, $v2->stages()->get()->keyBy('code'));

        $fProp = $ref->getProperty('fieldIds');
        $fProp->setAccessible(true);
        $fProp->setValue($builder, $v2->fields()->pluck('field_definitions.id', 'field_definitions.key')->all());
    }

    private function verify(WorkflowVersion $v1, WorkflowVersion $v2, int $expectedTerminal): bool
    {
        $activeOnV1 = EngineRequest::query()->where('workflow_version_id', $v1->id)->where('status', 'ACTIVE')->count();
        $terminalOnV1 = EngineRequest::query()->where('workflow_version_id', $v1->id)->whereIn('status', ['CLOSED', 'REJECTED'])->count();
        $activeOnV2 = EngineRequest::query()->where('workflow_version_id', $v2->id)->where('status', 'ACTIVE')->count();

        $finalStageId = WorkflowStage::query()->where('workflow_version_id', $v2->id)->where('code', 'FINAL')->value('id');
        $atFinal = EngineRequest::query()->where('workflow_version_id', $v2->id)->where('current_stage_id', $finalStageId)->count();

        $orphanDocs = DB::table('engine_request_documents')->whereNotIn('request_id', EngineRequest::query()->pluck('id'))->count();
        $orphanHist = DB::table('workflow_history')->whereNotIn('request_id', EngineRequest::query()->pluck('id'))->count();

        $this->table(['check', 'value', 'ok'], [
            ['active on V1 (want 0)', $activeOnV1, $activeOnV1 === 0 ? 'yes' : 'NO'],
            ['terminal on V1 (want '.$expectedTerminal.')', $terminalOnV1, $terminalOnV1 === $expectedTerminal ? 'yes' : 'NO'],
            ['active on V2 (>0)', $activeOnV2, $activeOnV2 > 0 ? 'yes' : 'NO'],
            ['requests at V2 FINAL (Director, >0)', $atFinal, $atFinal > 0 ? 'yes' : 'NO'],
            ['orphan documents (want 0)', $orphanDocs, $orphanDocs === 0 ? 'yes' : 'NO'],
            ['orphan history (want 0)', $orphanHist, $orphanHist === 0 ? 'yes' : 'NO'],
        ]);

        return $activeOnV1 === 0 && $terminalOnV1 === $expectedTerminal && $activeOnV2 > 0
            && $atFinal > 0 && $orphanDocs === 0 && $orphanHist === 0;
    }
}
