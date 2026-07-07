<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Services\Operations\SchedulerRunLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminHealthController extends Controller
{
    /** @var list<string> */
    private const RETENTION_COMMANDS = [
        'notifications:purge-old',
        'reports:purge-old-exports',
        'documents:purge-orphans',
        'documents:archive-superseded',
        'audit:archive-old',
    ];

    public function index(Request $request, SchedulerRunLogger $logger)
    {
        Gate::authorize('cbyAdmin', $request->user());

        $scheduler = [];
        foreach (config('retention.scheduler_stale_minutes') as $command => $minutes) {
            $last = $logger->lastRun($command);
            $stale = $last === null || $last->ran_at->lt(now()->subMinutes($minutes));

            $scheduler[] = [
                'command' => $command,
                'last_ran_at' => $last?->ran_at?->toIso8601String(),
                'status' => $last?->status,
                'stale' => $stale,
            ];
        }

        $recentFailures = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(10)
            ->get(['id', 'connection', 'queue', 'failed_at'])
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'connection' => $row->connection,
                'queue' => $row->queue,
                'failed_at' => $row->failed_at,
            ])
            ->values()
            ->all();

        $retentionLastRuns = [];
        foreach (self::RETENTION_COMMANDS as $command) {
            $last = $logger->lastRun($command);
            $retentionLastRuns[$command] = $last?->ran_at?->toIso8601String();
        }

        return ApiResponse::success([
            'scheduler' => $scheduler,
            'queue' => [
                'failed_jobs_count' => DB::table('failed_jobs')->count(),
                'recent_failures' => $recentFailures,
            ],
            'retention' => [
                'last_runs' => $retentionLastRuns,
            ],
            'mail' => [
                'driver' => config('mail.default'),
            ],
        ], 'System health retrieved.');
    }
}
