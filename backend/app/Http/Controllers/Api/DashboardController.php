<?php

namespace App\Http\Controllers\Api;

use App\Services\Dashboard\DashboardStatsService;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStatsService $statsService) {}

    #[OA\Get(
        path: '/api/dashboard/stats',
        tags: ['Dashboard'],
        summary: 'Dashboard stats',
        description: 'Returns role-scoped dashboard metrics.',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard stats retrieved'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function stats()
    {
        return $this->statsService->stats(request()->user());
    }
}
