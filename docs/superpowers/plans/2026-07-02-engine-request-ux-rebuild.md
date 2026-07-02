# Engine Request UX Rebuild Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the dynamic workflow-engine request pages (`workflows/new`, `workflows/instances/[id]`) into the richer legacy-style UX — summary strip, dynamic stage stepper, two-column detail with tabbed main + sticky actions rail, and a stepped create wizard — all driven by the existing engine API.

**Architecture:** Pure derivation logic (stage-path ordering, timeline ordering, wizard step state) lives in three new composables so it can be unit-tested in the repo's `node` vitest environment. Five new engine-native `.vue` components are thin presenters over that logic plus existing shadcn primitives and the existing `DynamicForm`. The instance page becomes the single surface for create → edit → view → act, branching on stage/role into wizard mode or view/act mode. No backend changes; every call uses existing engine store methods.

**Tech Stack:** Nuxt 4 / Vue 3 `<script setup>`, TypeScript, Pinia, shadcn-vue (Stepper, Tabs, Card, Button, Badge, Alert, Textarea, Field, Skeleton, Empty), vee-validate (inside `DynamicForm`), Vitest (`node` environment), Arabic RTL.

## Global Constraints

- All UI text is Arabic; every page/component root uses `dir="rtl"`.
- No new backend endpoints. Only existing engine API + existing store methods (`loadAvailableWorkflows`, `createInstance`, `loadInstance`, `saveDraftData`, `executeTransition`, `uploadDocument`, `removeDocument`) and existing composables (`useEngineFormSchema`, `useEngineRequestDocuments`, `useEngineClaim`, `useEngineRequestActions`).
- No new npm dependencies.
- `EngineRequest.status` is `'ACTIVE' | 'CLOSED' | 'REJECTED'` — there is **no** `DRAFT` status. "Draft/wizard" is determined by `current_stage.is_initial === true` AND the viewer is the creator (`created_by === auth.user.id`) AND `?mode=wizard` is present. Do **not** invent a `DRAFT` status.
- `DynamicForm` renders **every** group in the `fieldGroups` prop it receives. To render one group, pass a single-element array `[group]`. Do not modify `DynamicForm`.
- Tests run in vitest's default `node` environment (`vitest.config.ts`). New tests must be pure-logic composable tests — no component mounting. Match the mock style of `app/tests/unit/composables/useEngineRequestHistory.test.ts`.
- Currency/date formatting uses `ar-EG` locale (`Intl.NumberFormat`, `Intl.DateTimeFormat`).
- Creator-only create: `workflows/new` and wizard mode are gated to `UserRole.DATA_ENTRY` (`auth.currentRole === UserRole.DATA_ENTRY`). `UserRole` is imported from `@/types/enums`.
- Reuse `DynamicForm`, `ClaimBanner` (prop: `holderName: string`), shadcn `Stepper` primitives (`@/components/ui/stepper`), and the existing store. Do not duplicate their logic.
- Run the full unit suite with `npm test` (aliases `vitest run`); it must stay green (excluding the pre-quarantined `baselineRedTests` in `vitest.config.ts`).

---

## Relevant existing signatures (read-only reference)

Copy these verbatim where a task consumes them.

```ts
// @/types/models
interface WorkflowGraphNode { id: number; code: string; name: string; display_label: string | null; is_initial: boolean; is_final: boolean; sort_order: number }
interface WorkflowGraphEdge { id: number; from_stage_id: number; to_stage_id: number; action_id: number; action_code: string | null; action_name: string | null; requires_comment: boolean; is_self_loop: boolean; is_return: boolean }
interface WorkflowGraph { nodes: WorkflowGraphNode[]; edges: WorkflowGraphEdge[] }
interface EngineHistoryEntry { id: number; from_stage: { id: number; code: string; name: string } | null; to_stage: { id: number; code: string; name: string } | null; action_code: string | null; performed_by: { id: number; name: string } | null; comments: string | null; created_at: string | null }
interface EngineRequestDocument { id: number; request_id: number; field_id: number | null; stage_id: number; original_name: string; mime: string; size: number; uploaded_by: { id: number; name: string } | number; created_at: string | null }
interface ResolvedFieldGroup { id: number; name: string; label: string; sort_order: number; fields: ResolvedFieldDefinition[] }
interface EngineRequest { /* id, reference, status, version, workflow_version_id, current_stage{ id, code, name, is_initial, is_final, sla_duration_minutes, requires_claim } | null, bank{ id, name, code } | null, merchant{ id, name } | null, data, amount, currency, invoice_number, sla_status, claimed_by, claimed_by_user{ id, name } | null, created_by, creator{ id, name } | null, created_at, ... */ }
```

```ts
// @/stores/engineRequests.store — useEngineRequestsStore()
store.current: EngineRequest | null
store.history: EngineHistoryEntry[]
store.graph: WorkflowGraph | null
store.documents: EngineRequestDocument[]
store.loading: boolean
store.availableWorkflows: AvailableWorkflow[]
store.loadAvailableWorkflows(): Promise<void>
store.createInstance({ workflow_version_id, bank_id?, merchant_id?, data }): Promise<EngineRequest>
store.loadInstance(id): Promise<void>              // loads current + history + graph + documents
store.saveDraftData(id, data, version): Promise<void>
store.executeTransition(id, transitionId, comment|null, data, version): Promise<EngineRequest>
store.uploadDocument(id, file, fieldId|null): Promise<void>
store.removeDocument(id, documentId): Promise<void>
```

```ts
// @/composables/useEngineFormSchema — useEngineFormSchema()
const { fieldGroups /* Ref<ResolvedFieldGroup[]> */, fetchSchema /* (requestId) => Promise<void> */ } = useEngineFormSchema()
// @/composables/useEngineRequestDocuments — useEngineRequestDocuments()
const { downloadUrl /* (requestId, documentId) => string */ } = useEngineRequestDocuments()
// @/composables/useEngineClaim — useEngineClaim(requestId: Ref<number>, currentUserId: Ref<number|null>)
const { claim, release, isHeldByMe, heldByOther, claimedBy } = useEngineClaim(...)
// @/components/workflow/DynamicForm.vue — props: { fieldGroups, modelValue, mode:'edit'|'readonly', requestId? }; exposes validate(): Promise<{ valid, values }>
```

---

## File structure

New files:
- `app/composables/useEngineStagePath.ts` — pure: derive ordered stage steps from graph + history + current stage.
- `app/composables/useEngineTimeline.ts` — pure: order/format history entries.
- `app/composables/useEngineWizard.ts` — wizard step state machine (current step, next/back, per-step save-draft, final submit).
- `app/components/workflow/EngineStageStepper.vue` — presenter over `useEngineStagePath`.
- `app/components/workflow/EngineTimeline.vue` — presenter over `useEngineTimeline`.
- `app/components/workflow/EngineRequestSummary.vue` — summary strip.
- `app/components/workflow/EngineDocumentsPanel.vue` — documents list + upload/delete.
- `app/components/workflow/EngineActionsRail.vue` — sticky action panel (comment + transition buttons + claim state).
- `app/components/workflow/EngineRequestWizard.vue` — stepped create/edit flow.
- Tests: `app/tests/unit/composables/useEngineStagePath.test.ts`, `useEngineTimeline.test.ts`, `useEngineWizard.test.ts`.

Modified files:
- `app/pages/workflows/new.vue` — creator-gated picker; on select create instance and route to `?mode=wizard`.
- `app/pages/workflows/instances/[id].vue` — rebuilt: stepper + summary + wizard-mode / view-act-mode branch.

---

## Task 1: `useEngineStagePath` — dynamic stage ordering logic

**Files:**
- Create: `app/composables/useEngineStagePath.ts`
- Test: `app/tests/unit/composables/useEngineStagePath.test.ts`

**Interfaces:**
- Consumes: `WorkflowGraph`, `WorkflowGraphNode`, `EngineHistoryEntry` from `@/types/models`.
- Produces:
  - `type EngineStageStatus = 'visited' | 'current' | 'upcoming'`
  - `interface EngineStageStep { id: number; label: string; status: EngineStageStatus }`
  - `function buildStagePath(graph: WorkflowGraph | null, currentStageId: number | null, history: EngineHistoryEntry[]): EngineStageStep[]`
  - Ordering: `graph.nodes` sorted ascending by `sort_order` (ties broken by `id` ascending). `label` = `node.display_label ?? node.name`. A step is `current` iff `node.id === currentStageId`; `visited` iff not current AND (its id appears as a `to_stage.id` in `history` OR its `sort_order` is less than the current node's `sort_order`); otherwise `upcoming`. Self-loop/return history entries never add nodes (nodes come only from `graph.nodes`, so duplicates are structurally impossible).

- [ ] **Step 1: Write the failing test**

```ts
// app/tests/unit/composables/useEngineStagePath.test.ts
import { describe, expect, it } from 'vitest'
import { buildStagePath } from '@/composables/useEngineStagePath'
import type { WorkflowGraph, EngineHistoryEntry } from '@/types/models'

const node = (id: number, sort_order: number, name: string, extra: Partial<WorkflowGraph['nodes'][number]> = {}) => ({
  id, code: `S${id}`, name, display_label: null, is_initial: false, is_final: false, sort_order, ...extra,
})

const graph: WorkflowGraph = {
  nodes: [
    node(3, 30, 'اكتمال', { is_final: true }),
    node(1, 10, 'الإدخال', { is_initial: true }),
    node(2, 20, 'المراجعة'),
  ],
  edges: [],
}

const history: EngineHistoryEntry[] = [
  { id: 1, from_stage: { id: 1, code: 'S1', name: 'الإدخال' }, to_stage: { id: 2, code: 'S2', name: 'المراجعة' }, action_code: 'SUBMIT', performed_by: null, comments: null, created_at: '2026-06-01T10:00:00Z' },
]

describe('buildStagePath', () => {
  it('orders nodes by sort_order and marks visited/current/upcoming', () => {
    const path = buildStagePath(graph, 2, history)
    expect(path.map((s) => s.id)).toEqual([1, 2, 3])
    expect(path.map((s) => s.status)).toEqual(['visited', 'current', 'upcoming'])
    expect(path[0]!.label).toBe('الإدخال')
  })

  it('prefers display_label over name', () => {
    const g: WorkflowGraph = { nodes: [node(1, 10, 'الإدخال', { display_label: 'إدخال الطلب' })], edges: [] }
    expect(buildStagePath(g, 1, []).at(0)?.label).toBe('إدخال الطلب')
  })

  it('marks history-visited stages even when sort_order is unusual', () => {
    const path = buildStagePath(graph, 3, history)
    // stage 2 visited via history to_stage; stage 1 visited via lower sort_order
    expect(path.find((s) => s.id === 2)?.status).toBe('visited')
    expect(path.find((s) => s.id === 1)?.status).toBe('visited')
  })

  it('returns [] for a null graph', () => {
    expect(buildStagePath(null, 1, [])).toEqual([])
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test -- useEngineStagePath`
Expected: FAIL — `buildStagePath` is not exported / module not found.

- [ ] **Step 3: Write minimal implementation**

```ts
// app/composables/useEngineStagePath.ts
import type { EngineHistoryEntry, WorkflowGraph, WorkflowGraphNode } from '@/types/models'

export type EngineStageStatus = 'visited' | 'current' | 'upcoming'

export interface EngineStageStep {
  id: number
  label: string
  status: EngineStageStatus
}

export function buildStagePath(
  graph: WorkflowGraph | null,
  currentStageId: number | null,
  history: EngineHistoryEntry[],
): EngineStageStep[] {
  if (!graph) return []

  const ordered: WorkflowGraphNode[] = [...graph.nodes].sort(
    (a, b) => a.sort_order - b.sort_order || a.id - b.id,
  )

  const visitedIds = new Set<number>()
  for (const entry of history) {
    if (entry.to_stage) visitedIds.add(entry.to_stage.id)
  }

  const currentNode = ordered.find((n) => n.id === currentStageId) ?? null
  const currentSort = currentNode?.sort_order ?? null

  return ordered.map((node) => {
    let status: EngineStageStatus
    if (node.id === currentStageId) {
      status = 'current'
    } else if (
      visitedIds.has(node.id) ||
      (currentSort !== null && node.sort_order < currentSort)
    ) {
      status = 'visited'
    } else {
      status = 'upcoming'
    }
    return { id: node.id, label: node.display_label ?? node.name, status }
  })
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm test -- useEngineStagePath`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/composables/useEngineStagePath.ts app/tests/unit/composables/useEngineStagePath.test.ts
git commit -m "feat(workflow): add engine stage-path derivation composable"
```

---

## Task 2: `useEngineTimeline` — history ordering/formatting logic

**Files:**
- Create: `app/composables/useEngineTimeline.ts`
- Test: `app/tests/unit/composables/useEngineTimeline.test.ts`

**Interfaces:**
- Consumes: `EngineHistoryEntry` from `@/types/models`.
- Produces:
  - `interface TimelineItem { id: number; fromLabel: string | null; toLabel: string | null; actionCode: string | null; actorName: string; timestamp: string; comment: string | null; isLast: boolean }`
  - `function buildTimeline(entries: EngineHistoryEntry[]): TimelineItem[]` — sorts ascending by `created_at` (nulls last, stable by `id`), newest entry gets `isLast: true`. `actorName` = `performed_by?.name ?? 'النظام'`. `timestamp` = `created_at` formatted via `Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium', timeStyle: 'short' })`, or `'—'` when `created_at` is null. `fromLabel`/`toLabel` = the stage `name` or null.

- [ ] **Step 1: Write the failing test**

```ts
// app/tests/unit/composables/useEngineTimeline.test.ts
import { describe, expect, it } from 'vitest'
import { buildTimeline } from '@/composables/useEngineTimeline'
import type { EngineHistoryEntry } from '@/types/models'

const entry = (id: number, created_at: string | null, over: Partial<EngineHistoryEntry> = {}): EngineHistoryEntry => ({
  id,
  from_stage: { id: 1, code: 'S1', name: 'الإدخال' },
  to_stage: { id: 2, code: 'S2', name: 'المراجعة' },
  action_code: 'SUBMIT',
  performed_by: { id: 7, name: 'أحمد' },
  comments: null,
  created_at,
  ...over,
})

describe('buildTimeline', () => {
  it('sorts ascending by created_at and flags the last item', () => {
    const items = buildTimeline([
      entry(2, '2026-06-02T10:00:00Z'),
      entry(1, '2026-06-01T10:00:00Z'),
    ])
    expect(items.map((i) => i.id)).toEqual([1, 2])
    expect(items.at(-1)?.isLast).toBe(true)
    expect(items[0]!.isLast).toBe(false)
  })

  it('falls back to النظام actor and dash timestamp', () => {
    const items = buildTimeline([entry(1, null, { performed_by: null })])
    expect(items[0]!.actorName).toBe('النظام')
    expect(items[0]!.timestamp).toBe('—')
  })

  it('maps stage names and comment through', () => {
    const items = buildTimeline([entry(1, '2026-06-01T10:00:00Z', { comments: 'ملاحظة' })])
    expect(items[0]!.fromLabel).toBe('الإدخال')
    expect(items[0]!.toLabel).toBe('المراجعة')
    expect(items[0]!.comment).toBe('ملاحظة')
  })

  it('returns [] for no entries', () => {
    expect(buildTimeline([])).toEqual([])
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test -- useEngineTimeline`
Expected: FAIL — `buildTimeline` not exported.

- [ ] **Step 3: Write minimal implementation**

```ts
// app/composables/useEngineTimeline.ts
import type { EngineHistoryEntry } from '@/types/models'

export interface TimelineItem {
  id: number
  fromLabel: string | null
  toLabel: string | null
  actionCode: string | null
  actorName: string
  timestamp: string
  comment: string | null
  isLast: boolean
}

const dateFormatter = new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium', timeStyle: 'short' })

function sortKey(entry: EngineHistoryEntry): number {
  return entry.created_at ? Date.parse(entry.created_at) : Number.MAX_SAFE_INTEGER
}

export function buildTimeline(entries: EngineHistoryEntry[]): TimelineItem[] {
  const ordered = [...entries].sort((a, b) => sortKey(a) - sortKey(b) || a.id - b.id)
  return ordered.map((entry, index) => ({
    id: entry.id,
    fromLabel: entry.from_stage?.name ?? null,
    toLabel: entry.to_stage?.name ?? null,
    actionCode: entry.action_code,
    actorName: entry.performed_by?.name ?? 'النظام',
    timestamp: entry.created_at ? dateFormatter.format(new Date(entry.created_at)) : '—',
    comment: entry.comments,
    isLast: index === ordered.length - 1,
  }))
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm test -- useEngineTimeline`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/composables/useEngineTimeline.ts app/tests/unit/composables/useEngineTimeline.test.ts
git commit -m "feat(workflow): add engine timeline derivation composable"
```

---

## Task 3: `EngineStageStepper` component

**Files:**
- Create: `app/components/workflow/EngineStageStepper.vue`

**Interfaces:**
- Consumes: `buildStagePath`, `EngineStageStep` from `@/composables/useEngineStagePath`; shadcn stepper from `@/components/ui/stepper` (`Stepper`, `StepperItem`, `StepperIndicator`, `StepperSeparator`, `StepperTitle`, `StepperTrigger`).
- Produces: default export SFC with props `{ graph: WorkflowGraph | null; currentStageId: number | null; history: EngineHistoryEntry[] }`. Renders a horizontal, non-interactive stepper of the derived path with visited/current/upcoming visual states. Renders nothing (an empty state note) when path is empty.

- [ ] **Step 1: Implement the component**

```vue
<!-- app/components/workflow/EngineStageStepper.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { EngineHistoryEntry, WorkflowGraph } from '@/types/models'
import { buildStagePath } from '@/composables/useEngineStagePath'
import { Stepper, StepperItem, StepperIndicator, StepperSeparator, StepperTitle, StepperTrigger } from '@/components/ui/stepper'
import { Check } from 'lucide-vue-next'

const props = defineProps<{
  graph: WorkflowGraph | null
  currentStageId: number | null
  history: EngineHistoryEntry[]
}>()

const steps = computed(() => buildStagePath(props.graph, props.currentStageId, props.history))
const currentIndex = computed(() => {
  const i = steps.value.findIndex((s) => s.status === 'current')
  return i === -1 ? 1 : i + 1
})
</script>

<template>
  <div v-if="steps.length" dir="rtl" class="w-full overflow-x-auto">
    <Stepper :model-value="currentIndex" class="min-w-max">
      <StepperItem
        v-for="(step, index) in steps"
        :key="step.id"
        :step="index + 1"
        :completed="step.status === 'visited'"
        class="flex-1"
      >
        <StepperTrigger class="pointer-events-none flex-col gap-1">
          <StepperIndicator>
            <Check v-if="step.status === 'visited'" class="h-4 w-4" />
            <span v-else>{{ index + 1 }}</span>
          </StepperIndicator>
          <StepperTitle
            :class="step.status === 'current' ? 'text-foreground font-semibold' : 'text-muted-foreground'"
          >
            {{ step.label }}
          </StepperTitle>
        </StepperTrigger>
        <StepperSeparator v-if="index < steps.length - 1" class="mx-2" />
      </StepperItem>
    </Stepper>
  </div>
  <p v-else class="text-muted-foreground text-xs">لا توجد مراحل معرّفة لهذا المسار.</p>
</template>
```

> Note: the shadcn `Stepper` API in this repo lives in `@/components/ui/stepper`. If a subprop name (e.g. `:step`, `:completed`) differs from the installed version, read `app/components/ui/stepper/StepperItem.vue` and adapt the prop names; keep the visited/current/upcoming semantics identical.

- [ ] **Step 2: Typecheck / build sanity**

Run: `npx nuxi typecheck 2>&1 | grep -i EngineStageStepper || echo "no stepper type errors"`
Expected: `no stepper type errors` (or a clean typecheck run).

- [ ] **Step 3: Commit**

```bash
git add app/components/workflow/EngineStageStepper.vue
git commit -m "feat(workflow): add EngineStageStepper component"
```

---

## Task 4: `EngineTimeline` component

**Files:**
- Create: `app/components/workflow/EngineTimeline.vue`

**Interfaces:**
- Consumes: `buildTimeline`, `TimelineItem` from `@/composables/useEngineTimeline`.
- Produces: default export SFC with prop `{ entries: EngineHistoryEntry[] }`. Renders a vertical timeline; each item shows from→to stage, action code, actor, timestamp, optional comment; the last item is highlighted. Empty state when no entries.

- [ ] **Step 1: Implement the component**

```vue
<!-- app/components/workflow/EngineTimeline.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { EngineHistoryEntry } from '@/types/models'
import { buildTimeline } from '@/composables/useEngineTimeline'
import { Empty, EmptyMedia, EmptyHeader, EmptyTitle, EmptyDescription } from '@/components/ui/empty'
import { History } from 'lucide-vue-next'

const props = defineProps<{ entries: EngineHistoryEntry[] }>()
const items = computed(() => buildTimeline(props.entries))
</script>

<template>
  <ol v-if="items.length" dir="rtl" class="relative flex flex-col gap-4 border-s ps-6">
    <li v-for="item in items" :key="item.id" class="relative">
      <span
        class="absolute -start-[1.6rem] top-1 h-3 w-3 rounded-full ring-4 ring-background"
        :class="item.isLast ? 'bg-primary' : 'bg-muted-foreground/40'"
      />
      <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="font-medium">{{ item.fromLabel ?? '—' }}</span>
        <span class="text-muted-foreground">←</span>
        <span class="font-medium">{{ item.toLabel ?? '—' }}</span>
        <span v-if="item.actionCode" class="text-muted-foreground text-xs">({{ item.actionCode }})</span>
      </div>
      <p class="text-muted-foreground text-xs">{{ item.actorName }} · {{ item.timestamp }}</p>
      <p v-if="item.comment" class="text-foreground/80 mt-1 text-xs">{{ item.comment }}</p>
    </li>
  </ol>

  <Empty v-else>
    <EmptyMedia variant="icon"><History /></EmptyMedia>
    <EmptyHeader>
      <EmptyTitle>لا يوجد سجل بعد</EmptyTitle>
      <EmptyDescription>لم تُنفَّذ أي إجراءات على هذا الطلب حتى الآن.</EmptyDescription>
    </EmptyHeader>
  </Empty>
</template>
```

- [ ] **Step 2: Typecheck sanity**

Run: `npx nuxi typecheck 2>&1 | grep -i EngineTimeline || echo "no timeline type errors"`
Expected: `no timeline type errors`.

- [ ] **Step 3: Commit**

```bash
git add app/components/workflow/EngineTimeline.vue
git commit -m "feat(workflow): add EngineTimeline component"
```

---

## Task 5: `EngineRequestSummary` component

**Files:**
- Create: `app/components/workflow/EngineRequestSummary.vue`

**Interfaces:**
- Consumes: `EngineRequest` from `@/types/models`.
- Produces: default export SFC with prop `{ request: EngineRequest }`. Renders a labeled summary strip: reference, current stage, status, bank, merchant, amount (formatted), created date (formatted), claimed-by. Missing values render `'—'`.

- [ ] **Step 1: Implement the component**

```vue
<!-- app/components/workflow/EngineRequestSummary.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import type { EngineRequest } from '@/types/models'
import { Badge } from '@/components/ui/badge'

const props = defineProps<{ request: EngineRequest }>()

const dateFormatter = new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' })

const statusLabel: Record<EngineRequest['status'], string> = {
  ACTIVE: 'نشط',
  CLOSED: 'مكتمل',
  REJECTED: 'غير مؤهل',
}

const amountText = computed(() => {
  if (props.request.amount === null) return '—'
  const value = new Intl.NumberFormat('ar-EG').format(props.request.amount)
  return props.request.currency ? `${value} ${props.request.currency}` : value
})

const createdText = computed(() =>
  props.request.created_at ? dateFormatter.format(new Date(props.request.created_at)) : '—',
)

const items = computed(() => [
  { label: 'المرجع', value: props.request.reference },
  { label: 'المرحلة الحالية', value: props.request.current_stage?.name ?? '—' },
  { label: 'البنك', value: props.request.bank?.name ?? '—' },
  { label: 'التاجر', value: props.request.merchant?.name ?? '—' },
  { label: 'المبلغ', value: amountText.value },
  { label: 'تاريخ الإنشاء', value: createdText.value },
  { label: 'مُطالَب بواسطة', value: props.request.claimed_by_user?.name ?? '—' },
])
</script>

<template>
  <div dir="rtl" class="bg-muted/30 flex flex-wrap items-center gap-x-8 gap-y-3 rounded-lg border p-4">
    <div class="flex flex-col">
      <span class="text-muted-foreground text-xs">الحالة</span>
      <Badge variant="outline" class="mt-1 w-fit">{{ statusLabel[request.status] }}</Badge>
    </div>
    <div v-for="item in items" :key="item.label" class="flex flex-col">
      <span class="text-muted-foreground text-xs">{{ item.label }}</span>
      <span class="text-foreground mt-1 text-sm font-medium">{{ item.value }}</span>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Typecheck sanity**

Run: `npx nuxi typecheck 2>&1 | grep -i EngineRequestSummary || echo "no summary type errors"`
Expected: `no summary type errors`.

- [ ] **Step 3: Commit**

```bash
git add app/components/workflow/EngineRequestSummary.vue
git commit -m "feat(workflow): add EngineRequestSummary strip"
```

---

## Task 6: `EngineDocumentsPanel` component

**Files:**
- Create: `app/components/workflow/EngineDocumentsPanel.vue`

**Interfaces:**
- Consumes: `EngineRequestDocument` from `@/types/models`; `downloadUrl` from `useEngineRequestDocuments`.
- Produces: default export SFC with props `{ documents: EngineRequestDocument[]; requestId: number; canManage: boolean }` and emits `{ (e: 'upload', file: File): void; (e: 'remove', documentId: number): void }`. Lists documents (name, uploader name, date, download link); shows delete button when `canManage`; shows an upload `<input type="file">` when `canManage`. Empty state when no documents.

- [ ] **Step 1: Implement the component**

```vue
<!-- app/components/workflow/EngineDocumentsPanel.vue -->
<script setup lang="ts">
import type { EngineRequestDocument } from '@/types/models'
import { useEngineRequestDocuments } from '@/composables/useEngineRequestDocuments'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Empty, EmptyMedia, EmptyHeader, EmptyTitle, EmptyDescription } from '@/components/ui/empty'
import { FileText, Download, Trash2 } from 'lucide-vue-next'

const props = defineProps<{
  documents: EngineRequestDocument[]
  requestId: number
  canManage: boolean
}>()

const emit = defineEmits<{ upload: [file: File]; remove: [documentId: number] }>()

const { downloadUrl } = useEngineRequestDocuments()

const dateFormatter = new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' })

function uploaderName(doc: EngineRequestDocument): string {
  return typeof doc.uploaded_by === 'object' ? doc.uploaded_by.name : '—'
}

function formatDate(value: string | null): string {
  return value ? dateFormatter.format(new Date(value)) : '—'
}

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files?.[0]) {
    emit('upload', input.files[0])
    input.value = ''
  }
}
</script>

<template>
  <div dir="rtl" class="flex flex-col gap-4">
    <ul v-if="documents.length" class="flex flex-col divide-y">
      <li v-for="doc in documents" :key="doc.id" class="flex items-center gap-3 py-3">
        <FileText class="text-muted-foreground h-5 w-5 shrink-0" />
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium">{{ doc.original_name }}</p>
          <p class="text-muted-foreground text-xs">{{ uploaderName(doc) }} · {{ formatDate(doc.created_at) }}</p>
        </div>
        <a :href="downloadUrl(requestId, doc.id)" target="_blank" rel="noopener">
          <Button variant="ghost" size="icon" aria-label="تنزيل"><Download class="h-4 w-4" /></Button>
        </a>
        <Button
          v-if="canManage"
          variant="ghost"
          size="icon"
          aria-label="حذف"
          @click="emit('remove', doc.id)"
        >
          <Trash2 class="text-destructive h-4 w-4" />
        </Button>
      </li>
    </ul>

    <Empty v-else>
      <EmptyMedia variant="icon"><FileText /></EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>لا توجد مرفقات</EmptyTitle>
        <EmptyDescription>لم يُرفَق أي مستند بهذا الطلب بعد.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div v-if="canManage" class="border-t pt-4">
      <label class="text-muted-foreground mb-1 block text-xs">إرفاق مستند</label>
      <Input type="file" accept="application/pdf" @change="onFileChange" />
    </div>
  </div>
</template>
```

- [ ] **Step 2: Typecheck sanity**

Run: `npx nuxi typecheck 2>&1 | grep -i EngineDocumentsPanel || echo "no documents type errors"`
Expected: `no documents type errors`.

- [ ] **Step 3: Commit**

```bash
git add app/components/workflow/EngineDocumentsPanel.vue
git commit -m "feat(workflow): add EngineDocumentsPanel with upload and download"
```

---

## Task 7: `EngineActionsRail` component

**Files:**
- Create: `app/components/workflow/EngineActionsRail.vue`

**Interfaces:**
- Consumes: `WorkflowGraphEdge`, `EngineRequest` from `@/types/models`.
- Produces: default export SFC with props `{ availableActions: WorkflowGraphEdge[]; canAct: boolean; claimRequiredButNotHeld: boolean; showClaimButton: boolean; busy: boolean }`, a two-way bound `comment` via `defineModel<string>('comment')`, and emits `{ (e:'run', transitionId:number, requiresComment:boolean):void; (e:'claim'):void }`. Renders the comment textarea, the claim-required alert, the claim button, and one button per available action. Buttons disabled when `!canAct || busy`.

- [ ] **Step 1: Implement the component**

```vue
<!-- app/components/workflow/EngineActionsRail.vue -->
<script setup lang="ts">
import type { WorkflowGraphEdge } from '@/types/models'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Field, FieldLabel } from '@/components/ui/field'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { AlertCircle } from 'lucide-vue-next'

defineProps<{
  availableActions: WorkflowGraphEdge[]
  canAct: boolean
  claimRequiredButNotHeld: boolean
  showClaimButton: boolean
  busy: boolean
}>()

const emit = defineEmits<{ run: [transitionId: number, requiresComment: boolean]; claim: [] }>()
const comment = defineModel<string>('comment', { default: '' })
</script>

<template>
  <Card dir="rtl" class="sticky top-6 border-0 bg-muted/30 shadow-none">
    <CardHeader class="pb-2">
      <CardTitle class="text-sm font-semibold">إجراءات المرحلة</CardTitle>
    </CardHeader>
    <CardContent class="space-y-3">
      <Button v-if="showClaimButton" class="w-full" :disabled="busy" @click="emit('claim')">
        بدء المراجعة
      </Button>

      <Alert v-if="claimRequiredButNotHeld" role="status">
        <AlertCircle class="h-4 w-4" />
        <AlertDescription>يجب مطالبة هذه المرحلة قبل تنفيذ الإجراء.</AlertDescription>
      </Alert>

      <Field>
        <FieldLabel for="action-comment">ملاحظات</FieldLabel>
        <Textarea id="action-comment" v-model="comment" rows="3" :disabled="!canAct" />
      </Field>

      <div class="flex flex-col gap-2">
        <Button
          v-for="action in availableActions"
          :key="action.id"
          :disabled="!canAct || busy"
          @click="emit('run', action.id, action.requires_comment)"
        >
          {{ action.action_name ?? action.action_code }}
        </Button>
        <p v-if="!availableActions.length" class="text-muted-foreground text-xs">
          لا توجد إجراءات متاحة في هذه المرحلة.
        </p>
      </div>
    </CardContent>
  </Card>
</template>
```

- [ ] **Step 2: Typecheck sanity**

Run: `npx nuxi typecheck 2>&1 | grep -i EngineActionsRail || echo "no actions-rail type errors"`
Expected: `no actions-rail type errors`.

- [ ] **Step 3: Commit**

```bash
git add app/components/workflow/EngineActionsRail.vue
git commit -m "feat(workflow): add EngineActionsRail sidebar"
```

---

## Task 8: `useEngineWizard` — wizard step state machine

**Files:**
- Create: `app/composables/useEngineWizard.ts`
- Test: `app/tests/unit/composables/useEngineWizard.test.ts`

**Interfaces:**
- Consumes: `ResolvedFieldGroup` from `@/types/models`; a `saveDraft(data, version)` and `submit(data, version)` callback pair injected by the caller (keeps it pure/testable — no store import inside).
- Produces:
  ```ts
  interface WizardCallbacks {
    saveDraft: (data: Record<string, unknown>) => Promise<void>
    submit: (data: Record<string, unknown>) => Promise<void>
  }
  interface EngineWizard {
    stepIndex: Ref<number>
    totalSteps: ComputedRef<number>
    currentGroup: ComputedRef<ResolvedFieldGroup | null>
    isFirst: ComputedRef<boolean>
    isLast: ComputedRef<boolean>
    busy: Ref<boolean>
    next: (data: Record<string, unknown>) => Promise<void>   // saveDraft then advance
    back: () => void
    finish: (data: Record<string, unknown>) => Promise<void>  // submit
  }
  function useEngineWizard(groups: Ref<ResolvedFieldGroup[]>, cb: WizardCallbacks): EngineWizard
  ```
  `next` calls `cb.saveDraft(data)` then increments `stepIndex` (clamped to last). `back` decrements (clamped to 0). `finish` calls `cb.submit(data)`. `busy` is true during any async op. Groups are consumed in `sort_order` order (sort a copy ascending).

- [ ] **Step 1: Write the failing test**

```ts
// app/tests/unit/composables/useEngineWizard.test.ts
import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useEngineWizard } from '@/composables/useEngineWizard'
import type { ResolvedFieldGroup } from '@/types/models'

const group = (id: number, sort_order: number): ResolvedFieldGroup => ({
  id, name: `g${id}`, label: `مجموعة ${id}`, sort_order, fields: [],
})

describe('useEngineWizard', () => {
  it('orders groups by sort_order and exposes the current one', () => {
    const groups = ref([group(2, 20), group(1, 10)])
    const w = useEngineWizard(groups, { saveDraft: vi.fn(), submit: vi.fn() })
    expect(w.totalSteps.value).toBe(2)
    expect(w.currentGroup.value?.id).toBe(1)
    expect(w.isFirst.value).toBe(true)
    expect(w.isLast.value).toBe(false)
  })

  it('next saves the draft then advances', async () => {
    const saveDraft = vi.fn().mockResolvedValue(undefined)
    const groups = ref([group(1, 10), group(2, 20)])
    const w = useEngineWizard(groups, { saveDraft, submit: vi.fn() })
    await w.next({ a: 1 })
    expect(saveDraft).toHaveBeenCalledWith({ a: 1 })
    expect(w.stepIndex.value).toBe(1)
    expect(w.isLast.value).toBe(true)
  })

  it('back does not go below zero', () => {
    const groups = ref([group(1, 10)])
    const w = useEngineWizard(groups, { saveDraft: vi.fn(), submit: vi.fn() })
    w.back()
    expect(w.stepIndex.value).toBe(0)
  })

  it('finish calls submit with the data', async () => {
    const submit = vi.fn().mockResolvedValue(undefined)
    const groups = ref([group(1, 10)])
    const w = useEngineWizard(groups, { saveDraft: vi.fn(), submit })
    await w.finish({ b: 2 })
    expect(submit).toHaveBeenCalledWith({ b: 2 })
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test -- useEngineWizard`
Expected: FAIL — `useEngineWizard` not exported.

- [ ] **Step 3: Write minimal implementation**

```ts
// app/composables/useEngineWizard.ts
import { computed, ref, type ComputedRef, type Ref } from 'vue'
import type { ResolvedFieldGroup } from '@/types/models'

export interface WizardCallbacks {
  saveDraft: (data: Record<string, unknown>) => Promise<void>
  submit: (data: Record<string, unknown>) => Promise<void>
}

export interface EngineWizard {
  stepIndex: Ref<number>
  totalSteps: ComputedRef<number>
  currentGroup: ComputedRef<ResolvedFieldGroup | null>
  isFirst: ComputedRef<boolean>
  isLast: ComputedRef<boolean>
  busy: Ref<boolean>
  next: (data: Record<string, unknown>) => Promise<void>
  back: () => void
  finish: (data: Record<string, unknown>) => Promise<void>
}

export function useEngineWizard(
  groups: Ref<ResolvedFieldGroup[]>,
  cb: WizardCallbacks,
): EngineWizard {
  const stepIndex = ref(0)
  const busy = ref(false)

  const ordered = computed(() => [...groups.value].sort((a, b) => a.sort_order - b.sort_order))
  const totalSteps = computed(() => ordered.value.length)
  const currentGroup = computed(() => ordered.value[stepIndex.value] ?? null)
  const isFirst = computed(() => stepIndex.value === 0)
  const isLast = computed(() => stepIndex.value >= totalSteps.value - 1)

  async function next(data: Record<string, unknown>) {
    busy.value = true
    try {
      await cb.saveDraft(data)
      if (stepIndex.value < totalSteps.value - 1) stepIndex.value += 1
    } finally {
      busy.value = false
    }
  }

  function back() {
    if (stepIndex.value > 0) stepIndex.value -= 1
  }

  async function finish(data: Record<string, unknown>) {
    busy.value = true
    try {
      await cb.submit(data)
    } finally {
      busy.value = false
    }
  }

  return { stepIndex, totalSteps, currentGroup, isFirst, isLast, busy, next, back, finish }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm test -- useEngineWizard`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/composables/useEngineWizard.ts app/tests/unit/composables/useEngineWizard.test.ts
git commit -m "feat(workflow): add engine wizard step-state composable"
```

---

## Task 9: `EngineRequestWizard` component

**Files:**
- Create: `app/components/workflow/EngineRequestWizard.vue`

**Interfaces:**
- Consumes: `useEngineWizard` + `WizardCallbacks`; `DynamicForm` (props `fieldGroups`, `modelValue`, `mode`, `requestId`; exposes `validate()`); shadcn `Stepper` primitives; `ResolvedFieldGroup` from `@/types/models`.
- Produces: default export SFC with props `{ requestId: number; fieldGroups: ResolvedFieldGroup[]; version: number; initialData: Record<string, unknown> }` and emits `{ (e:'submitted'):void }`. Internally calls the store for save-draft and submit (initial-stage transition). Renders a vertical group stepper + the current group's `DynamicForm` (passed a single-element `[currentGroup]` array) + Back / Save-and-next / Submit controls. On the last step, Submit runs the initial-stage transition edge and emits `submitted`.
- Note on submit transition: the initial-stage transition is the single graph edge whose `from_stage_id === store.current.current_stage.id` when the current stage `is_initial`. If there are multiple, use the first. This mirrors how `availableActions` is computed on the instance page.

- [ ] **Step 1: Implement the component**

```vue
<!-- app/components/workflow/EngineRequestWizard.vue -->
<script setup lang="ts">
import { computed, ref, toRef } from 'vue'
import type { ResolvedFieldGroup } from '@/types/models'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useEngineWizard } from '@/composables/useEngineWizard'
import DynamicForm from '@/components/workflow/DynamicForm.vue'
import { Stepper, StepperItem, StepperIndicator, StepperSeparator, StepperTitle, StepperTrigger } from '@/components/ui/stepper'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Check, AlertTriangle } from 'lucide-vue-next'

const props = defineProps<{
  requestId: number
  fieldGroups: ResolvedFieldGroup[]
  version: number
  initialData: Record<string, unknown>
}>()

const emit = defineEmits<{ submitted: [] }>()

const store = useEngineRequestsStore()
const formRef = ref<InstanceType<typeof DynamicForm> | null>(null)
const formData = ref<Record<string, unknown>>({ ...props.initialData })
const submitError = ref<string | null>(null)

const groupsRef = toRef(props, 'fieldGroups')

const wizard = useEngineWizard(groupsRef, {
  saveDraft: async (data) => {
    await store.saveDraftData(props.requestId, data, store.current?.version ?? props.version)
  },
  submit: async (data) => {
    const edges = store.graph?.edges ?? []
    const initial = edges.find((e) => e.from_stage_id === store.current?.current_stage?.id)
    if (!initial) {
      submitError.value = 'لا يوجد إجراء بدء متاح لهذا الطلب.'
      throw new Error('no initial transition')
    }
    await store.executeTransition(props.requestId, initial.id, null, data, store.current?.version ?? props.version)
  },
})

const stepGroups = computed(() => (wizard.currentGroup.value ? [wizard.currentGroup.value] : []))
const stepperValue = computed(() => wizard.stepIndex.value + 1)

async function validateThen(action: (data: Record<string, unknown>) => Promise<void>) {
  submitError.value = null
  const result = await formRef.value?.validate()
  if (result && !result.valid) return
  const data = result?.values ?? formData.value
  try {
    await action(data)
  } catch {
    if (!submitError.value) submitError.value = 'تعذر حفظ الخطوة. حاول مرة أخرى.'
  }
}

async function onNext() {
  await validateThen((data) => wizard.next(data))
}

async function onSubmit() {
  await validateThen(async (data) => {
    await wizard.finish(data)
    emit('submitted')
  })
}
</script>

<template>
  <div dir="rtl" class="flex flex-col gap-6">
    <Stepper :model-value="stepperValue" orientation="vertical" class="w-full">
      <StepperItem
        v-for="(group, index) in [...fieldGroups].sort((a, b) => a.sort_order - b.sort_order)"
        :key="group.id"
        :step="index + 1"
        :completed="index < wizard.stepIndex.value"
      >
        <StepperTrigger class="pointer-events-none">
          <StepperIndicator>
            <Check v-if="index < wizard.stepIndex.value" class="h-4 w-4" />
            <span v-else>{{ index + 1 }}</span>
          </StepperIndicator>
          <StepperTitle>{{ group.label }}</StepperTitle>
        </StepperTrigger>
        <StepperSeparator v-if="index < fieldGroups.length - 1" />
      </StepperItem>
    </Stepper>

    <Card class="border-0 shadow">
      <CardContent class="p-4">
        <DynamicForm
          ref="formRef"
          v-model="formData"
          :field-groups="stepGroups"
          mode="edit"
          :request-id="requestId"
        />
      </CardContent>
    </Card>

    <Alert v-if="submitError" variant="destructive" role="alert">
      <AlertTriangle class="h-4 w-4" />
      <AlertDescription>{{ submitError }}</AlertDescription>
    </Alert>

    <div class="flex items-center justify-between gap-2">
      <Button variant="outline" :disabled="wizard.isFirst.value || wizard.busy.value" @click="wizard.back()">
        السابق
      </Button>
      <Button v-if="!wizard.isLast.value" :disabled="wizard.busy.value" @click="onNext">
        حفظ ومتابعة
      </Button>
      <Button v-else :disabled="wizard.busy.value" @click="onSubmit">
        إرسال الطلب
      </Button>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Typecheck sanity**

Run: `npx nuxi typecheck 2>&1 | grep -i EngineRequestWizard || echo "no wizard type errors"`
Expected: `no wizard type errors`.

- [ ] **Step 3: Commit**

```bash
git add app/components/workflow/EngineRequestWizard.vue
git commit -m "feat(workflow): add EngineRequestWizard stepped create flow"
```

---

## Task 10: Rework `workflows/new.vue` (creator-gated picker → wizard)

**Files:**
- Modify: `app/pages/workflows/new.vue`

**Interfaces:**
- Consumes: `useEngineRequestsStore` (`loadAvailableWorkflows`, `createInstance`, `availableWorkflows`, `loading`); `useAuthStore` (`currentRole`); `UserRole` from `@/types/enums`.
- Produces: on "بدء الطلب" creates an instance and navigates to `/workflows/instances/{id}?mode=wizard`. Non-creator roles see a "not permitted" empty state.

- [ ] **Step 1: Replace the file contents**

```vue
<!-- app/pages/workflows/new.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useAuthStore } from '@/stores/auth.store'
import { UserRole } from '@/types/enums'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Card, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Empty, EmptyMedia, EmptyHeader, EmptyTitle, EmptyDescription } from '@/components/ui/empty'
import { Inbox, ShieldAlert } from 'lucide-vue-next'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'requests' })

const store = useEngineRequestsStore()
const auth = useAuthStore()

const canCreate = computed(() => auth.currentRole === UserRole.DATA_ENTRY)

onMounted(() => {
  if (canCreate.value) store.loadAvailableWorkflows()
})

async function startWorkflow(versionId: number) {
  const instance = await store.createInstance({ workflow_version_id: versionId, data: {} })
  await navigateTo(`/workflows/instances/${instance.id}?mode=wizard`)
}
</script>

<template>
  <div class="flex flex-col gap-6 p-6" dir="rtl">
    <PageHeader
      title="طلب تمويل جديد"
      subtitle="اختر مسار العمل لبدء طلب تمويل جديد وإدخال بياناته خطوة بخطوة."
      :breadcrumbs="[
        { label: 'الرئيسية', to: '/dashboard' },
        { label: 'طلبات التمويل', to: '/workflows' },
        { label: 'طلب جديد' },
      ]"
    />

    <Empty v-if="!canCreate">
      <EmptyMedia variant="icon"><ShieldAlert /></EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>غير مصرح بإنشاء الطلبات</EmptyTitle>
        <EmptyDescription>
          إنشاء طلبات التمويل مقصور على موظفي الإدخال. دورك الحالي لا يسمح بذلك.
        </EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div v-else-if="store.loading" class="grid gap-4 sm:grid-cols-2">
      <Skeleton v-for="n in 2" :key="n" class="h-32 w-full rounded-xl" />
    </div>

    <Empty v-else-if="store.availableWorkflows.length === 0">
      <EmptyMedia variant="icon"><Inbox /></EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>لا توجد مسارات عمل متاحة</EmptyTitle>
        <EmptyDescription>لا يوجد مسار عمل منشور يمكنك بدء طلب جديد ضمنه حالياً.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div v-else class="grid gap-4 sm:grid-cols-2">
      <Card v-for="workflow in store.availableWorkflows" :key="workflow.version_id" class="border-0 shadow">
        <CardHeader>
          <CardTitle class="text-sm font-semibold">{{ workflow.name }}</CardTitle>
          <CardDescription class="text-xs">{{ workflow.code }} — الإصدار {{ workflow.version_number }}</CardDescription>
        </CardHeader>
        <CardFooter>
          <Button :data-testid="`create-instance-${workflow.id}`" @click="startWorkflow(workflow.version_id)">
            بدء الطلب
          </Button>
        </CardFooter>
      </Card>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Typecheck sanity**

Run: `npx nuxi typecheck 2>&1 | grep -iE "new.vue|workflows/new" || echo "no new-page type errors"`
Expected: `no new-page type errors`.

- [ ] **Step 3: Commit**

```bash
git add app/pages/workflows/new.vue
git commit -m "feat(workflow): gate create page to data-entry and route to wizard"
```

---

## Task 11: Rework `workflows/instances/[id].vue` (summary + stepper + wizard/view branch)

**Files:**
- Modify: `app/pages/workflows/instances/[id].vue`

**Interfaces:**
- Consumes: everything built above — `EngineStageStepper`, `EngineRequestSummary`, `EngineTimeline`, `EngineDocumentsPanel`, `EngineActionsRail`, `EngineRequestWizard`; store; `useEngineFormSchema`; `useEngineClaim`; `useEngineRequestActions`; `DynamicForm`; `ClaimBanner`; `UserRole`.
- Produces: the single create→edit→view→act surface. `?mode=wizard` + creator + `current_stage.is_initial` → renders `EngineRequestWizard`; otherwise → two-column view/act layout.

Key computeds to define (copied from the current page, plus new ones):
- `wizardMode = route.query.mode === 'wizard' && store.current.current_stage?.is_initial === true && store.current.created_by === auth.user?.id`
- `availableActions`, `stageRequiresClaim`, `isUnclaimed`, `showClaimButton`, `canAct`, `claimRequiredButNotHeld`, `claimHolderName` — same semantics as the current file (lines 46–62).
- `canManageDocuments = canAct.value` (upload/delete allowed when the user can act on the current stage).

- [ ] **Step 1: Replace the file contents**

```vue
<!-- app/pages/workflows/instances/[id].vue -->
<script setup lang="ts">
import { computed, ref } from 'vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useEngineFormSchema } from '@/composables/useEngineFormSchema'
import { useEngineRequestActions } from '@/composables/useEngineRequestActions'
import { useEngineClaim } from '@/composables/useEngineClaim'
import { useAuthStore } from '@/stores/auth.store'
import DynamicForm from '@/components/workflow/DynamicForm.vue'
import ClaimBanner from '@/components/workflow/ClaimBanner.vue'
import EngineStageStepper from '@/components/workflow/EngineStageStepper.vue'
import EngineRequestSummary from '@/components/workflow/EngineRequestSummary.vue'
import EngineTimeline from '@/components/workflow/EngineTimeline.vue'
import EngineDocumentsPanel from '@/components/workflow/EngineDocumentsPanel.vue'
import EngineActionsRail from '@/components/workflow/EngineActionsRail.vue'
import EngineRequestWizard from '@/components/workflow/EngineRequestWizard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert'
import { Skeleton } from '@/components/ui/skeleton'
import { AlertTriangle } from 'lucide-vue-next'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'requests' })

const route = useRoute()
const router = useRouter()
const requestId = computed(() => Number(route.params.id))

const store = useEngineRequestsStore()
const { fieldGroups, fetchSchema } = useEngineFormSchema()
const { executeAction, conflictError } = useEngineRequestActions()

const auth = useAuthStore()
const currentUserId = computed(() => auth.user?.id ?? null)
const { claim, isHeldByMe, heldByOther, claimedBy } = useEngineClaim(requestId, currentUserId)

const formRef = ref<InstanceType<typeof DynamicForm> | null>(null)
const formData = ref<Record<string, unknown>>({})
const comment = ref('')
const actionBusy = ref(false)

async function load() {
  await store.loadInstance(requestId.value)
  await fetchSchema(requestId.value)
  formData.value = store.current?.data ?? {}
  claimedBy.value = store.current?.claimed_by ?? null
}

onMounted(load)

const wizardMode = computed(
  () =>
    route.query.mode === 'wizard' &&
    store.current?.current_stage?.is_initial === true &&
    store.current?.created_by === auth.user?.id,
)

const availableActions = computed(() => {
  if (!store.current?.current_stage || !store.graph) return []
  return store.graph.edges.filter((edge) => edge.from_stage_id === store.current!.current_stage!.id)
})

const stageRequiresClaim = computed(() => store.current?.current_stage?.requires_claim === true)
const isUnclaimed = computed(() => claimedBy.value === null)
const showClaimButton = computed(() => stageRequiresClaim.value && isUnclaimed.value && !heldByOther.value)
const claimHolderName = computed(() => store.current?.claimed_by_user?.name ?? null)
const canAct = computed(() => !stageRequiresClaim.value || isHeldByMe.value)
const claimRequiredButNotHeld = computed(() => stageRequiresClaim.value && !isHeldByMe.value)

async function startReview() {
  await claim()
}

async function runAction(transitionId: number, requiresComment: boolean) {
  if (requiresComment && !comment.value.trim()) return
  const validation = await formRef.value?.validate()
  if (validation && !validation.valid) return
  actionBusy.value = true
  try {
    await executeAction(
      requestId.value,
      transitionId,
      comment.value || null,
      validation?.values ?? formData.value,
      store.current!.version,
    )
    comment.value = ''
    await load()
  } catch {
    // conflictError / fieldErrors already surfaced by the composable
  } finally {
    actionBusy.value = false
  }
}

async function onWizardSubmitted() {
  // Drop wizard mode and reload into the view/act layout.
  await router.replace({ path: route.path })
  await load()
}

async function onUpload(file: File) {
  await store.uploadDocument(requestId.value, file, null)
}

async function onRemove(documentId: number) {
  await store.removeDocument(requestId.value, documentId)
}
</script>

<template>
  <div class="flex flex-col gap-6 p-6" dir="rtl">
    <div v-if="store.loading && !store.current">
      <Skeleton class="mb-4 h-8 w-64" />
      <Skeleton class="h-48 w-full" />
    </div>

    <template v-else-if="store.current">
      <PageHeader
        :title="store.current.reference"
        subtitle="تفاصيل طلب التمويل والإجراءات المتاحة في المرحلة الحالية"
        :breadcrumbs="[
          { label: 'الرئيسية', to: '/dashboard' },
          { label: 'طلبات التمويل', to: '/workflows' },
          { label: store.current.reference },
        ]"
      />

      <EngineStageStepper
        :graph="store.graph"
        :current-stage-id="store.current.current_stage?.id ?? null"
        :history="store.history"
      />

      <EngineRequestSummary :request="store.current" />

      <Alert v-if="conflictError" variant="destructive" role="alert">
        <AlertTriangle class="h-4 w-4" />
        <AlertTitle>تعارض في التحديث</AlertTitle>
        <AlertDescription>تم تحديث الطلب من مستخدم آخر. تم تحديث البيانات المعروضة.</AlertDescription>
      </Alert>

      <ClaimBanner v-if="heldByOther" :holder-name="claimHolderName ?? 'مراجع آخر'" />

      <!-- Wizard mode: draft creator on the initial stage collects inputs step by step. -->
      <EngineRequestWizard
        v-if="wizardMode"
        :request-id="requestId"
        :field-groups="fieldGroups"
        :version="store.current.version"
        :initial-data="formData"
        @submitted="onWizardSubmitted"
      />

      <!-- View / act mode: two-column detail. -->
      <div v-else class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="min-w-0">
          <Tabs default-value="data" dir="rtl">
            <TabsList>
              <TabsTrigger value="data">البيانات</TabsTrigger>
              <TabsTrigger value="documents">المرفقات</TabsTrigger>
              <TabsTrigger value="history">السجل</TabsTrigger>
            </TabsList>

            <TabsContent value="data" class="mt-4">
              <Card class="border-0 shadow">
                <CardContent class="p-4">
                  <DynamicForm
                    ref="formRef"
                    v-model="formData"
                    :field-groups="fieldGroups"
                    :mode="canAct ? 'edit' : 'readonly'"
                    :request-id="requestId"
                  />
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="documents" class="mt-4">
              <Card class="border-0 shadow">
                <CardContent class="p-4">
                  <EngineDocumentsPanel
                    :documents="store.documents"
                    :request-id="requestId"
                    :can-manage="canAct"
                    @upload="onUpload"
                    @remove="onRemove"
                  />
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="history" class="mt-4">
              <Card class="border-0 shadow">
                <CardContent class="p-4">
                  <EngineTimeline :entries="store.history" />
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>

        <aside>
          <EngineActionsRail
            v-model:comment="comment"
            :available-actions="availableActions"
            :can-act="canAct"
            :claim-required-but-not-held="claimRequiredButNotHeld"
            :show-claim-button="showClaimButton"
            :busy="actionBusy"
            @run="runAction"
            @claim="startReview"
          />
        </aside>
      </div>
    </template>
  </div>
</template>
```

- [ ] **Step 2: Typecheck sanity**

Run: `npx nuxi typecheck 2>&1 | grep -iE "instances/\[id\]" || echo "no instance-page type errors"`
Expected: `no instance-page type errors`.

- [ ] **Step 3: Run the full unit suite**

Run: `npm test`
Expected: PASS — all non-quarantined tests green, including the three new composable test files.

- [ ] **Step 4: Commit**

```bash
git add app/pages/workflows/instances/[id].vue
git commit -m "feat(workflow): rebuild engine instance page with stepper, summary, wizard, rail"
```

---

## Task 12: Live verification and polish pass

**Files:** none (verification only; fix-forward small edits if issues found).

This task confirms the four reported breakages are fixed against the running dev server, using Playwright with a seeded auth store and mocked engine API (the pattern already established in this project; the mock script lives at the session scratchpad `mock-all.js`).

- [ ] **Step 1: Start the dev server (if not already running)**

Run (background): `npm run dev`
Expected: server on `http://localhost:3000`.

- [ ] **Step 2: Verify wizard (create-collects-inputs) as DATA_ENTRY**

Seed auth as `UserRole.DATA_ENTRY`, mock `available-workflows` to return one workflow and `form-schema` to return ≥2 field groups. Navigate `/workflows/new`, click "بدء الطلب", confirm redirect to `/workflows/instances/{id}?mode=wizard`, confirm the vertical group stepper renders, "حفظ ومتابعة" advances and calls `PATCH /draft`, and the final step shows "إرسال الطلب".
Expected: wizard advances per step; draft saved each step; submit triggers the initial transition.

- [ ] **Step 3: Verify view/act mode + documents + history**

Open an instance NOT in wizard mode (non-initial stage). Confirm: `EngineStageStepper` shows visited/current/upcoming; `EngineRequestSummary` strip renders; the two-column layout shows tabs (البيانات / المرفقات / السجل) with a sticky `EngineActionsRail`; the المرفقات tab lists documents with download links and (when `canAct`) an upload input; the السجل tab renders `EngineTimeline` sorted with the last entry highlighted.
Expected: all four previously-bare/broken areas now render correctly.

- [ ] **Step 4: Verify edit-in-place and claim gating**

On a claim-required stage while not holding the claim: `EngineActionsRail` shows the "بدء المراجعة" button and disables action buttons + comment; the data form is `readonly`. After claiming, actions enable and the form becomes editable.
Expected: claim gating matches the current behavior; edit folds into the instance page (no separate edit route).

- [ ] **Step 5: Final full test run + typecheck**

Run: `npm test && npx nuxi typecheck`
Expected: unit suite green; typecheck clean for the new/changed files.

- [ ] **Step 6: Commit any fixes**

```bash
git add -A
git commit -m "fix(workflow): polish engine request UX after live verification"
```

---

## Self-Review

**1. Spec coverage:**
- Goal 1 (wizard collects inputs by field-group) → Tasks 8, 9, 10, 11 (wizard state + component + create route + render branch). ✅
- Goal 2 (two-column detail: summary strip, tabbed main, sticky rail, dynamic stepper) → Tasks 3, 5, 6, 7, 11. ✅
- Goal 3 (edit-in-place, no separate route) → Task 11 (data tab `mode` toggles edit/readonly; wizard mode covers draft edit). ✅
- Goal 4 breakages: create-collects-no-inputs → Tasks 9–10; form/actions → Task 11 `EngineActionsRail`; edit → Task 11; bare documents/history → Tasks 4, 6, 11. ✅
- Components: EngineStageStepper (T3), EngineTimeline (T4), EngineRequestSummary (T5), EngineActionsRail (T7), EngineRequestWizard (T9). All five present, plus EngineDocumentsPanel (T6) which the spec's Documents section requires. ✅
- Data contract: all calls via existing store methods; no new endpoints. ✅
- Testing section (stepper ordering; timeline sorting; wizard advance/save/submit) → Tasks 1, 2, 8 as pure-logic tests (adapted to the repo's `node` vitest environment instead of component mounting, which is quarantined). ✅
- Error handling: 409 conflict alert (T11), load failure handled by existing `store.loading`/`store.current` guards, wizard save-draft failure alert (T9), validate-before-submit (T9, T11). ✅

**2. Placeholder scan:** No TBD/TODO/"add error handling" placeholders; every code step contains complete code. The one soft note (Task 3/9 "if a shadcn stepper subprop name differs, read the file and adapt") is a real, bounded contingency, not a placeholder — the semantics to preserve are stated.

**3. Type consistency:** `buildStagePath` / `EngineStageStep` / `EngineStageStatus` consistent across Tasks 1 and 3. `buildTimeline` / `TimelineItem` consistent across Tasks 2 and 4. `useEngineWizard` / `WizardCallbacks` / `EngineWizard` consistent across Tasks 8 and 9. `EngineActionsRail` prop names (`availableActions`, `canAct`, `claimRequiredButNotHeld`, `showClaimButton`, `busy`, `v-model:comment`) match between Task 7 and Task 11's usage. `EngineDocumentsPanel` props (`documents`, `requestId`, `canManage`) and events (`upload`, `remove`) match between Task 6 and Task 11. All store/composable signatures match the "Relevant existing signatures" block, which was read from source.

One spec deviation, deliberate and documented: the spec loosely says "draft stage"; the real model has no `DRAFT` status, so wizard mode keys off `current_stage.is_initial` + creator + `?mode=wizard` (pinned in Global Constraints and Task 11).
