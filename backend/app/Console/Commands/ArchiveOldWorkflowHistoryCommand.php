<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsSchedulerHeartbeat;
use App\Services\Operations\WorkflowHistoryArchiveService;
use Illuminate\Console\Command;

class ArchiveOldWorkflowHistoryCommand extends Command
{
    use RecordsSchedulerHeartbeat;

    protected $signature = 'workflow-history:archive-old';

    protected $description = 'Archive workflow_history rows older than the hot retention horizon for non-active requests';

    public function handle(WorkflowHistoryArchiveService $archiveService): int
    {
        return $this->runWithHeartbeat(fn (): int => $archiveService->archiveBatch());
    }
}
