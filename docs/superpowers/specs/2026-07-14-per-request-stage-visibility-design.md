# Per-Request Stage & History Visibility — Design

Status: draft, pending user review
Owner request: filter the "سير العملية التنظيمية" process rail (and every other per-request stage/history surface) to the viewing user's own `StagePermission` grants, instead of always showing every designer-defined stage.

## 1. Problem

Today, any user who passes `EngineRequestPolicy::view()` (VIEW access on the request's **current** stage only) sees:

- Every stage in the workflow version on the process rail (`EngineOrgProcessRail.vue`), regardless of whether they have VIEW/EXECUTE on any of those stages individually.
- Every `workflow_history` entry for the request — including `comments` and the acting user's name — for **every** past stage, even stages the viewer never had access to (e.g. a Bank Reviewer viewing a request now at the Executive stage still sees Support Committee's internal transition comments).

Confirmed in code: `WorkflowGraphService::build()` takes only a `WorkflowVersion`, no `User` — it cannot filter by design. `EngineRequestController::history()` returns all rows unconditionally once the single request-level gate passes; `EngineRequestPolicy` has no per-entry authorization at all.

The organizational intent (per Workflow Designer's `StagePermission` model) is that different roles/teams/orgs should see **different configured journeys** through the same request — e.g. an intake team sees only "create → internal review → complete," while another role sees a different subset. The system already lets a designer configure this via `stage_permissions`; the UI just never enforced it for *viewing the journey*, only for the "دورك" execute badge.

## 2. Decisions already made (confirmed with user)

1. **Strict filter, no placeholder.** A stage the user has no VIEW/EXECUTE on is simply absent from the rail — including if it's the request's current stage. No generic "in progress" placeholder node.
2. **Backend omits, doesn't flag.** The `/graph` response contains only nodes/edges the user is authorized to see. No `visible: false` pass-through — ungranted stages must not appear in the payload at all.
3. **Dangling edges are dropped.** If either endpoint (`from_stage_id` or `to_stage_id`) of an edge is filtered out, the edge is dropped entirely.
4. **Scope: every per-request stage/history surface**, not just one component — but explicitly **not** the Workflow Designer's own graph view (`WorkflowVersionController`'s `/workflow-versions/{v}/graph` endpoint, consumed by `WorkflowProcessGraph.vue` in `admin/workflows`). That surface is for designers/admins configuring `stage_permissions` and must keep showing the full topology.
5. **Version-pinning confirmed safe, reused as-is.** `EngineRequest.workflow_version_id` permanently pins a request to one `WorkflowVersion`. Stage/transition/permission rows on a PUBLISHED version are immutable (`WorkflowDesignerService::ensureStageVersionEditable()`). Team/role/org membership is evaluated live at request time — by design, not a gap. Every existing consumer of `accessibleStageIds()` is already version-safe because `workflow_stages.id` is a globally-unique PK and all call sites filter through `current_stage_id` (a FK carrying version identity implicitly). The new filtering reuses this exact pattern — no new version-safety mechanism needed.
6. **History redaction rule (this session's decision):**
   - Viewer currently has VIEW/EXECUTE on the entry's stage → show the normal entry (existing field/document visibility rules apply, unchanged).
   - Viewer has no access to the stage, but **is the actor** (`performed_by === viewer.id`) → show a **sanitized, minimal** entry: action happened, timestamp, a generic result/status, and a fixed label ("إجراء تم في مرحلة مقيدة" / "Action performed in a restricted stage"). Do not expose: stage name (if it reveals restricted process info), comments, documents, other users' identities, transition metadata.
   - Viewer has no access and is not the actor → hide the entry completely.
   - `SYSTEM_ADMIN` (via `RoleCodes::SYSTEM_ADMIN`, the codebase's one consistent elevated-visibility bypass — see `EngineRequestPolicy::view()`, `FxConfirmationAuthorizationService`, `AuditLogController`) sees full, unredacted history regardless of stage access. No other role name is used for this bypass, per `AGENTS.md`'s invariant against using `CBY_ADMIN` as a workflow super-actor.
7. **Explicitly out of scope:** `audit_logs` table (separate model, same shape of leak — `AuditLogController` exposes `old_values`/`new_values` unfiltered — but not part of this request; flagged as a follow-up). The `ReportExportController`/`GenerateReportExport` CSV export currently ignores `report_type` and never actually includes `workflow_history` data regardless of the accepted contract listing `stage-duration`/`team-performance` — this is a pre-existing, unrelated bug and is not touched by this design.

## 3. Which stage-access level counts as "visible"?

`StageAccessLevel` is `VIEW` or `EXECUTE`, with `EXECUTE ⊃ VIEW` already encoded in `StagePermissionResolver`. "Visible on the rail" = holds at least VIEW. Calling `accessibleStageIds($user, StageAccessLevel::VIEW)` naturally returns stages granted at either level — no separate EXECUTE-specific call needed for the node-filter (the existing `execute_stage_ids` badge computation stays as-is, unchanged, still computed with `StageAccessLevel::EXECUTE`).

## 4. Backend design

### 4.1 Stage/edge filtering — `EngineRequestController::graph()`

Reuse the exact intersection pattern already used for `execute_stage_ids` (lines 469-475 today), applied to the node/edge list itself instead of only to the badge array:

```php
public function graph(Request $request, EngineRequest $engineRequest): JsonResponse
{
    $this->authorize('view', $engineRequest);

    $engineRequest->load(['workflowVersion', 'history']);
    $graphData = $this->graphService->build($engineRequest->workflowVersion);

    $user = $request->user();
    $versionStageIds = array_column($graphData['nodes'], 'id');

    $viewableStageIds = $user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)
        ? $versionStageIds
        : array_values(array_intersect(
            $this->permissionResolver->accessibleStageIds($user, StageAccessLevel::VIEW),
            $versionStageIds,
        ));

    $graphData['nodes'] = array_values(array_filter(
        $graphData['nodes'],
        fn ($node) => in_array($node['id'], $viewableStageIds, true),
    ));

    $viewableIdSet = array_flip($viewableStageIds);
    $graphData['edges'] = array_values(array_filter(
        $graphData['edges'],
        fn ($edge) => isset($viewableIdSet[$edge['from_stage_id']]) && isset($viewableIdSet[$edge['to_stage_id']]),
    ));

    // ... existing state annotation (current/executed/possible) runs AFTER filtering,
    // over the now-reduced node/edge arrays only ...

    // ... existing execute_stage_ids computation unchanged, still EXECUTE-scoped ...
}
```

Key ordering point: state annotation (`current`/`executed`/`possible`) must run on the filtered arrays, not before — otherwise a `foreach (&$node)` over a soon-to-be-removed node is wasted work, and more importantly the current-stage marker must never resurrect a stage the filter just removed. Filter first, annotate second.

`SYSTEM_ADMIN` bypass: skip filtering entirely, matching the existing pattern in `EngineRequestPolicy::view()` and `FxConfirmationAuthorizationService` (check-and-return-early, not a parallel code path).

No change needed to `WorkflowGraphService::build()` itself — it stays a pure, user-agnostic version→graph builder, reused unchanged by the Designer's own `/workflow-versions/{v}/graph` endpoint (which must keep seeing everything). All filtering lives in the controller, which is the only version-aware, user-aware caller.

### 4.2 Downstream frontend surfaces — no separate filtering needed

Traced and confirmed: `EngineOrgProcessRail.vue`, `EngineStageStepper.vue`, and `useEngineProgress` (percent/total/currentIndex) all derive purely from the `graph` object returned by this one endpoint. Filtering once at the API boundary automatically fixes all three — no frontend logic changes required for stage visibility. `WorkflowProcessGraph.vue` fetches from the separate, intentionally-unfiltered Designer endpoint and needs no change.

### 4.3 History redaction — new authorization step in `EngineRequestController::history()`

Add a `WorkflowHistoryVisibility` value object/enum (`FULL`, `SANITIZED`, `HIDDEN`) computed per entry, and a small resolver co-located with `StagePermissionResolver` (same service directory, same DI pattern) rather than a new Policy class — this is a data-shaping decision per entry, not a single yes/no gate, so it doesn't fit Laravel's Policy shape cleanly. Suggested: `StageHistoryVisibilityResolver` in `app/Services/Workflow/`.

```php
class StageHistoryVisibilityResolver
{
    public function __construct(private StagePermissionResolver $permissionResolver) {}

    public function visibilityFor(User $user, WorkflowHistoryEntry $entry): WorkflowHistoryVisibility
    {
        if ($user->hasRoleCode(RoleCodes::SYSTEM_ADMIN)) {
            return WorkflowHistoryVisibility::FULL;
        }

        $stage = $entry->toStage ?? $entry->fromStage; // to_stage is nullable only for ABANDON entries
        if ($stage !== null && $this->permissionResolver->userCanAccessStage($user, $stage, StageAccessLevel::VIEW)) {
            return WorkflowHistoryVisibility::FULL;
        }

        if ((int) $entry->performed_by === (int) $user->getKey()) {
            return WorkflowHistoryVisibility::SANITIZED;
        }

        return WorkflowHistoryVisibility::HIDDEN;
    }
}
```

`EngineRequestController::history()` becomes:

```php
$entries = $engineRequest->history()->with([...])->orderBy('created_at')->orderBy('id')->get();

$data = $entries
    ->map(function ($e) use ($user) {
        $visibility = $this->historyVisibilityResolver->visibilityFor($user, $e);

        return match ($visibility) {
            WorkflowHistoryVisibility::HIDDEN => null,
            WorkflowHistoryVisibility::SANITIZED => [
                'id' => $e->id,
                'from_stage' => null,
                'to_stage' => null,
                'action_code' => null,
                'performed_by' => ['id' => $e->performer->id, 'name' => $e->performer->name],
                'comments' => null,
                'created_at' => $e->created_at?->toISOString(),
                'restricted' => true,
                'restricted_label' => 'إجراء تم في مرحلة مقيدة',
            ],
            WorkflowHistoryVisibility::FULL => [
                'id' => $e->id,
                'from_stage' => $e->fromStage ? [...] : null,
                'to_stage' => $e->toStage ? [...] : null,
                'action_code' => $e->action_code,
                'performed_by' => $e->performer ? [...] : null,
                'comments' => $e->comments,
                'created_at' => $e->created_at?->toISOString(),
                'restricted' => false,
            ],
        };
    })
    ->filter()
    ->values();
```

Note: sanitized entries still show `performed_by` (their own name) since the whole point is "I did this" — the redaction hides the *stage identity and content*, not the fact that it was this user's own action. Re-reading the user's rule: "That the user performed an action" is explicitly an allowed field, and this is the user's own identity, not "other users' actions" (which stays hidden). `created_at` (date/time) and a generic result are allowed; `action_code` is treated as potentially revealing stage-specific process detail (e.g. `SWIFT_CONFIRM`) so it's nulled in favor of the fixed generic label — this is a judgment call the user should confirm in review (alternative: keep a generic result field like `outcome: 'completed'` derived from whether the transition led to a final stage, distinct from the specific `action_code`).

### 4.4 `graph()` endpoint's internal history load

`graph()` loads `$engineRequest->history` only to compute `state` (executed/current/possible) per node/edge — it never serializes `comments`/`performed_by` in the response today. No redaction needed there; flagged in research for defense-in-depth but confirmed not a live leak. No change beyond the node/edge filtering in §4.1.

### 4.5 Report aggregates (`ReportController::stageDuration`, `teamPerformance`)

These read `workflow_history` directly but only ever emit aggregated counts/averages, gated by a separate `reports:VIEW` capability — not a per-entry or per-request surface, and not what the user asked to change ("history API, request details, timeline UI, graph-related history views, exports" — read as the request-scoped surfaces, not the org-wide reports module). Left untouched. Called out here so it's a documented decision, not an oversight.

## 5. Frontend design

### 5.1 Type change

`EngineHistoryEntry` (`frontend/app/types/models.ts:886-894`) gains two fields:

```ts
export interface EngineHistoryEntry {
  id: number
  from_stage: { id: number; code: string; name: string } | null
  to_stage: { id: number; code: string; name: string } | null
  action_code: string | null
  performed_by: { id: number; name: string } | null
  comments: string | null
  created_at: string | null
  restricted: boolean
  restricted_label: string | null
}
```

### 5.2 `EngineTimeline.vue` / `useEngineTimeline.ts`

`buildTimeline()` checks `entry.restricted` and maps to a distinct `TimelineItem` shape for restricted entries: shows `restricted_label` in place of `fromLabel`/`toLabel`/`actionCode`, keeps `actorName` (already the viewer's own name in this case) and `timestamp`, omits `comment` entirely (already `null` from backend, but the component should not render an empty comment block differently than "no comment was ever left" — needs its own visual treatment, e.g. a muted/locked-icon row style, so users don't mistake "redacted" for "no comment given").

### 5.3 `EngineOrgProcessRail.vue` / `useEngineStagePath.ts`

No change needed — `buildStagePath()` only reads `entry.to_stage?.id` for visitation marking, and since the `/graph` endpoint now only returns visible nodes, any history entry pointing to a filtered-out stage simply won't match a node in the (already-filtered) `graph.nodes` list. Confirm this degrades gracefully (no node = no dot rendered for that entry) rather than throwing — `buildStagePath` should be checked for a safe `.find()`/`undefined` path here during implementation, but no design change is anticipated.

## 6. Testing approach (focused, per project convention)

- Backend: `StagePermissionResolver`/`StageHistoryVisibilityResolver` unit tests (pure, no DB) covering FULL/SANITIZED/HIDDEN branching, and `EngineRequestController::graph()`/`history()` feature tests covering: user with partial stage access sees only granted nodes+edges (dangling edge dropped), `SYSTEM_ADMIN` sees everything, actor sees own sanitized entry on an inaccessible stage, non-actor sees it hidden.
- Frontend: `useEngineStagePath`/`useEngineTimeline` unit tests for the `restricted` branch; existing `WorkflowProcessGraph.test.ts` should be checked to confirm it doesn't regress (that component is unaffected — different endpoint).
- No full suite run required per project default; run the specific new/touched test files only.

## 7. Open items for user review

1. `action_code` fully nulled vs. replaced with a generic `outcome` (e.g. `advanced`/`returned`/`closed`) in sanitized entries — pick one (see §4.3 note).
2. Should the `restricted_label` string live in the backend (single source of truth, as drafted) or be a frontend-only i18n string keyed off `restricted: true`? Drafted as backend-supplied for now since it's simpler and avoids drift, but frontend may already have an i18n convention this should follow instead.
3. Confirm `WorkflowHistoryArchive` (the separate archived-history table) is genuinely unreachable by any current user-facing endpoint (research found no controller reads it) — if a future feature ever surfaces archived history, this same visibility rule must apply there too; noting it now so it isn't forgotten later.
