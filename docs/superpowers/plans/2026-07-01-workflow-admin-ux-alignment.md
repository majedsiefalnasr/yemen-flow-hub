# Workflow Admin UX Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align `/workflows`, `/workflows/instances/[id]`, `/admin/workflows`, and `/admin/reference-data` with the current Yemen Flow Hub Nuxt UI while adding a draft-gated workflow canvas view.

**Architecture:** Keep the current normal workflow designer as the authoritative editor and add a focused canvas component that derives nodes/edges from existing workflow graph data. Uplift list/detail/reference-data pages using existing app primitives (`PageHeader`, `ScreenGuard`, `DataTable`, metric cards, badges, alerts, dialogs) without changing backend contracts.

**Tech Stack:** Nuxt 4, Vue 3, Pinia, shadcn-vue/reka-ui, TanStack Vue Table, lucide-vue-next, Vitest, Vue Test Utils, Playwright CLI for browser verification. Add `@vue-flow/core` only for the workflow canvas; if installation is unavailable, implement Task 1 with the SVG fallback described there.

## Global Constraints

- Work in `/Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code`.
- The approved spec is `docs/superpowers/specs/2026-07-01-workflow-admin-ux-design.md`.
- Backend API contracts stay unchanged.
- Only draft workflow versions are editable. Published or archived workflow versions are live/read-only.
- Do not persist canvas node coordinates in this pass.
- Do not replace the normal workflow designer with the canvas.
- Preserve role, screen, and capability gates.
- Use existing app primitives before adding new abstractions.
- Keep RTL alignment and mobile layouts free of text overlap.
- Do not use stale old-project wording in docs or UI copy for this work.

---

## File Structure

Create:

- `frontend/app/components/workflow/WorkflowCanvas.vue`  
  Visual workflow canvas. Consumes `WorkflowVersion`, fetches graph/stages/transitions/actions through existing composables, renders nodes/edges, and exposes safe draft-only actions.

- `frontend/app/tests/unit/components/WorkflowCanvas.test.ts`  
  Tests canvas node/edge rendering, draft-vs-live edit gating, edge labels, and local drag state.

Modify:

- `frontend/package.json` and `frontend/pnpm-lock.yaml`  
  Add `@vue-flow/core` if dependency installation is allowed during implementation.

- `frontend/app/pages/admin/workflows.vue`  
  Add normal/canvas view switch, read-only notice, canvas panel, and tighter workspace structure.

- `frontend/app/tests/unit/pages/WorkflowDesignerPage.test.ts`  
  Test the new view switch and read-only/draft affordances.

- `frontend/app/pages/workflows/index.vue`  
  Replace basic heading/table with PageHeader, metrics, filters, status badges, and operational table flow.

- `frontend/app/tests/unit/pages/workflows-index.test.ts`  
  Test PageHeader, queue/all switch, filters, status badge rendering, and empty states.

- `frontend/app/pages/workflows/instances/[id].vue`  
  Add PageHeader hierarchy, claim summary, action panel, clearer disabled-action explanation, and improved history/documents containers.

- `frontend/app/tests/unit/pages/workflows-instance-detail.test.ts`  
  Test claim-required disabled state, action panel separation, and header metadata.

- `frontend/app/pages/admin/reference-data.vue`  
  Add selected-table summary, selected value metric, clearer two-pane behavior, and stronger empty states.

- `frontend/app/tests/unit/pages/ReferenceDataPage.test.ts`  
  Test selected table summary, value count metric, protected delete state, and empty states.

No backend files should change.

---

### Task 1: Workflow Canvas Component

**Files:**
- Create: `frontend/app/components/workflow/WorkflowCanvas.vue`
- Create: `frontend/app/tests/unit/components/WorkflowCanvas.test.ts`
- Modify: `frontend/package.json`
- Modify: `frontend/pnpm-lock.yaml`

**Interfaces:**
- Consumes: `WorkflowVersion`, `WorkflowGraph`, `WorkflowGraphNode`, `WorkflowGraphEdge` from `frontend/app/types/models.ts`.
- Consumes: `useWorkflowGraph().fetchGraph(versionId: number)`.
- Produces: `WorkflowCanvas` prop interface:

```ts
defineProps<{
  version: WorkflowVersion
}>()
```

- Produces DOM hooks used by tests:
  - `data-testid="workflow-canvas"`
  - `data-testid="workflow-canvas-readonly"`
  - `data-testid="workflow-canvas-node-${node.id}"`
  - `data-testid="workflow-canvas-edge-${edge.id}"`

- Produces emitted events for later integration:

```ts
const emit = defineEmits<{
  inspectNode: [stageId: number]
  inspectEdge: [transitionId: number]
  addTransition: []
}>()
```

- If `@vue-flow/core` cannot be installed, produce the same public props/events/test ids using an SVG-based implementation so Task 2 does not change.

- [ ] **Step 1: Add Vue Flow dependency**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm add @vue-flow/core
```

Expected: `package.json` contains `@vue-flow/core`, and `pnpm-lock.yaml` changes.

If network/dependency installation is blocked, do not change APIs. Implement `WorkflowCanvas.vue` using a local SVG fallback:

```vue
<svg data-testid="workflow-canvas" class="h-[620px] w-full">
  <g v-for="edge in graph?.edges ?? []" :key="edge.id" :data-testid="`workflow-canvas-edge-${edge.id}`">
    <path :d="edgePath(edge)" />
    <text>{{ edge.action_name || edge.action_code }}</text>
  </g>
  <g v-for="node in graph?.nodes ?? []" :key="node.id" :data-testid="`workflow-canvas-node-${node.id}`">
    <rect />
    <text>{{ node.display_label || node.name }}</text>
  </g>
</svg>
```

- [ ] **Step 2: Write failing canvas tests**

Add `frontend/app/tests/unit/components/WorkflowCanvas.test.ts`:

```ts
// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import WorkflowCanvas from '@/components/workflow/WorkflowCanvas.vue'

const mockGraph = {
  nodes: [
    {
      id: 1,
      code: 'INTAKE',
      name: 'استلام',
      display_label: null,
      is_initial: true,
      is_final: false,
      sort_order: 0,
    },
    {
      id: 2,
      code: 'APPROVED',
      name: 'اعتماد',
      display_label: 'اعتماد نهائي',
      is_initial: false,
      is_final: true,
      sort_order: 1,
    },
  ],
  edges: [
    {
      id: 9,
      from_stage_id: 1,
      to_stage_id: 2,
      action_id: 3,
      action_code: 'APPROVE',
      action_name: 'اعتماد',
      requires_comment: true,
      is_self_loop: false,
      is_return: false,
    },
  ],
}

const fetchGraph = vi.fn()
const graph = ref(mockGraph)
const loading = ref(false)
const error = ref<string | null>(null)

vi.mock('@/composables/useWorkflowGraph', () => ({
  useWorkflowGraph: () => ({ graph, loading, error, fetchGraph }),
}))

const draftVersion = {
  id: 10,
  workflow_definition_id: 1,
  version_number: 2,
  state: 'DRAFT',
  is_editable: true,
  published_at: null,
  created_at: null,
  updated_at: null,
  version: 1,
} as const

const publishedVersion = {
  ...draftVersion,
  state: 'PUBLISHED',
  is_editable: false,
} as const

describe('WorkflowCanvas', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    graph.value = mockGraph
    loading.value = false
    error.value = null
  })

  it('loads the workflow graph for the selected version', async () => {
    mount(WorkflowCanvas, { props: { version: draftVersion } })
    await flushPromises()
    expect(fetchGraph).toHaveBeenCalledWith(10)
  })

  it('renders stage nodes and action edge labels', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: draftVersion } })
    await flushPromises()
    expect(wrapper.get('[data-testid="workflow-canvas-node-1"]').text()).toContain('استلام')
    expect(wrapper.get('[data-testid="workflow-canvas-node-2"]').text()).toContain('اعتماد نهائي')
    expect(wrapper.get('[data-testid="workflow-canvas-edge-9"]').text()).toContain('اعتماد')
    expect(wrapper.get('[data-testid="workflow-canvas-edge-9"]').text()).toContain('تعليق')
  })

  it('shows edit affordances for draft versions', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: draftVersion } })
    await flushPromises()
    expect(wrapper.text()).toContain('إضافة انتقال')
    expect(wrapper.find('[data-testid="workflow-canvas-readonly"]').exists()).toBe(false)
  })

  it('is inspect-only for published versions', async () => {
    const wrapper = mount(WorkflowCanvas, { props: { version: publishedVersion } })
    await flushPromises()
    expect(wrapper.text()).not.toContain('إضافة انتقال')
    expect(wrapper.get('[data-testid="workflow-canvas-readonly"]').text()).toContain('للعرض فقط')
  })
})
```

- [ ] **Step 3: Run test to verify it fails**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm test -- app/tests/unit/components/WorkflowCanvas.test.ts
```

Expected: FAIL because `WorkflowCanvas.vue` does not exist.

- [ ] **Step 4: Implement `WorkflowCanvas.vue`**

Create `frontend/app/components/workflow/WorkflowCanvas.vue` using Vue Flow when installed:

```vue
<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { VueFlow, type Edge, type Node } from '@vue-flow/core'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import type { WorkflowGraphEdge, WorkflowGraphNode, WorkflowVersion } from '@/types/models'
import { useWorkflowGraph } from '@/composables/useWorkflowGraph'
import { AlertCircle, GitBranch, Lock, Maximize2, Plus } from 'lucide-vue-next'

const props = defineProps<{ version: WorkflowVersion }>()
const emit = defineEmits<{
  inspectNode: [stageId: number]
  inspectEdge: [transitionId: number]
  addTransition: []
}>()

const { graph, loading, error, fetchGraph } = useWorkflowGraph()
const editable = computed(() => props.version.state === 'DRAFT' && props.version.is_editable)

function nodePosition(node: WorkflowGraphNode) {
  const column = Math.max(node.sort_order, 0)
  return { x: column * 260, y: node.is_final ? 180 : 40 + (column % 2) * 120 }
}

function nodeLabel(node: WorkflowGraphNode) {
  return node.display_label || node.name
}

const nodes = computed<Node[]>(() =>
  (graph.value?.nodes ?? []).map((node) => ({
    id: String(node.id),
    type: 'default',
    position: nodePosition(node),
    draggable: editable.value,
    selectable: true,
    data: {
      label: nodeLabel(node),
      code: node.code,
      isInitial: node.is_initial,
      isFinal: node.is_final,
    },
    class: [
      'workflow-canvas-node',
      node.is_initial ? 'workflow-canvas-node--initial' : '',
      node.is_final ? 'workflow-canvas-node--final' : '',
    ].join(' '),
  })),
)

const edges = computed<Edge[]>(() =>
  (graph.value?.edges ?? []).map((edge) => ({
    id: String(edge.id),
    source: String(edge.from_stage_id),
    target: String(edge.to_stage_id),
    label: edgeLabel(edge),
    animated: edge.is_return,
    data: edge,
  })),
)

function edgeLabel(edge: WorkflowGraphEdge) {
  const label = edge.action_name || edge.action_code || `#${edge.action_id}`
  return edge.requires_comment ? `${label} · تعليق مطلوب` : label
}

function load() {
  void fetchGraph(props.version.id)
}

onMounted(load)
watch(() => props.version.id, load)
</script>

<template>
  <section class="space-y-3" aria-label="لوحة مسار العمل">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="font-section text-sm font-semibold">لوحة مسار العمل</h3>
        <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
          عرض مرئي للمراحل والانتقالات. التعديل متاح فقط للنسخ المسودة.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <Badge v-if="editable" variant="secondary">قابلة للتعديل</Badge>
        <Badge v-else data-testid="workflow-canvas-readonly" variant="outline" class="gap-1">
          <Lock class="h-3 w-3" />
          للعرض فقط
        </Badge>
        <Button v-if="editable" size="sm" @click="emit('addTransition')">
          <Plus class="h-3.5 w-3.5" />
          إضافة انتقال
        </Button>
      </div>
    </div>

    <Alert v-if="error" variant="destructive" role="alert">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>تعذّر تحميل لوحة مسار العمل</AlertTitle>
      <AlertDescription>{{ error }}</AlertDescription>
    </Alert>

    <div v-else-if="loading" class="grid gap-2">
      <Skeleton class="h-[420px] w-full rounded-lg" />
    </div>

    <Empty v-else-if="!graph || graph.nodes.length === 0">
      <EmptyHeader>
        <EmptyTitle>لا توجد مراحل لعرضها</EmptyTitle>
        <EmptyDescription>أضف مراحل وانتقالات في العرض التفصيلي.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div v-else data-testid="workflow-canvas" class="border-border h-[620px] overflow-hidden rounded-lg border bg-background">
      <VueFlow
        :nodes="nodes"
        :edges="edges"
        dir="rtl"
        fit-view-on-init
        :nodes-draggable="editable"
        :edges-updatable="false"
        @node-click="({ node }) => emit('inspectNode', Number(node.id))"
        @edge-click="({ edge }) => emit('inspectEdge', Number(edge.id))"
      >
        <template #node-default="{ data, id }">
          <div
            :data-testid="`workflow-canvas-node-${id}`"
            class="border-border bg-card min-w-44 rounded-md border p-3 shadow-sm"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="text-sm font-semibold">{{ data.label }}</div>
                <div class="text-muted-foreground font-mono text-xs">{{ data.code }}</div>
              </div>
              <GitBranch class="text-muted-foreground h-4 w-4" />
            </div>
            <div class="mt-2 flex flex-wrap gap-1">
              <Badge v-if="data.isInitial" variant="secondary">بداية</Badge>
              <Badge v-if="data.isFinal" variant="secondary">نهاية</Badge>
            </div>
          </div>
        </template>

        <template #edge-label="{ edge }">
          <span :data-testid="`workflow-canvas-edge-${edge.id}`">{{ edge.label }}</span>
        </template>
      </VueFlow>
    </div>
  </section>
</template>
```

If using the SVG fallback, keep the same test ids and event emissions.

- [ ] **Step 5: Run component test**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm test -- app/tests/unit/components/WorkflowCanvas.test.ts
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/package.json frontend/pnpm-lock.yaml frontend/app/components/workflow/WorkflowCanvas.vue frontend/app/tests/unit/components/WorkflowCanvas.test.ts
git commit -m "feat(workflow): add workflow canvas component"
```

---

### Task 2: Integrate Canvas Into `/admin/workflows`

**Files:**
- Modify: `frontend/app/pages/admin/workflows.vue`
- Modify: `frontend/app/tests/unit/pages/WorkflowDesignerPage.test.ts`

**Interfaces:**
- Consumes: `WorkflowCanvas` from Task 1.
- Produces: a page-level `designerView` ref with values `'normal' | 'canvas'`.
- Produces visible Arabic view labels:
  - `تفصيلي`
  - `لوحة`
- Produces read-only notice text:
  - `هذه النسخة منشورة أو مؤرشفة، لذلك يمكن عرضها فقط.`

- [ ] **Step 1: Write failing page tests**

Append to `frontend/app/tests/unit/pages/WorkflowDesignerPage.test.ts`:

```ts
it('shows normal and canvas view switches for selected versions', async () => {
  const wrapper = await mountPage(['VIEW'])

  expect(wrapper.text()).toContain('تفصيلي')
  expect(wrapper.text()).toContain('لوحة')
})

it('renders read-only copy for published versions', async () => {
  const wrapper = await mountPage(['VIEW'])

  expect(wrapper.text()).toContain('هذه النسخة منشورة أو مؤرشفة، لذلك يمكن عرضها فقط')
})

it('can switch to the canvas view', async () => {
  const wrapper = await mountPage(['VIEW'])
  const canvasButton = wrapper.findAll('button').find((button) => button.text().includes('لوحة'))

  expect(canvasButton).toBeDefined()
  await canvasButton!.trigger('click')

  expect(wrapper.text()).toContain('لوحة مسار العمل')
})
```

Stub `WorkflowCanvas` inside `mountPage` if Vue Flow causes jsdom friction:

```ts
stubs: {
  Teleport: true,
  NuxtLink: true,
  WorkflowCanvas: { template: '<section>لوحة مسار العمل</section>' },
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm test -- app/tests/unit/pages/WorkflowDesignerPage.test.ts
```

Expected: FAIL because the page has no normal/canvas view switch.

- [ ] **Step 3: Implement page integration**

In `frontend/app/pages/admin/workflows.vue`, add:

```ts
import WorkflowCanvas from '@/components/workflow/WorkflowCanvas.vue'
import { Eye, GitBranch } from 'lucide-vue-next'

const designerView = ref<'normal' | 'canvas'>('normal')
const selectedVersionEditable = computed(
  () => selectedVersion.value?.state === 'DRAFT' && selectedVersion.value.is_editable,
)
```

In the selected-version template, add a compact notice:

```vue
<Alert v-if="selectedVersion && !selectedVersionEditable" class="border-muted bg-muted/30">
  <Eye class="h-4 w-4" />
  <AlertTitle>نسخة للعرض فقط</AlertTitle>
  <AlertDescription>
    هذه النسخة منشورة أو مؤرشفة، لذلك يمكن عرضها فقط. استنسخ نسخة مسودة لإجراء تعديلات.
  </AlertDescription>
</Alert>
```

Replace the direct tabs block with a view switch:

```vue
<Tabs v-else v-model="designerView" dir="rtl">
  <TabsList class="w-full justify-start">
    <TabsTrigger value="normal">تفصيلي</TabsTrigger>
    <TabsTrigger value="canvas">
      <GitBranch class="h-3.5 w-3.5" />
      لوحة
    </TabsTrigger>
  </TabsList>

  <TabsContent value="normal" class="mt-4">
    <!-- existing stages/routing/transitions/fields/actions tabs move here unchanged -->
  </TabsContent>

  <TabsContent value="canvas" class="mt-4">
    <Card class="border-0 shadow">
      <CardContent class="p-4">
        <WorkflowCanvas :version="selectedVersion" />
      </CardContent>
    </Card>
  </TabsContent>
</Tabs>
```

Keep the existing inner normal designer tabs as-is inside `value="normal"`.

- [ ] **Step 4: Run page test**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm test -- app/tests/unit/pages/WorkflowDesignerPage.test.ts
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app/pages/admin/workflows.vue frontend/app/tests/unit/pages/WorkflowDesignerPage.test.ts
git commit -m "feat(workflow): add designer canvas view"
```

---

### Task 3: Uplift `/workflows` List

**Files:**
- Modify: `frontend/app/pages/workflows/index.vue`
- Modify: `frontend/app/tests/unit/pages/workflows-index.test.ts`

**Interfaces:**
- Consumes: `useEngineRequestsStore().queue`, `.instances`, `.loading`, `.error`.
- Produces local state:

```ts
const view = ref<'queue' | 'all'>('queue')
const query = ref('')
const selectedStatus = ref('all')
const selectedStage = ref('all')
```

- Produces computed rows:

```ts
const filteredRows = computed(() => rows.value.filter(/* reference/status/stage filters */))
```

- [ ] **Step 1: Write failing list tests**

Replace or extend `frontend/app/tests/unit/pages/workflows-index.test.ts` with:

```ts
it('renders the operational page header and metrics', () => {
  const store = useEngineRequestsStore()
  store.queue = []
  store.instances = []

  const wrapper = mount(WorkflowsIndexPage, {
    global: { stubs: { NuxtLink: true } },
  })

  expect(wrapper.text()).toContain('سير العمل الديناميكي')
  expect(wrapper.text()).toContain('طابوري')
  expect(wrapper.text()).toContain('جميع الطلبات')
})

it('filters rows by reference search', async () => {
  const store = useEngineRequestsStore()
  store.queue = [
    { id: 1, reference: 'ENG-001', status: 'ACTIVE', current_stage: { name: 'استلام' } },
    { id: 2, reference: 'ENG-002', status: 'ACTIVE', current_stage: { name: 'اعتماد' } },
  ] as any

  const wrapper = mount(WorkflowsIndexPage, {
    global: { stubs: { NuxtLink: true } },
  })

  await wrapper.get('input[placeholder="بحث بالمرجع..."]').setValue('ENG-002')

  expect(wrapper.text()).toContain('ENG-002')
  expect(wrapper.text()).not.toContain('ENG-001')
})

it('shows explicit view action for rows', () => {
  const store = useEngineRequestsStore()
  store.queue = [
    { id: 1, reference: 'ENG-001', status: 'ACTIVE', current_stage: { name: 'استلام' } },
  ] as any

  const wrapper = mount(WorkflowsIndexPage, {
    global: { stubs: { NuxtLink: true } },
  })

  expect(wrapper.text()).toContain('عرض')
})
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm test -- app/tests/unit/pages/workflows-index.test.ts
```

Expected: FAIL because search/metrics/header structure is incomplete.

- [ ] **Step 3: Implement list UX**

In `frontend/app/pages/workflows/index.vue`, add imports:

```ts
import PageHeader from '@/components/layout/PageHeader.vue'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { BriefcaseBusiness, FileText, Search, Timer } from 'lucide-vue-next'
```

Add computed state:

```ts
const query = ref('')

const rows = computed(() => (view.value === 'queue' ? store.queue : store.instances))
const filteredRows = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return rows.value
  return rows.value.filter((item) => item.reference.toLowerCase().includes(q))
})

const stats = computed(() => ({
  queue: store.queue.length,
  all: store.instances.length,
  waiting: rows.value.filter((item) => item.status === 'ACTIVE').length,
}))

function statusLabel(status: string) {
  if (status === 'ACTIVE') return 'نشط'
  if (status === 'COMPLETED') return 'مكتمل'
  if (status === 'CANCELLED') return 'ملغى'
  return status
}
```

Use template shape:

```vue
<div class="mx-auto max-w-[1600px] space-y-6 p-6" dir="rtl">
  <PageHeader
    title="سير العمل الديناميكي"
    subtitle="متابعة طلبات محرك سير العمل والطلبات التي تنتظر إجراءك"
    :breadcrumbs="[{ label: 'الرئيسية', to: '/dashboard' }, { label: 'سير العمل' }]"
  >
    <template #actions>
      <Button @click="navigateTo('/workflows/new')">
        <FileText class="me-2 h-4 w-4" />
        طلب جديد
      </Button>
    </template>
  </PageHeader>

  <MetricGrid :columns="3">
    <MetricCard label="طابوري" :value="stats.queue" :icon="BriefcaseBusiness" />
    <MetricCard label="جميع الطلبات" :value="stats.all" :icon="FileText" tone="info" />
    <MetricCard label="بانتظار الإجراء" :value="stats.waiting" :icon="Timer" tone="warning" />
  </MetricGrid>

  <div class="flex flex-wrap items-center justify-between gap-3">
    <Tabs v-model="view">
      <TabsList variant="line">
        <TabsTrigger value="queue">طابوري</TabsTrigger>
        <TabsTrigger value="all">جميع الطلبات</TabsTrigger>
      </TabsList>
    </Tabs>
    <div class="relative w-full sm:w-72">
      <Search class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
      <Input v-model="query" class="ps-9" placeholder="بحث بالمرجع..." />
    </div>
  </div>
```

Render `filteredRows` instead of `rows`, and render status as:

```vue
<Badge variant="secondary">{{ statusLabel(instance.status) }}</Badge>
```

- [ ] **Step 4: Run test**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm test -- app/tests/unit/pages/workflows-index.test.ts
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app/pages/workflows/index.vue frontend/app/tests/unit/pages/workflows-index.test.ts
git commit -m "feat(workflow): uplift workflow queue page"
```

---

### Task 4: Uplift Workflow Instance Detail

**Files:**
- Modify: `frontend/app/pages/workflows/instances/[id].vue`
- Modify: `frontend/app/tests/unit/pages/workflows-instance-detail.test.ts`

**Interfaces:**
- Consumes: existing `store.current`, `store.graph`, claim state, `DynamicForm`.
- Produces visible action-panel heading: `إجراءات المرحلة`.
- Produces disabled explanation: `يجب مطالبة هذه المرحلة قبل تنفيذ الإجراء.`

- [ ] **Step 1: Write failing detail tests**

Append to `frontend/app/tests/unit/pages/workflows-instance-detail.test.ts`:

```ts
it('renders a stage action panel separate from the form', async () => {
  const store = useEngineRequestsStore()
  store.current = makeInstance()
  store.graph = {
    nodes: [],
    edges: [
      {
        id: 9,
        from_stage_id: 1,
        to_stage_id: 2,
        action_id: 1,
        action_code: 'SUBMIT',
        action_name: 'إرسال',
        requires_comment: false,
        is_self_loop: false,
        is_return: false,
      },
    ],
  }

  const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
  await wrapper.vm.$nextTick()

  expect(wrapper.text()).toContain('إجراءات المرحلة')
  expect(wrapper.text()).toContain('إرسال')
})

it('explains why actions are disabled when claim is required and not held', async () => {
  const store = useEngineRequestsStore()
  store.current = makeInstance({
    current_stage: {
      id: 1,
      code: 'INTAKE',
      name: 'استلام',
      is_initial: true,
      is_final: false,
      sla_duration_minutes: null,
      requires_claim: true,
    },
    claimed_by: null,
  })
  store.graph = {
    nodes: [],
    edges: [
      {
        id: 9,
        from_stage_id: 1,
        to_stage_id: 2,
        action_id: 1,
        action_code: 'SUBMIT',
        action_name: 'إرسال',
        requires_comment: false,
        is_self_loop: false,
        is_return: false,
      },
    ],
  }

  const wrapper = mount(WorkflowInstanceDetailPage, { global: { stubs } })
  await wrapper.vm.$nextTick()

  expect(wrapper.text()).toContain('يجب مطالبة هذه المرحلة قبل تنفيذ الإجراء')
  expect(wrapper.find('button:disabled').exists()).toBe(true)
})
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm test -- app/tests/unit/pages/workflows-instance-detail.test.ts
```

Expected: FAIL because the action panel/explanation copy does not exist yet.

- [ ] **Step 3: Implement detail hierarchy**

Add imports:

```ts
import PageHeader from '@/components/layout/PageHeader.vue'
import { AlertCircle, AlertTriangle, FileText, LockKeyhole } from 'lucide-vue-next'
```

Add computed explanation:

```ts
const claimRequiredButNotHeld = computed(() => stageRequiresClaim.value && !isHeldByMe.value)
```

Replace the top heading with:

```vue
<PageHeader
  :title="store.current.reference"
  subtitle="تفاصيل طلب محرك سير العمل والإجراءات المتاحة في المرحلة الحالية"
  :breadcrumbs="[
    { label: 'الرئيسية', to: '/dashboard' },
    { label: 'سير العمل', to: '/workflows' },
    { label: store.current.reference },
  ]"
>
  <template #actions>
    <Button v-if="showClaimButton" size="sm" @click="startReview">بدء المراجعة</Button>
  </template>
</PageHeader>
```

Add status strip below header:

```vue
<div class="flex flex-wrap items-center gap-2">
  <Badge variant="outline">{{ store.current.current_stage?.name ?? '—' }}</Badge>
  <Badge v-if="stageRequiresClaim" variant="secondary">
    <LockKeyhole class="h-3 w-3" />
    يتطلب مطالبة
  </Badge>
</div>
```

Move comments/actions into a distinct card after `DynamicForm`:

```vue
<Card class="mt-4 border-0 bg-muted/30 shadow-none">
  <CardHeader class="pb-2">
    <CardTitle class="text-sm font-semibold">إجراءات المرحلة</CardTitle>
  </CardHeader>
  <CardContent class="space-y-3">
    <Alert v-if="claimRequiredButNotHeld" role="status">
      <AlertCircle class="h-4 w-4" />
      <AlertDescription>
        يجب مطالبة هذه المرحلة قبل تنفيذ الإجراء.
      </AlertDescription>
    </Alert>
    <Field>
      <FieldLabel for="comment">ملاحظات</FieldLabel>
      <Textarea id="comment" v-model="comment" rows="3" :disabled="!canAct" />
    </Field>
    <div class="flex flex-wrap gap-2">
      <Button
        v-for="action in availableActions"
        :key="action.id"
        :disabled="!canAct"
        @click="runAction(action.id, action.requires_comment)"
      >
        {{ action.action_name ?? action.action_code }}
      </Button>
    </div>
  </CardContent>
</Card>
```

- [ ] **Step 4: Run test**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm test -- app/tests/unit/pages/workflows-instance-detail.test.ts
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add 'frontend/app/pages/workflows/instances/[id].vue' frontend/app/tests/unit/pages/workflows-instance-detail.test.ts
git commit -m "feat(workflow): clarify instance action panel"
```

---

### Task 5: Uplift `/admin/reference-data`

**Files:**
- Modify: `frontend/app/pages/admin/reference-data.vue`
- Modify: `frontend/app/tests/unit/pages/ReferenceDataPage.test.ts`

**Interfaces:**
- Consumes: existing `ReferenceTable` and `ReferenceValue`.
- Produces visible selected-table summary label: `الجدول المحدد`.
- Produces fourth metric label: `قيم الجدول المحدد`.

- [ ] **Step 1: Write failing reference-data tests**

Append to `frontend/app/tests/unit/pages/ReferenceDataPage.test.ts`:

```ts
it('shows a selected table summary after choosing a table', async () => {
  const wrapper = await mountPage(['VIEW', 'CREATE', 'UPDATE', 'DELETE'])
  mockGet.mockResolvedValueOnce({ data: [VALUE], meta: META })

  await wrapper.get('tbody tr').trigger('click')
  await flushPromises()

  expect(wrapper.text()).toContain('الجدول المحدد')
  expect(wrapper.text()).toContain('النشاط القطاعي')
  expect(wrapper.text()).toContain('sector_activity')
  expect(wrapper.text()).toContain('نظامي')
  expect(wrapper.text()).toContain('مستخدم')
})

it('shows the selected table value count metric', async () => {
  const wrapper = await mountPage(['VIEW', 'CREATE', 'UPDATE', 'DELETE'])
  mockGet.mockResolvedValueOnce({ data: [VALUE], meta: META })

  await wrapper.get('tbody tr').trigger('click')
  await flushPromises()

  expect(wrapper.text()).toContain('قيم الجدول المحدد')
})
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm test -- app/tests/unit/pages/ReferenceDataPage.test.ts
```

Expected: FAIL because selected summary and fourth metric do not exist.

- [ ] **Step 3: Implement summary and metric**

Add computed:

```ts
const selectedValueCount = computed(() => (selectedTable.value ? referenceValues.value.length : 0))
```

Change metric grid:

```vue
<MetricGrid :columns="4">
  <MetricCard label="إجمالي الجداول" :value="tableStats.total" :icon="Database" />
  <MetricCard label="نشط" :value="tableStats.active" :icon="Database" tone="success" />
  <MetricCard label="نظامي" :value="tableStats.system" :icon="Database" tone="info" />
  <MetricCard label="قيم الجدول المحدد" :value="selectedValueCount" :icon="Table2" tone="warning" />
</MetricGrid>
```

Add selected table summary above values table:

```vue
<div
  v-if="selectedTable"
  class="border-border bg-muted/30 mb-4 rounded-lg border p-3"
  aria-label="الجدول المحدد"
>
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <div class="text-muted-foreground text-xs font-medium">الجدول المحدد</div>
      <div class="font-section mt-1 text-sm font-semibold">{{ selectedTable.label }}</div>
      <div class="text-muted-foreground font-mono text-xs">{{ selectedTable.key }}</div>
    </div>
    <div class="flex flex-wrap gap-1">
      <Badge :variant="selectedTable.is_active ? 'secondary' : 'outline'">
        {{ selectedTable.is_active ? 'نشط' : 'موقوف' }}
      </Badge>
      <Badge v-if="selectedTable.is_system" variant="outline">نظامي</Badge>
      <Badge v-if="selectedTable.is_in_use" variant="outline">مستخدم</Badge>
    </div>
  </div>
</div>
```

Keep delete guards unchanged:

```ts
hidden: (row) => row.original.is_system || row.original.is_in_use
```

- [ ] **Step 4: Run test**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm test -- app/tests/unit/pages/ReferenceDataPage.test.ts
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app/pages/admin/reference-data.vue frontend/app/tests/unit/pages/ReferenceDataPage.test.ts
git commit -m "feat(admin): clarify reference data workspace"
```

---

### Task 6: Final Verification And Browser Review

**Files:**
- Modify only if verification exposes defects from Tasks 1-5.

**Interfaces:**
- Consumes all previous tasks.
- Produces verified local UI and test evidence.

- [ ] **Step 1: Run focused unit tests**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm test -- app/tests/unit/components/WorkflowCanvas.test.ts app/tests/unit/pages/WorkflowDesignerPage.test.ts app/tests/unit/pages/workflows-index.test.ts app/tests/unit/pages/workflows-instance-detail.test.ts app/tests/unit/pages/ReferenceDataPage.test.ts
```

Expected: PASS for all focused tests.

- [ ] **Step 2: Run typecheck**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm typecheck
```

Expected: PASS.

- [ ] **Step 3: Run lint**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm lint
```

Expected: PASS, including `lint:not-eligible-copy`.

- [ ] **Step 4: Start frontend dev server**

Run:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code/frontend
pnpm dev --host 127.0.0.1 --port 3000
```

Expected: Nuxt starts on `http://127.0.0.1:3000`.

- [ ] **Step 5: Browser verify desktop**

Use Playwright CLI against the running frontend:

```bash
playwright-cli snapshot http://127.0.0.1:3000/admin/workflows
playwright-cli snapshot http://127.0.0.1:3000/workflows
playwright-cli snapshot http://127.0.0.1:3000/admin/reference-data
```

Expected:

- `/admin/workflows` shows `تفصيلي` and `لوحة`.
- Canvas view shows workflow nodes/edges or a correct empty/loading/error state.
- Published/archived workflow versions are read-only.
- `/workflows` shows PageHeader, metrics, search, queue/all switch, and table rows or correct empty state.
- `/admin/reference-data` shows metric cards, table panel, values panel, and selected-table summary after selection.

- [ ] **Step 6: Browser verify mobile widths**

Use Playwright CLI with mobile viewport if available:

```bash
playwright-cli snapshot http://127.0.0.1:3000/admin/workflows --viewport 390x844
playwright-cli snapshot http://127.0.0.1:3000/workflows --viewport 390x844
playwright-cli snapshot http://127.0.0.1:3000/admin/reference-data --viewport 390x844
```

Expected:

- No horizontal text overlap.
- Toolbars wrap cleanly.
- Reference-data panes stack.
- Canvas remains reachable and normal view remains available as fallback.

- [ ] **Step 7: Fix verification defects only**

If any focused test, typecheck, lint, or browser review fails, make the smallest scoped fix. Do not add new features. Re-run the failing command until it passes.

- [ ] **Step 8: Final commit**

If Step 7 changed files:

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add frontend/app frontend/package.json frontend/pnpm-lock.yaml
git commit -m "fix(workflow): polish workflow admin ux"
```

If no files changed, skip this commit.

---

## Self-Review

- Spec coverage:
  - `/admin/workflows` normal/canvas workspace: Tasks 1 and 2.
  - Draft-only edit gate for canvas/live versions: Tasks 1 and 2.
  - `/workflows` operational table uplift: Task 3.
  - `/workflows/instances/[id]` hierarchy/action panel: Task 4.
  - `/admin/reference-data` master/detail clarity: Task 5.
  - Verification and browser review: Task 6.
- Placeholder scan:
  - No unfinished-marker language or stale old-project wording should remain.
- Dependency decision:
  - Plan prefers `@vue-flow/core` for the n8n-like canvas. The fallback keeps the same component interface if dependency installation fails.
- Type consistency:
  - `WorkflowCanvas` consumes `WorkflowVersion` and uses existing `WorkflowGraphNode`/`WorkflowGraphEdge`.
  - Page integration depends only on `WorkflowCanvas :version` and its emitted events.
