<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use App\Support\ApiResponse;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\SelfReviewException;
use App\Exceptions\UnauthorizedTransitionException;
use App\Exceptions\DuplicateVoteException;
use App\Exceptions\VotingException;
use App\Exceptions\DocumentException;
use App\Exceptions\CustomsException;
use App\Exceptions\WorkflowLockedStateException;
use App\Exceptions\WorkflowImmutableStateException;
use App\Http\Middleware\Authenticate;
use App\Enums\AuditAction;
use App\Services\Audit\AuditService;

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
        ]);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
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

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') && $e->getStatusCode() === 419) {
                return ApiResponse::error('CSRF token mismatch.', [], 419);
            }
        });

        // AuthorizationException is converted to AccessDeniedHttpException by the framework
        // before reaching render callbacks, so both types must be listed.
        $exceptions->render(function (AccessDeniedHttpException|AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                try {
                    app(AuditService::class)->log(
                        AuditAction::AUTHORIZATION_FAILURE,
                        $request->user(),
                        null,
                        ['reason' => $e->getMessage(), 'path' => $request->path(), 'method' => $request->method()]
                    );
                } catch (\Throwable) {
                    // Never let audit failure suppress the actual response
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

        $exceptions->render(function (UnauthorizedTransitionException|SelfReviewException $e, Request $request) {
            if ($request->is('api/*')) {
                try {
                    app(AuditService::class)->log(
                        AuditAction::AUTHORIZATION_FAILURE,
                        $request->user(),
                        null,
                        ['reason' => $e->getMessage(), 'path' => $request->path(), 'method' => $request->method()]
                    );
                } catch (\Throwable) {
                    // Never let audit failure suppress the actual response
                }
                return ApiResponse::forbidden($e->getMessage(), 'WORKFLOW_FORBIDDEN');
            }
        });

        $exceptions->render(function (DuplicateVoteException|VotingException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), [], 422);
            }
        });

        $exceptions->render(function (DocumentException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), [], 422);
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

        $exceptions->render(function (WorkflowLockedStateException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_code' => 'WORKFLOW_LOCKED_STATE',
                ], 422);
            }
        });

        $exceptions->render(function (WorkflowImmutableStateException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_code' => 'WORKFLOW_IMMUTABLE_STATE',
                    'current_status' => $e->currentStatus->value,
                ], 403);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Too many requests. Please try again later.', [], 429);
            }
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Internal server error.', [], 500);
            }
        });
    })->create();
