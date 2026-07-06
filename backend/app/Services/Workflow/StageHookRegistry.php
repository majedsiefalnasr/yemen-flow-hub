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

    /** @var array<string, callable[]> */
    private array $effectEntryHooks = [];

    public function onStageEntry(string $stageCode, callable $handler): void
    {
        $this->entryHooks[$stageCode][] = $handler;
    }

    public function onStageExit(string $stageCode, callable $handler): void
    {
        $this->exitHooks[$stageCode][] = $handler;
    }

    public function onEffectEntry(string $effectCode, callable $handler): void
    {
        $this->effectEntryHooks[$effectCode][] = $handler;
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
        $transition->loadMissing('toStage');
        $toStage = $transition->toStage;
        if ($toStage === null) {
            return;
        }

        $effects = $toStage->attached_effects ?? [];
        if ($effects !== []) {
            foreach ($effects as $effectCode) {
                foreach ($this->effectEntryHooks[(string) $effectCode] ?? [] as $handler) {
                    $handler($request, $transition, $actor);
                }
            }

            return;
        }

        $toCode = $toStage->code;
        foreach ($this->entryHooks[$toCode] ?? [] as $handler) {
            $handler($request, $transition, $actor);
        }
    }
}
