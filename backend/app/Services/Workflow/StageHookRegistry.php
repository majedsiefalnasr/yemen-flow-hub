<?php

namespace App\Services\Workflow;

use App\Models\EngineRequest;
use App\Models\User;
use App\Models\WorkflowTransition;

class StageHookRegistry
{
    /** @var array<string, callable[]> */
    private array $entryHooks = [];

    /** @var array<string, callable[]> */
    private array $exitHooks = [];

    public function onStageEntry(string $stageCode, callable $handler): void
    {
        $this->entryHooks[$stageCode][] = $handler;
    }

    public function onStageExit(string $stageCode, callable $handler): void
    {
        $this->exitHooks[$stageCode][] = $handler;
    }

    public function fireExit(EngineRequest $request, WorkflowTransition $transition, User $actor): void
    {
        $fromCode = $transition->fromStage?->code;
        if ($fromCode === null) {
            return;
        }

        foreach ($this->exitHooks[$fromCode] ?? [] as $handler) {
            $handler($request, $transition, $actor);
        }
    }

    public function fireEntry(EngineRequest $request, WorkflowTransition $transition, User $actor): void
    {
        $toCode = $transition->toStage?->code;
        if ($toCode === null) {
            return;
        }

        foreach ($this->entryHooks[$toCode] ?? [] as $handler) {
            $handler($request, $transition, $actor);
        }
    }
}
