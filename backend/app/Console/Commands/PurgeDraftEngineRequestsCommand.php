<?php

namespace App\Console\Commands;

use App\Models\EngineRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * LOCAL/STAGING/TESTING data-cleanup tooling for the request-draft removal
 * (draft save/abandon endpoints were deleted; a request is now created and
 * submitted in one call). Deletes ACTIVE engine_requests still sitting on
 * their workflow's initial stage — the legacy draft/edit surface — since
 * no such request can ever be reached through the new flow.
 *
 * Hard-blocked outside local/staging/testing. Per docs/production-guide.md
 * §"Retention & Compliance", audit_logs and workflow_history are
 * archive-only within their hot retention horizon; any hard-delete
 * deviation from archive-first requires explicit CBY sign-off. This
 * command performs that deviation deliberately for demo/test data only —
 * it must never run unattended against a database that can hold real
 * regulatory history.
 *
 * Synthetic-only guard: aborts if any matched row was ever the subject of
 * a STATUS_TRANSITION audit log (proof it left its initial stage at some
 * point), so a request that only *currently* sits on an initial stage due
 * to an in-flight correction/return is never silently destroyed.
 *
 * Dry-run by default; --execute performs the destructive step.
 */
class PurgeDraftEngineRequestsCommand extends Command
{
    protected $signature = 'workflow:purge-draft-requests
        {--execute : Perform the destructive purge. Without this flag the command is a dry-run.}';

    protected $description = 'Delete ACTIVE engine_requests still on their initial stage (legacy draft rows). Local/staging/testing only.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            $this->error('Restricted to local/staging/testing — audit_logs/workflow_history are archive-only per retention policy; this command performs a hard-delete deviation that requires explicit CBY sign-off before it may touch a production-capable database.');

            return self::FAILURE;
        }

        $draftIds = DB::table('engine_requests')
            ->join('workflow_stages', 'engine_requests.current_stage_id', '=', 'workflow_stages.id')
            ->where('engine_requests.status', 'ACTIVE')
            ->where('workflow_stages.is_initial', true)
            ->pluck('engine_requests.id')
            ->all();

        if ($draftIds === []) {
            $this->info('No initial-stage ACTIVE requests found. Nothing to purge.');

            return self::SUCCESS;
        }

        // Synthetic/never-transitioned guard: a request that ever recorded a
        // STATUS_TRANSITION proves it left its initial stage at some point in
        // its history. If it is back on an initial stage now, that is either a
        // correction/return flow or a data anomaly — not a legacy draft that
        // was simply never submitted. Abort rather than guess.
        $everTransitioned = DB::table('audit_logs')
            ->where('action', 'STATUS_TRANSITION')
            ->whereIn('subject_id', $draftIds)
            ->where('subject_type', EngineRequest::class)
            ->pluck('subject_id')
            ->unique()
            ->all();

        if ($everTransitioned !== []) {
            $this->error(sprintf(
                'Aborting: %d matched request(s) have a STATUS_TRANSITION audit entry (ids: %s) — they left their initial stage at least once and are not legacy drafts. Investigate before purging.',
                count($everTransitioned),
                implode(', ', $everTransitioned),
            ));

            return self::FAILURE;
        }

        $historyCount = DB::table('workflow_history')->whereIn('request_id', $draftIds)->count();
        $documentCount = DB::table('engine_request_documents')->whereIn('request_id', $draftIds)->count();
        $auditCount = DB::table('audit_logs')->whereIn('workflow_instance_id', $draftIds)->count();

        $this->table(['request_id', 'reference', 'workflow_version_id', 'created_by', 'created_at'], EngineRequest::query()
            ->whereIn('id', $draftIds)
            ->get(['id', 'reference', 'workflow_version_id', 'created_by', 'created_at'])
            ->map(fn (EngineRequest $r) => [$r->id, $r->reference, $r->workflow_version_id, $r->created_by, $r->created_at])
            ->all());

        $this->info(sprintf(
            'env=%s · initial-stage requests=%d · workflow_history rows=%d · documents=%d · audit_logs to detach=%d · mode=%s',
            app()->environment(), count($draftIds), $historyCount, $documentCount, $auditCount,
            $this->option('execute') ? 'EXECUTE' : 'DRY-RUN',
        ));

        if (! $this->option('execute')) {
            $this->info('Dry run: no changes made. Re-run with --execute to perform the purge.');

            return self::SUCCESS;
        }

        if (! $this->confirm(sprintf('Hard-delete %d request(s) and %d workflow_history row(s)? This cannot be undone.', count($draftIds), $historyCount))) {
            $this->info('Aborted by operator.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($draftIds): void {
            // audit_logs.workflow_instance_id is a nullable FK (nullOnDelete) —
            // detach explicitly first so the audit row itself survives (never
            // hard-deleted; only its request linkage is cleared).
            DB::table('audit_logs')->whereIn('workflow_instance_id', $draftIds)->update(['workflow_instance_id' => null]);
            $deleted = DB::table('engine_requests')->whereIn('id', $draftIds)->delete();
            $this->info("Deleted {$deleted} initial-stage request(s).");
        });

        return self::SUCCESS;
    }
}
