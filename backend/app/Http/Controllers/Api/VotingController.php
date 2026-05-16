<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\VoteType;
use App\Exceptions\VotingException;
use App\Http\Requests\CastVoteRequest;
use App\Http\Requests\DirectorDecideRequest;
use App\Http\Requests\OverrideVoteRequest;
use App\Http\Resources\ImportRequestListResource;
use App\Http\Resources\ImportRequestResource;
use App\Http\Resources\VoteResource;
use App\Http\Resources\VotingTallyResource;
use App\Models\ImportRequest;
use App\Models\RequestVote;
use App\Models\User;
use App\Services\Voting\VotingService;
use App\Services\Workflow\WorkflowService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class VotingController extends Controller
{
    public function __construct(
        private readonly VotingService $votingService,
        private readonly WorkflowService $workflowService,
    ) {
    }

    #[OA\Get(path: '/api/voting', tags: ['Voting'], summary: 'List requests in executive voting', responses: [new OA\Response(response: 200, description: 'Voting list')])]
    public function index()
    {
        $user = request()->user();
        if (!$user->hasRole(UserRole::EXECUTIVE_MEMBER) && !$user->hasRole(UserRole::COMMITTEE_DIRECTOR)) {
            return ApiResponse::forbidden();
        }

        $items = ImportRequest::query()
            ->whereIn('status', [
                RequestStatus::EXECUTIVE_VOTING_OPEN->value,
                RequestStatus::EXECUTIVE_VOTING_CLOSED->value,
            ])
            ->with('bank')
            ->latest('id')
            ->paginate(20);

        return ApiResponse::success(ImportRequestListResource::collection($items), 'Voting requests retrieved.');
    }

    #[OA\Get(path: '/api/voting/{id}', tags: ['Voting'], summary: 'Voting detail', responses: [new OA\Response(response: 200, description: 'Voting detail')])]
    public function show(ImportRequest $importRequest)
    {
        $user = request()->user();
        if (!$user->hasRole(UserRole::EXECUTIVE_MEMBER) && !$user->hasRole(UserRole::COMMITTEE_DIRECTOR)) {
            return ApiResponse::forbidden();
        }

        $votes = RequestVote::query()
            ->where('request_id', $importRequest->id)
            ->with('user')
            ->get();

        $myVote = $votes->firstWhere('user_id', $user->id);

        $totalMembers = User::query()
            ->whereIn('role', [UserRole::EXECUTIVE_MEMBER->value, UserRole::COMMITTEE_DIRECTOR->value])
            ->where('is_active', true)
            ->count();

        return ApiResponse::success([
            'request' => new ImportRequestResource($importRequest->load('bank')),
            'tally' => new VotingTallyResource($this->votingService->tally($importRequest)),
            'votes' => VoteResource::collection($votes),
            'total_members' => $totalMembers,
            'my_vote' => $myVote ? new VoteResource($myVote->load('user')) : null,
        ], 'Voting details retrieved.');
    }

    #[OA\Post(path: '/api/voting/{importRequest}/open', tags: ['Voting'], summary: 'Director opens voting session (WAITING_FOR_VOTING_OPEN → EXECUTIVE_VOTING_OPEN)', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Voting session opened')])]
    public function openSession(Request $request, ImportRequest $importRequest)
    {
        if (!$request->user()->hasPermission('voting.finalize')) {
            return ApiResponse::forbidden();
        }

        $updated = $this->workflowService->transition($importRequest, 'open_voting', $request->user());

        return ApiResponse::success(new ImportRequestResource($updated->load('bank')), 'Voting session opened.');
    }

    #[OA\Post(path: '/api/voting/{importRequest}/close', tags: ['Voting'], summary: 'Director closes voting session and applies AUTO_ABSTAIN_TIMEOUT to non-voters', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Voting session closed')])]
    public function closeSession(Request $request, ImportRequest $importRequest)
    {
        if (!$request->user()->hasPermission('voting.finalize')) {
            return ApiResponse::forbidden();
        }

        try {
            $updated = $this->votingService->closeSession($importRequest, $request->user());
        } catch (VotingException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(new ImportRequestResource($updated->load('bank')), 'Voting session closed.');
    }

    #[OA\Post(path: '/api/voting/{importRequest}/vote', tags: ['Voting'], summary: 'Cast executive vote', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['vote'], properties: [new OA\Property(property: 'vote', type: 'string', enum: ['APPROVE', 'REJECT']), new OA\Property(property: 'justification', type: 'string', nullable: true, maxLength: 3000)])), responses: [new OA\Response(response: 200, description: 'Vote cast')])]
    public function vote(CastVoteRequest $request, ImportRequest $importRequest)
    {
        $vote = $this->votingService->castVote(
            $importRequest,
            $request->user(),
            VoteType::from($request->string('vote')->toString()),
            $request->input('justification')
        );

        return ApiResponse::success(new VoteResource($vote), 'Vote submitted successfully.');
    }

    #[OA\Post(path: '/api/voting/{importRequest}/director-decide', tags: ['Voting'], summary: 'Director tie-break decision', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['vote'], properties: [new OA\Property(property: 'vote', type: 'string', enum: ['APPROVE', 'REJECT']), new OA\Property(property: 'justification', type: 'string', nullable: true, maxLength: 3000)])), responses: [new OA\Response(response: 200, description: 'Director decision applied')])]
    public function directorDecide(DirectorDecideRequest $request, ImportRequest $importRequest)
    {
        if (!$request->user()->hasPermission('voting.finalize')) {
            return ApiResponse::forbidden();
        }

        $updated = $this->votingService->finalize(
            $importRequest,
            $request->user()
        );

        return ApiResponse::success(new ImportRequestResource($updated->load('bank')), 'Director decision applied.');
    }

    #[OA\Post(path: '/api/voting/{importRequest}/override', tags: ['Voting'], summary: 'Committee director override and finalize', parameters: [new OA\Parameter(name: 'importRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['decision', 'justification'], properties: [new OA\Property(property: 'decision', type: 'string', enum: ['APPROVE', 'REJECT']), new OA\Property(property: 'justification', type: 'string', minLength: 3, maxLength: 3000)])), responses: [new OA\Response(response: 200, description: 'Override decision applied')])]
    public function override(OverrideVoteRequest $request, ImportRequest $importRequest)
    {
        if (!$request->user()->hasPermission('voting.finalize')) {
            return ApiResponse::forbidden();
        }

        $updated = $this->votingService->overrideAndFinalize(
            $importRequest,
            $request->user(),
            VoteType::from($request->string('decision')->toString()),
            $request->string('justification')->toString()
        );

        return ApiResponse::success(new ImportRequestResource($updated->load('bank')), 'Override decision applied.');
    }
}
