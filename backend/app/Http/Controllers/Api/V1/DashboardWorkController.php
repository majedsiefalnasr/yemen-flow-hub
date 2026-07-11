<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Services\Workflow\UserActionableRequestQuery;
use App\Support\ApiResponse;
use App\Support\EngineRequestReadModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The generic work dashboard (Phase D0). One endpoint for every workflow user;
 * its content is derived from the current user's authorization + workflow
 * metadata, never from a role code. The `actionable` section is the exact record
 * set /my-queue returns (both resolve through UserActionableRequestQuery), so the
 * dashboard count, preview, nav badge, and my-queue never disagree.
 */
class DashboardWorkController extends Controller
{
    private const PREVIEW_LIMIT = 10;

    public function __construct(
        private UserActionableRequestQuery $actionable,
    ) {}

    public function work(Request $request): JsonResponse
    {
        $user = $request->user();

        $actionableItems = $this->actionable->actionablePreview($user, $request, self::PREVIEW_LIMIT);
        $trackingItems = $this->actionable->trackingPreview($user, $request, self::PREVIEW_LIMIT);
        $claimedItems = $this->actionable->claimedPreview($user, $request, self::PREVIEW_LIMIT);

        return ApiResponse::success([
            'actionable' => [
                'count' => $this->actionable->actionableCount($user, $request),
                'items' => EngineRequestReadModel::resourceCollection($actionableItems),
                'queue_url' => '/workflows?queue=mine',
            ],
            'claimed' => [
                'count' => $this->actionable->claimedCount($user, $request),
                'items' => EngineRequestReadModel::resourceCollection($claimedItems),
            ],
            'tracking' => [
                'count' => $this->actionable->trackingCount($user, $request),
                'items' => EngineRequestReadModel::resourceCollection($trackingItems),
                'queue_url' => '/workflows?scope=all',
            ],
            'sla' => $this->actionable->slaCounts($user, $request),
            // Level-1 sections the backend fills as data/capabilities become
            // available; empty is a valid "nothing to show" state, not an error.
            'recent_activity' => [],
            'metrics' => [],
        ], 'Work dashboard retrieved.');
    }
}
