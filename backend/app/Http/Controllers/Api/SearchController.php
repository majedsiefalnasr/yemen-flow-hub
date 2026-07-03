<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\EngineRequestReadModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SearchController extends Controller
{
    private const MIN_QUERY_LENGTH = 2;

    private const MAX_RESULTS_PER_GROUP = 10;

    private const MAX_RECENT_SEARCHES = 10;

    #[OA\Get(
        path: '/api/search',
        tags: ['Search'],
        summary: 'Global search across role-scoped entities',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        parameters: [new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Search results')]
    )]
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $user = $request->user();

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return ApiResponse::success([
                'requests' => [],
                'users' => [],
                'banks' => [],
                'customs' => [],
            ], 'Search results.');
        }

        $results = [
            'requests' => $this->searchRequests($user, $query),
            'users' => $this->searchUsers($user, $query),
            'banks' => $this->searchBanks($user, $query),
            'customs' => $this->searchCustoms($user, $query),
        ];

        $this->persistRecentSearch($user, $query);

        return ApiResponse::success($results, 'Search results.');
    }

    #[OA\Get(
        path: '/api/search/recent',
        tags: ['Search'],
        summary: 'Get recent searches for authenticated user',
        security: [['bearerAuth' => []], ['sanctumCookie' => []]],
        responses: [new OA\Response(response: 200, description: 'Recent searches')]
    )]
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $prefs = $user->user_preferences ?? [];
        $recent = $prefs['recent_searches'] ?? [];

        return ApiResponse::success(['recent_searches' => $recent], 'Recent searches.');
    }

    private function searchRequests(User $user, string $query): array
    {
        $like = "%{$query}%";

        $results = EngineRequestReadModel::queryFor($user)
            ->where(function ($q) use ($like): void {
                $q->where('engine_requests.reference', 'like', $like)
                    ->orWhere('engine_requests.invoice_number', 'like', $like)
                    ->orWhereHas('merchant', fn ($m) => $m->where('name', 'like', $like))
                    ->orWhereHas('bank', fn ($b) => $b->where('name', 'like', $like));
            })
            ->limit(self::MAX_RESULTS_PER_GROUP)
            ->get();

        return EngineRequestReadModel::resourceCollection($results);
    }

    private function searchUsers(User $user, string $query): array
    {
        if (! $user->hasAnyRoleCode(['system_admin', 'bank_admin'])) {
            return [];
        }

        $like = "%{$query}%";

        $userQuery = User::query()
            ->with(['bank'])
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });

        if ($user->hasRoleCode('bank_admin')) {
            if (! $user->bank_id) {
                return [];
            }

            $userQuery->where('bank_id', $user->bank_id)
                ->whereHas('roles', fn ($q) => $q->whereIn('code', ['intake', 'internal_reviewer']));
        }

        return UserResource::collection(
            $userQuery->limit(self::MAX_RESULTS_PER_GROUP)->get()
        )->resolve();
    }

    private function searchBanks(User $user, string $query): array
    {
        if (! $user->hasRoleCode('system_admin')) {
            return [];
        }

        $like = "%{$query}%";

        $banks = Bank::query()
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like);
            })
            ->limit(self::MAX_RESULTS_PER_GROUP)
            ->get();

        return $banks->map(fn (Bank $bank) => [
            'id' => $bank->id,
            'name' => $bank->name,
            'code' => $bank->code,
            'is_active' => $bank->is_active,
        ])->values()->all();
    }

    private function searchCustoms(User $user, string $query): array
    {
        $like = "%{$query}%";

        $customsQuery = CustomsDeclaration::query()
            ->with(['engineRequest'])
            ->where('declaration_number', 'like', $like);

        if ($user->hasAnyRoleCode(['intake', 'internal_reviewer', 'bank_admin', 'fx_swift'])) {
            $customsQuery->whereHas('engineRequest', fn ($q) => $q->where('bank_id', $user->bank_id));
        }

        $declarations = $customsQuery->limit(self::MAX_RESULTS_PER_GROUP)->get();

        // Output keys 'request_id'/'reference_number' are the public API contract
        // (see frontend GlobalSearch.vue); only the underlying relation/column
        // names changed below (engine_request_id/reference).
        return $declarations->map(fn (CustomsDeclaration $d) => [
            'id' => $d->id,
            'declaration_number' => $d->declaration_number,
            'issued_at' => $d->issued_at?->toISOString(),
            'request_id' => $d->engine_request_id,
            'reference_number' => $d->engineRequest?->reference,
        ])->values()->all();
    }

    private function persistRecentSearch(User $user, string $query): void
    {
        try {
            $prefs = $user->user_preferences ?? [];
            $recent = $prefs['recent_searches'] ?? [];

            // dedup: remove existing occurrence, prepend new
            $recent = array_values(array_filter($recent, fn ($item) => $item !== $query));
            array_unshift($recent, $query);
            $recent = array_slice($recent, 0, self::MAX_RECENT_SEARCHES);

            $prefs['recent_searches'] = $recent;
            $user->user_preferences = $prefs;
            $user->saveQuietly();
        } catch (\Throwable) {
            // fire-and-forget: do not fail the search response if preferences write fails
        }
    }
}
