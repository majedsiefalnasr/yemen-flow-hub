<?php

namespace App\Providers;

use App\Services\Authorization\PermissionService;
use App\Services\Customs\FxConfirmationAuthorizationService;
use App\Services\Documents\PdfGeneratorService;
use App\Services\Settings\SettingResolver;
use App\Services\Workflow\Effects\CustomsFxPdfEffect;
use App\Services\Workflow\Effects\FinancingLedgerEffect;
use App\Services\Workflow\StageFieldOutputFilter;
use App\Services\Workflow\StageHookRegistry;
use App\Support\QueryMetrics;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionService::class, PermissionService::class);
        $this->app->singleton(PdfGeneratorService::class, PdfGeneratorService::class);
        $this->app->singleton(SettingResolver::class);
        // The engine stage-hook registry must be a singleton: domain effects are
        // registered once at boot and resolved by EngineTransitionService on every
        // transition. A transient binding would discard registered hooks.
        $this->app->singleton(StageHookRegistry::class, StageHookRegistry::class);
        // Both resources are resolved fresh per row when serializing engine-request
        // list responses (EngineRequestResource::toArray). Binding them as
        // container singletons lets their internal per-(workflow_version_id,
        // stage_id) memoization actually persist across rows within a single
        // request, instead of resetting on every app() call.
        $this->app->singleton(StageFieldOutputFilter::class, StageFieldOutputFilter::class);
        $this->app->singleton(FxConfirmationAuthorizationService::class, FxConfirmationAuthorizationService::class);
        // OBS-001: one counter instance per request, fed by the DB::listen hook
        // registered in boot() below.
        $this->app->singleton(QueryMetrics::class, QueryMetrics::class);
    }

    public function boot(): void
    {
        $this->registerEngineStageHooks();
        $this->registerApiRateLimiter();
        $this->registerQueryMetricsListener();

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
     * ARCH-003: default request cap for the authenticated API groups. Keyed per
     * authenticated user (falling back to client IP), so it bounds a single
     * client's volume against the expensive list/report/dashboard endpoints
     * without a shared global bucket. Per-route throttles (uploads, admin writes)
     * stack on top and remain the tighter limit where present.
     */
    private function registerApiRateLimiter(): void
    {
        RateLimiter::for('api-default', function (Request $request) {
            $perMinute = (int) config('auth_security.api_throttle_per_minute', 120);

            return Limit::perMinute($perMinute)->by(
                $request->user()?->getAuthIdentifier() !== null
                    ? 'user:'.$request->user()->getAuthIdentifier()
                    : 'ip:'.$request->ip(),
            );
        });
    }

    /**
     * OBS-001: accumulate every executed query into the request-scoped
     * QueryMetrics singleton so query-count regressions (e.g. N+1) are
     * assertable in tests and surfaced via response headers in non-production.
     */
    private function registerQueryMetricsListener(): void
    {
        DB::listen(function (QueryExecuted $event) {
            $this->app->make(QueryMetrics::class)->record($event);
        });
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
