<?php

namespace App\Providers;

use App\Services\Authorization\PermissionService;
use App\Services\Documents\PdfGeneratorService;
use App\Services\Workflow\Effects\CustomsFxPdfEffect;
use App\Services\Workflow\Effects\FinancingLedgerEffect;
use App\Services\Workflow\StageHookRegistry;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionService::class, PermissionService::class);
        $this->app->singleton(PdfGeneratorService::class, PdfGeneratorService::class);
        // The engine stage-hook registry must be a singleton: domain effects are
        // registered once at boot and resolved by EngineTransitionService on every
        // transition. A transient binding would discard registered hooks.
        $this->app->singleton(StageHookRegistry::class, StageHookRegistry::class);
    }

    public function boot(): void
    {
        $this->registerEngineStageHooks();

        if (! $this->app->environment('production')) {
            return;
        }

        $mailer = (string) config('mail.default');
        $smtpHost = strtolower((string) config('mail.mailers.smtp.host'));

        if (
            $mailer !== 'smtp'
            || in_array($smtpHost, ['mailpit', 'localhost', '127.0.0.1'], true)
        ) {
            throw new RuntimeException(
                'Production mail must use the approved CBY/government SMTP server.'
            );
        }
    }

    /**
     * Bind DI-4 domain side-effects to engine stage entry by configured stage code.
     * The registry is a singleton, so these run on every engine transition.
     */
    private function registerEngineStageHooks(): void
    {
        $registry = $this->app->make(StageHookRegistry::class);

        $registry->onEffectEntry(
            'financing.reserve',
            fn ($request, $transition, $actor) => $this->app->make(FinancingLedgerEffect::class)($request, $transition, $actor),
        );

        $registry->onEffectEntry(
            'fx.confirmation_pdf',
            fn ($request, $transition, $actor) => $this->app->make(CustomsFxPdfEffect::class)($request, $transition, $actor),
        );

        // Legacy config-stage bootstrap for workflows without attached_effects yet.
        $registry->onStageEntry(
            (string) config('engine_hooks.financing_reserve_stage'),
            fn ($request, $transition, $actor) => $this->app->make(FinancingLedgerEffect::class)($request, $transition, $actor),
        );

        $registry->onStageEntry(
            (string) config('engine_hooks.fx_pdf_stage'),
            fn ($request, $transition, $actor) => $this->app->make(CustomsFxPdfEffect::class)($request, $transition, $actor),
        );
    }
}
