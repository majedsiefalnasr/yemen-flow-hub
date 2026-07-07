<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsSchedulerHeartbeat;
use App\Services\Operations\AuditLogArchiveService;
use Illuminate\Console\Command;

class ArchiveOldAuditLogsCommand extends Command
{
    use RecordsSchedulerHeartbeat;

    protected $signature = 'audit:archive-old';

    protected $description = 'Archive audit logs older than the hot retention horizon';

    public function handle(AuditLogArchiveService $archiveService): int
    {
        return $this->runWithHeartbeat(fn (): int => $archiveService->archiveBatch());
    }
}
