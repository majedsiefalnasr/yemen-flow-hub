<?php

use App\Enums\AuditAction;
use App\Exceptions\CustomsException;
use App\Exceptions\DocumentException;
use App\Exceptions\DuplicateInvoiceMismatchException;
use App\Exceptions\FinancingLimitExceededException;
use App\Exceptions\FinancingLockTimeoutException;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\SelfReviewException;
use App\Exceptions\UnauthorizedTransitionException;
use App\Http\Middleware\AttachQueryMetricsHeaders;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureActiveUser;
use App\Services\Audit\AuditService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(HandleCors::class);

        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', null),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->alias([
            'auth' => Authenticate::class,
            'active' => EnsureActiveUser::class,
        ]);

        // OBS-001: prepend before EnsureFrontendRequestsAreStateful so the
        // reset happens before Sanctum/auth resolution runs any queries —
        // the header must reflect the full request, not just the controller.
        $middleware->api(prepend: [
            AttachQueryMetricsHeaders::class,
            EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $auditAuthorizationFailure = function (Throwable $e, Request $request): void {
            try {
                app(AuditService::class)->log(
                    AuditAction::AUTHORIZATION_FAILURE,
                    $request->user(),
                    null,
                    ['reason' => $e->getMessage(), 'path' => $request->path(), 'method' => $request->method()]
                );
            } catch (Throwable) {
                // Never let audit failure suppress the actual response
            }
        };

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::validationError($e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::unauthorized();
            }
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('CSRF token mismatch.', [], 419);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($auditAuthorizationFailure) {
            if ($request->is('api/*') && $e->getStatusCode() === 419) {
                return ApiResponse::error('CSRF token mismatch.', [], 419);
            }

            if ($request->is('api/*') && $e->getStatusCode() === 403) {
                if ($e instanceof AccessDeniedHttpException && $e->getPrevious() instanceof AuthorizationException) {
                    $auditAuthorizationFailure($e, $request);
                }

                return ApiResponse::forbidden('Forbidden action', 'WORKFLOW_FORBIDDEN');
            }
        });

        // AuthorizationException is converted to AccessDeniedHttpException by the framework
        // before reaching render callbacks, so both types must be listed.
        $exceptions->render(function (AccessDeniedHttpException|AuthorizationException $e, Request $request) use ($auditAuthorizationFailure) {
            if ($request->is('api/*')) {
                $isDomainAuthorization = $e instanceof AuthorizationException
                    || $e->getPrevious() instanceof AuthorizationException;

                if ($isDomainAuthorization) {
                    $auditAuthorizationFailure($e, $request);
                }

                return ApiResponse::forbidden('Forbidden action', 'WORKFLOW_FORBIDDEN');
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::notFound();
            }
        });

        $exceptions->render(function (InvalidTransitionException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), [], 422);
            }
        });

        $exceptions->render(function (UnauthorizedTransitionException|SelfReviewException $e, Request $request) use ($auditAuthorizationFailure) {
            if ($request->is('api/*')) {
                $auditAuthorizationFailure($e, $request);

                return ApiResponse::forbidden($e->getMessage(), 'WORKFLOW_FORBIDDEN');
            }
        });

        $exceptions->render(function (DocumentException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), [], 422);
            }
        });

        $exceptions->render(function (FinancingLimitExceededException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), [], 422, FinancingLimitExceededException::ERROR_CODE);
            }
        });

        $exceptions->render(function (FinancingLockTimeoutException $e, Request $request) {
            if ($request->is('api/*')) {
                // 409 Conflict — retryable contention, not a server error.
                return ApiResponse::error($e->getMessage(), [], 409, FinancingLockTimeoutException::ERROR_CODE);
            }
        });

        $exceptions->render(function (DuplicateInvoiceMismatchException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), [], 422, DuplicateInvoiceMismatchException::ERROR_CODE);
            }
        });

        $exceptions->render(function (CustomsException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), [], 422);
            }
        });

        $exceptions->render(function (LogicException $e, Request $request) {
            if ($request->is('api/*') && str_contains($e->getMessage(), 'immutable')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_code' => 'WORKFLOW_IMMUTABLE_STATE',
                ], 403);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                // Keep Retry-After / X-RateLimit-* from the limiter so clients
                // can back off for the exact remaining window instead of guessing.
                return ApiResponse::error('Too many requests. Please try again later.', [], 429, 'RATE_LIMITED')
                    ->withHeaders($e->getHeaders());
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Internal server error.', [], 500);
            }
        });
    })->create();
