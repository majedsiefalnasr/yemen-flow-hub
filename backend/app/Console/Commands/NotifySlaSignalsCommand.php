<?php

namespace App\Console\Commands;

use App\Models\EngineNotification;
use App\Models\EngineRequest;
use App\Services\Notifications\EngineNotificationDispatcher;
use Illuminate\Console\Command;

class NotifySlaSignalsCommand extends Command
{
    protected $signature = 'workflow:notify-sla-signals';

    protected $description = 'Notify the oversight audience of requests that have breached or are nearing their stage SLA';

    public function handle(EngineNotificationDispatcher $dispatcher): void
    {
        $requests = EngineRequest::query()
            ->withStageEntry()
            ->active()
            ->whereNotNull('current_stage.sla_duration_minutes')
            ->with('currentStage:id,code,name,sla_duration_minutes')
            ->get();

        $sent = 0;

        foreach ($requests as $request) {
            $status = $request->sla_status;
            if ($status !== 'breached' && $status !== 'nearing') {
                continue;
            }

            $type = $status === 'breached' ? 'sla.breached' : 'sla.nearing';

            // Dedup: do not re-emit the same SLA signal for the same request while one is
            // still outstanding. A new signal is sent only if its status escalates.
            $alreadyNotified = EngineNotification::query()
                ->where('entity_type', 'engine_request')
                ->where('entity_id', $request->id)
                ->where('type', $type)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $dispatcher->afterSlaSignal(
                $request->id,
                (string) $request->reference,
                $status,
                $request->currentStage?->name ?? '',
            );

            $sent++;
        }

        $this->info("Dispatched {$sent} SLA signal notification(s).");
    }
}
