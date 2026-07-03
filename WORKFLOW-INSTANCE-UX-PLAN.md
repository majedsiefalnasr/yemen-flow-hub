# Implementation Plan — Role-aware workflow instance page

**Branch:** `feat/workflow-admin-ux-alignment`
**Goal:** Make `workflows/instances/[id]` derive per-role view/act state, matching the demo (`dynamic-workflow-engine`). Kill the "viewer sees action buttons + editable form" defect and its downstream UX gaps.

**Root cause (established in review):** The backend enforces role scoping (`myQueue` = EXECUTE stages, `index` = VIEW stages, `authorize('execute')` on all mutations), but that authority never reaches the detail UI. `EngineRequestResource` carries no `can_execute` flag; the page treats every viewer as the actor.

**Fix strategy:** One server-provided `can_execute` flag → frontend gates actions, edit mode, and adds view-only/your-turn signaling. Reuses existing `StagePermissionResolver`. No new patterns, no new libraries.

---

## Phase 1 — Backend: expose `can_execute` on the request resource

### 1.1 `EngineRequestResource` — add the flag

`backend/app/Http/Resources/EngineRequestResource.php`

The resolver needs the current stage loaded. `show()` already loads `currentStage` (controller line 123). Guard for the unloaded case (list endpoints don't need it; return `null` there).

```diff
 <?php

 namespace App\Http\Resources;

+use App\Enums\StageAccessLevel;
+use App\Services\Workflow\StagePermissionResolver;
 use Illuminate\Http\Request;
 use Illuminate\Http\Resources\Json\JsonResource;

 class EngineRequestResource extends JsonResource
 {
     public function toArray(Request $request): array
     {
         return [
             'id' => $this->id,
             'reference' => $this->reference,
             'status' => $this->status,
             'version' => $this->version,
             'workflow_version_id' => $this->workflow_version_id,
             'current_stage' => $this->whenLoaded('currentStage', fn () => [
                 'id' => $this->currentStage->id,
                 'code' => $this->currentStage->code,
                 'name' => $this->currentStage->name,
                 'is_initial' => $this->currentStage->is_initial,
                 'is_final' => $this->currentStage->is_final,
                 'sla_duration_minutes' => $this->currentStage->sla_duration_minutes,
                 'requires_claim' => $this->currentStage->requires_claim,
             ]),
+            // Whether the signed-in user may EXECUTE the current stage. Drives the
+            // detail page's action panel and edit mode. Null when the stage is not
+            // loaded (list endpoints), where the client does not need it.
+            'can_execute' => $this->when(
+                $request->user() !== null && $this->relationLoaded('currentStage') && $this->currentStage !== null,
+                fn () => app(StagePermissionResolver::class)->userCanAccessStage(
+                    $request->user(),
+                    $this->currentStage,
+                    StageAccessLevel::EXECUTE,
+                ),
+            ),
             'bank_id' => $this->bank_id,
```

**Note:** `$request->user()` returning `true` for a system admin — check the intent. `StagePermissionResolver` does NOT special-case admins (only `index`/`myQueue` in the controller do, via `isSystemAdmin()`). A CBY admin should NOT be able to execute Director/SWIFT/Support actions (AGENTS.md "Never Do"). So `can_execute` staying assignment-based for admins is **correct** — admin views are read-only unless explicitly assigned. Confirm this matches the demo: demo `isExecutor = canExecute(...)` (assignment), while `isAdmin` only widens *visibility*, never grants execute. ✔ Aligned.

### 1.2 Test — resource flag

`backend/tests/Feature/Engine/EngineRequestResourceCanExecuteTest.php` (new)

Cover three cases against a request whose current stage has one EXECUTE permission row for a role:
- user with that role → `can_execute: true`
- user without it (VIEW-only assignment) → `can_execute: false`
- system admin not assigned to the stage → `can_execute: false`

Assert the `show` endpoint (`GET /api/v1/engine-requests/{id}`) payload. Model factories/seeders already exist (`EngineRequestDemoSeeder`); mirror an existing feature test's setup under `tests/Feature/Engine/`.

**Verify:**
```bash
cd backend
php artisan test --filter=EngineRequestResourceCanExecuteTest
vendor/bin/pint app/Http/Resources/EngineRequestResource.php --test
```

---

## Phase 2 — Frontend: type + gate actions and edit mode

### 2.1 `EngineRequest` type — add `can_execute`

`frontend/app/types/models.ts` (interface at line 864)

```diff
   claimed_by: number | null
   claimed_by_user: { id: number; name: string } | null
   claimed_at: string | null
   claim_expires_at: string | null
+  // Whether the signed-in user may execute the current stage. Present only on
+  // the single-request (show) payload; absent/undefined on list rows.
+  can_execute?: boolean
   created_by: number
```

### 2.2 Instance page — gate on `can_execute`

`frontend/app/pages/workflows/instances/[id].vue`

**(a) availableActions — empty unless executor.** This is the core fix.

```diff
 const availableActions = computed(() => {
   if (!store.current?.current_stage || !store.graph) return []
+  // Non-executors never see stage actions, even if the graph has outgoing
+  // edges. Mirrors the demo's getAvailableActions() → [] unless canExecute.
+  if (store.current.can_execute !== true) return []
   return store.graph.edges.filter((edge) => edge.from_stage_id === store.current!.current_stage!.id)
 })
```

**(b) canAct — require execute permission, not just claim state.**

```diff
 const stageRequiresClaim = computed(() => store.current?.current_stage?.requires_claim === true)
+const canExecute = computed(() => store.current?.can_execute === true)
 const isUnclaimed = computed(() => claimedBy.value === null)
 const showClaimButton = computed(
-  () => stageRequiresClaim.value && isUnclaimed.value && !heldByOther.value,
+  () => canExecute.value && stageRequiresClaim.value && isUnclaimed.value && !heldByOther.value,
 )
 const claimHolderName = computed(() => store.current?.claimed_by_user?.name ?? null)
-const canAct = computed(() => !stageRequiresClaim.value || isHeldByMe.value)
-const claimRequiredButNotHeld = computed(() => stageRequiresClaim.value && !isHeldByMe.value)
+const canAct = computed(
+  () => canExecute.value && (!stageRequiresClaim.value || isHeldByMe.value),
+)
+const claimRequiredButNotHeld = computed(
+  () => canExecute.value && stageRequiresClaim.value && !isHeldByMe.value,
+)
+// View-only when the user may see the request but cannot execute the stage.
+const isViewOnly = computed(() => store.current?.can_execute !== true)
```

`DynamicForm :mode="canAct ? 'edit' : 'readonly'"` and `EngineDocumentsPanel :can-manage="canAct"` now correctly read-only for viewers with no code change — `canAct` already flows in.

**(c) View-only badge in the summary region.** Add next to the stepper (template, after `EngineRequestSummary`):

```diff
       <EngineRequestSummary :request="store.current" />

+      <div v-if="isViewOnly" class="flex items-center gap-2">
+        <Badge variant="outline" class="gap-1">
+          <Lock class="h-3 w-3" aria-hidden="true" />
+          عرض فقط
+        </Badge>
+        <span class="text-muted-foreground text-xs">
+          هذا الطلب معروض للاطلاع فقط؛ الإجراءات متاحة للمكلّف بالمرحلة الحالية.
+        </span>
+      </div>
+
```

Imports:
```diff
-import { AlertTriangle } from 'lucide-vue-next'
+import { AlertTriangle, Lock } from 'lucide-vue-next'
+import { Badge } from '@/components/ui/badge'
```

**Note on `wizardMode`:** already correctly gated (`is_initial && created_by === user.id`). A draft creator on the initial stage is the executor there, so `can_execute` will be `true` — no conflict. Wizard path unchanged.

### 2.3 Actions rail — friendlier empty copy for viewers

`frontend/app/components/workflow/EngineActionsRail.vue`

The rail already renders "لا توجد إجراءات متاحة في هذه المرحلة." when `availableActions` is empty — which now correctly triggers for viewers. Optional polish: hide the comment box + rail entirely for pure viewers. Minimal change — pass `isViewOnly` and short-circuit:

```diff
 defineProps<{
   availableActions: WorkflowGraphEdge[]
   canAct: boolean
   claimRequiredButNotHeld: boolean
   showClaimButton: boolean
   busy: boolean
+  viewOnly?: boolean
 }>()
```

```diff
   <Card dir="rtl" class="bg-muted/30 sticky top-6 border-0 shadow-none">
     <CardHeader class="pb-2">
       <CardTitle class="text-sm font-semibold">إجراءات المرحلة</CardTitle>
     </CardHeader>
     <CardContent class="space-y-3">
+      <p v-if="viewOnly" class="text-muted-foreground text-xs">
+        لا تملك صلاحية تنفيذ إجراءات على المرحلة الحالية. يمكنك الاطلاع على الطلب وسجله.
+      </p>
+      <template v-else>
       <Button v-if="showClaimButton" class="w-full" :disabled="busy" @click="emit('claim')">
         بدء المراجعة
       </Button>
       ...
       </div>
+      </template>
     </CardContent>
   </Card>
```

Pass through in the page:
```diff
           <EngineActionsRail
             v-model:comment="comment"
             :available-actions="availableActions"
             :can-act="canAct"
             :claim-required-but-not-held="claimRequiredButNotHeld"
             :show-claim-button="showClaimButton"
+            :view-only="isViewOnly"
             :busy="actionBusy"
             @run="runAction"
             @claim="startReview"
           />
```

**Verify Phase 2:**
```bash
cd frontend
pnpm exec vitest run app/tests/unit/pages/workflows-instance-detail.test.ts
pnpm exec eslint app/pages/workflows/instances/\[id\].vue app/components/workflow/EngineActionsRail.vue
pnpm typecheck   # type + store contract changed → run it
```
Update `workflows-instance-detail.test.ts`: the existing tests mount `store.current` without `can_execute`. Add `can_execute: true` to the executor-fixture and a new case with `can_execute: false` asserting no action buttons + view-only badge.

---

## Phase 3 — "دورك" (your turn) marker on the stepper

`frontend/app/components/workflow/EngineStageStepper.vue` + `useEngineStagePath.ts`

The demo's `OrgProcessStepper` marks stages the user owns. We have no per-stage execute map on the client — only `can_execute` for the *current* stage. Minimal, honest version: mark the **current** step "دورك" when `can_execute` is true, rather than faking per-stage ownership.

`useEngineStagePath.ts`:
```diff
 export interface EngineStageStep {
   id: number
   label: string
   status: EngineStageStatus
+  isYours?: boolean
 }

 export function buildStagePath(
   graph: WorkflowGraph | null,
   currentStageId: number | null,
   history: EngineHistoryEntry[],
+  currentStageIsYours = false,
 ): EngineStageStep[] {
   ...
   return ordered.map((node) => {
     let status: EngineStageStatus
     ...
-    return { id: node.id, label: node.display_label ?? node.name, status }
+    return {
+      id: node.id,
+      label: node.display_label ?? node.name,
+      status,
+      isYours: status === 'current' && currentStageIsYours,
+    }
   })
 }
```

`EngineStageStepper.vue`:
```diff
 const props = defineProps<{
   graph: WorkflowGraph | null
   currentStageId: number | null
   history: EngineHistoryEntry[]
+  currentStageIsYours?: boolean
 }>()

-const steps = computed(() => buildStagePath(props.graph, props.currentStageId, props.history))
+const steps = computed(() =>
+  buildStagePath(props.graph, props.currentStageId, props.history, props.currentStageIsYours ?? false),
+)
```

In the `StepperTitle` block, append a marker for `step.isYours`:
```diff
           <StepperTitle ...>
             {{ step.label }}
+            <Badge
+              v-if="step.isYours"
+              variant="outline"
+              class="border-primary/40 text-primary ms-1 h-4 px-1 text-[10px]"
+            >
+              دورك
+            </Badge>
           </StepperTitle>
```
(import `Badge`.)

Page wiring:
```diff
       <EngineStageStepper
         :graph="store.graph"
         :current-stage-id="store.current.current_stage?.id ?? null"
         :history="store.history"
+        :current-stage-is-yours="canExecute"
       />
```

**Verify:**
```bash
pnpm exec vitest run app/tests/unit/composables/useEngineStagePath.test.ts   # if present; else add one
pnpm exec eslint app/components/workflow/EngineStageStepper.vue app/composables/useEngineStagePath.ts
```

---

## Phase 4 — `index.vue` raw-HTML / token cleanup

`frontend/app/pages/workflows/index.vue`

Two `SHADCN.md` rule-1 / `DESIGN.md` violations in the column defs:

**(a) reference cell — raw `h('button', …)` → `Button variant="link"`** (lines ~235–248):
```diff
     cell: ({ row }) =>
-      h(
-        'button',
-        {
-          type: 'button',
-          class:
-            'text-primary font-mono text-sm text-start hover:underline underline-offset-2 focus-visible:outline-none focus-visible:underline',
-          onClick: (e: Event) => {
-            e.stopPropagation()
-            openInstance(row.original.id)
-          },
-        },
-        row.original.reference,
-      ),
+      h(
+        Button,
+        {
+          variant: 'link',
+          class: 'text-primary h-auto p-0 font-mono text-sm',
+          onClick: (e: Event) => {
+            e.stopPropagation()
+            openInstance(row.original.id)
+          },
+        },
+        () => row.original.reference,
+      ),
```

**(b) status + SLA chips — hand-rolled `h('span', {style})` → `Badge`.** Replace the inline-styled spans in the `status` and `sla` cells with `Badge` + semantic-token classes. Use the `DESIGN.md` §7 mapping (green/red/amber tokens) instead of inline `style`. Keep the existing `statusTone`/`slaTone` logic but map to class strings, e.g.:

```ts
function statusBadgeClass(status: string): string {
  if (status === 'CLOSED')
    return 'border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 text-[var(--severity-green)]'
  if (status === 'REJECTED')
    return 'border-[var(--severity-red)]/30 bg-[var(--severity-red)]/10 text-[var(--severity-red)]'
  return 'border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]'
}
```
and render `h(Badge, { variant: 'outline', class: statusBadgeClass(...) }, () => statusLabel(...))`. Same treatment for SLA. Drop the now-unused `statusTone`/`slaTone` returning inline style objects.

**Verify:**
```bash
pnpm exec vitest run app/tests/unit/pages/workflows-index.test.ts
pnpm exec vitest run app/tests/unit/pages/requests/RequestsListAdvancedFilters.test.ts
pnpm exec eslint app/pages/workflows/index.vue
```

---

## Phase 5 (follow-up, separate commit) — duplicate-invoice banner on detail

**Not in the core fix.** `show()` returns bare `data`; the duplicate check (`DuplicateInvoiceChecker`) runs only on create/transition. To surface the demo's "فاتورة مكررة محتملة" banner on the detail page, the backend must compute it in `show()` or `formSchema()`:

- Add to `EngineRequestController@show`: if `invoice_number !== null`, run `duplicateChecker->check(...)` and return under a `warnings` key alongside `data`.
- Frontend: `useEngineRequests.show()` capture warnings → store → render an `Alert variant="destructive"` (already imported) above the two-column grid, listing conflicting refs.

Defer unless you want it in this pass; flag as its own story to keep the core PR tight.

---

## Phase 6 (decision required) — `new.vue` picker

**Needs your call, no code until decided.** Demo creates from one button → straight into wizard. Current `new.vue` is a workflow-picker page (`store.availableWorkflows`).

- **Keep** if multiple published workflows will genuinely coexist and users must choose.
- **Collapse** to one-click if there's effectively one workflow: skip the picker when `availableWorkflows.length === 1`, auto-create, navigate to `?mode=wizard`. Keep the picker only when `> 1`.

Low risk either way; the collapse is ~10 lines in `new.vue`'s `onMounted`.

---

## Commit sequence

1. `feat(workflow): expose can_execute on engine request resource` (Phase 1, backend + test)
2. `fix(workflow): gate instance actions and edit mode on can_execute` (Phase 2)
3. `feat(workflow): mark current stage "دورك" on instance stepper` (Phase 3)
4. `style(workflow): replace raw button/chips with Button/Badge in requests list` (Phase 4)

Phases 5–6 as separate stories after decisions.

## Order of verification before the PR

Per verification ladder — focused, not full suites:
```bash
# backend
cd backend && php artisan test --filter=EngineRequestResourceCanExecuteTest && vendor/bin/pint <touched> --test
# frontend
cd frontend && pnpm exec vitest run app/tests/unit/pages/workflows-instance-detail.test.ts app/tests/unit/pages/workflows-index.test.ts
pnpm exec eslint <touched files>
pnpm typecheck   # store/type/contract changed
```
Then `graphify update .` (local only, never commit `graphify-out/`).
