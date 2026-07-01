<script setup lang="ts">
import { computed, h, watch } from 'vue'
import {
  VueFlow,
  Panel,
  Handle,
  Position,
  MarkerType,
  type Edge,
  type Node,
  type NodeTypesObject,
  type EdgeMouseEvent,
  type NodeMouseEvent,
  type Connection,
  useVueFlow,
} from '@vue-flow/core'
import '@vue-flow/core/dist/style.css'
import { AlertCircle, Expand, GitBranch, Lock, Minus, Play, Plus, Square, ZoomIn, ZoomOut } from 'lucide-vue-next'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { useWorkflowGraph } from '@/composables/useWorkflowGraph'
import type { WorkflowGraphNode, WorkflowVersion } from '@/types/models'

const props = defineProps<{
  version: WorkflowVersion
}>()

const emit = defineEmits<{
  inspectNode: [stageId: number]
  inspectEdge: [transitionId: number]
  addTransition: []
  addStage: []
  connect: [fromStageId: number, toStageId: number]
}>()

const { graph, loading, error, fetchGraph } = useWorkflowGraph()
const { zoomIn: flowZoomIn, zoomOut: flowZoomOut, fitView } = useVueFlow()

const STAGE_W = 200
const STAGE_H = 80
const ACTION_SIZE = 48
const COL_GAP = 120
const ROW_GAP = 100
const COLS_PER_ROW = 4

const editable = computed(() => props.version.state === 'DRAFT' && props.version.is_editable)

function computeStagePositions(graphNodes: WorkflowGraphNode[]): Map<number, { x: number; y: number }> {
  const sorted = [...graphNodes].sort((a, b) => a.sort_order - b.sort_order)
  const positions = new Map<number, { x: number; y: number }>()
  sorted.forEach((node, i) => {
    const col = i % COLS_PER_ROW
    const row = Math.floor(i / COLS_PER_ROW)
    positions.set(node.id, {
      x: 60 + col * (STAGE_W + COL_GAP),
      y: 60 + row * (STAGE_H + ROW_GAP + ACTION_SIZE),
    })
  })
  return positions
}

const stagePositions = computed(() => computeStagePositions(graph.value?.nodes ?? []))

// ─── Custom Stage Node (rectangle) ──────────────────────────────────────────
const StageNode = {
  name: 'StageNode',
  props: ['data'],
  setup(nodeProps: { data: { label: string; code: string; isInitial: boolean; isFinal: boolean; stageId: number; editable: boolean } }) {
    return () => {
      const { label, code, isInitial, isFinal, stageId, editable: isEditable } = nodeProps.data

      const borderClass = isInitial
        ? 'border-[var(--severity-green)] shadow-[0_0_0_3px_color-mix(in_srgb,var(--severity-green)_15%,transparent)]'
        : isFinal
          ? 'border-[var(--severity-amber)] shadow-[0_0_0_3px_color-mix(in_srgb,var(--severity-amber)_15%,transparent)]'
          : 'border-border'

      return h('div', { class: 'relative' }, [
        // Left handle
        h(Handle, {
          id: `${stageId}-target`,
          type: 'target',
          position: Position.Left,
          style: { background: 'var(--primary)', width: '10px', height: '10px', border: '2px solid var(--background)' },
          connectable: isEditable,
        }),

        // Node card
        h(
          'button',
          {
            type: 'button',
            class: [
              'flex flex-col gap-1.5 rounded-lg border-2 bg-card p-3 text-start',
              'transition-all duration-150 hover:shadow-md',
              'focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
              borderClass,
            ],
            style: { width: `${STAGE_W}px`, minHeight: `${STAGE_H}px` },
            'data-testid': `workflow-canvas-node-${stageId}`,
            onClick: () => emit('inspectNode', stageId),
          },
          [
            h('div', { class: 'flex items-center gap-2' }, [
              h('div', {
                class: [
                  'flex h-7 w-7 shrink-0 items-center justify-center rounded-md',
                  isInitial ? 'bg-[var(--severity-green)]/15' : isFinal ? 'bg-[var(--severity-amber)]/15' : 'bg-muted',
                ],
              }, [
                h(isInitial ? Play : isFinal ? Square : GitBranch, {
                  class: [
                    'h-3.5 w-3.5',
                    isInitial ? 'text-[var(--severity-green)]' : isFinal ? 'text-[var(--severity-amber)]' : isEditable ? 'text-primary' : 'text-muted-foreground',
                  ],
                }),
              ]),
              h('div', { class: 'min-w-0 flex-1' }, [
                h('div', { class: 'truncate text-xs font-semibold text-foreground leading-tight' }, label),
                h('div', { class: 'font-mono text-[10px] text-muted-foreground mt-0.5' }, code),
              ]),
            ]),
            (isInitial || isFinal) && h('div', { class: 'flex gap-1' }, [
              isInitial && h('span', {
                class: 'rounded-full px-1.5 py-px text-[9px] font-medium bg-[var(--severity-green)]/10 text-[var(--severity-green)] border border-[var(--severity-green)]/25',
              }, 'بداية'),
              isFinal && h('span', {
                class: 'rounded-full px-1.5 py-px text-[9px] font-medium bg-[var(--severity-amber)]/10 text-[var(--severity-amber)] border border-[var(--severity-amber)]/25',
              }, 'نهاية'),
            ]),
          ],
        ),

        // Right handle
        h(Handle, {
          id: `${stageId}-source`,
          type: 'source',
          position: Position.Right,
          style: { background: 'var(--primary)', width: '10px', height: '10px', border: '2px solid var(--background)' },
          connectable: isEditable,
        }),
      ])
    }
  },
}

// ─── Custom Action Node (circle) ─────────────────────────────────────────────
const ActionNode = {
  name: 'ActionNode',
  props: ['data'],
  setup(nodeProps: { data: { label: string; transitionId: number; isReturn: boolean; editable: boolean } }) {
    return () => {
      const { label, transitionId, isReturn, editable: isEditable } = nodeProps.data

      return h('div', { class: 'relative flex flex-col items-center' }, [
        h(Handle, {
          type: 'target',
          position: Position.Left,
          style: { opacity: 0, pointerEvents: 'none' },
          connectable: false,
        }),

        h(
          'button',
          {
            type: 'button',
            class: [
              'flex items-center justify-center rounded-full border-2 bg-card shadow-sm',
              'transition-all duration-150 hover:shadow-md',
              'focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
              isReturn
                ? 'border-[var(--severity-amber)] text-[var(--severity-amber)]'
                : 'border-primary text-primary',
              isEditable ? 'hover:scale-110' : '',
            ],
            style: { width: `${ACTION_SIZE}px`, height: `${ACTION_SIZE}px` },
            'data-testid': `workflow-canvas-edge-${transitionId}`,
            onClick: () => emit('inspectEdge', transitionId),
          },
          [
            h('span', {
              class: 'font-mono text-[9px] font-semibold leading-tight text-center px-1 max-w-full truncate',
            }, label),
          ],
        ),

        h(Handle, {
          type: 'source',
          position: Position.Right,
          style: { opacity: 0, pointerEvents: 'none' },
          connectable: false,
        }),
      ])
    }
  },
}

const nodeTypes: NodeTypesObject = {
  stage: StageNode as any,
  action: ActionNode as any,
}

// Build nodes: stage nodes + action nodes (one per edge, positioned midway)
const nodes = computed<Node[]>(() => {
  const stageNodes: Node[] = (graph.value?.nodes ?? []).map((node) => ({
    id: `stage-${node.id}`,
    type: 'stage',
    position: stagePositions.value.get(node.id) ?? { x: 0, y: 0 },
    data: {
      label: node.display_label || node.name,
      code: node.code,
      isInitial: node.is_initial,
      isFinal: node.is_final,
      stageId: node.id,
      editable: editable.value,
    },
    draggable: editable.value,
    selectable: true,
    connectable: editable.value,
  }))

  const actionNodes: Node[] = (graph.value?.edges ?? []).map((edge) => {
    const fromPos = stagePositions.value.get(edge.from_stage_id) ?? { x: 0, y: 0 }
    const toPos = stagePositions.value.get(edge.to_stage_id) ?? { x: 0, y: 0 }
    const midX = (fromPos.x + STAGE_W + toPos.x) / 2 - ACTION_SIZE / 2
    const midY = (fromPos.y + STAGE_H / 2 + toPos.y + STAGE_H / 2) / 2 - ACTION_SIZE / 2

    const label = edge.action_name || edge.action_code || `#${edge.action_id}`

    return {
      id: `action-${edge.id}`,
      type: 'action',
      position: { x: midX, y: midY },
      data: {
        label,
        transitionId: edge.id,
        isReturn: edge.is_return,
        editable: editable.value,
      },
      draggable: false,
      selectable: true,
      connectable: false,
    }
  })

  return [...stageNodes, ...actionNodes]
})

// Edges: stage→action and action→stage (via action intermediate nodes)
const edges = computed<Edge[]>(() => {
  const result: Edge[] = []
  const edgeColor = (isReturn: boolean) => isReturn ? 'var(--severity-amber)' : 'var(--primary)'

  for (const edge of graph.value?.edges ?? []) {
    const color = edgeColor(edge.is_return)
    const baseStyle = {
      stroke: color,
      strokeWidth: 1.5,
      strokeDasharray: edge.is_return ? '5 4' : undefined,
    }
    const marker = { type: MarkerType.ArrowClosed, color, width: 14, height: 14 }

    // stage → action
    result.push({
      id: `e-${edge.id}-in`,
      source: `stage-${edge.from_stage_id}`,
      sourceHandle: `${edge.from_stage_id}-source`,
      target: `action-${edge.id}`,
      type: 'bezier',
      animated: edge.is_return,
      style: baseStyle,
      markerEnd: marker,
      selectable: false,
    })

    // action → stage
    result.push({
      id: `e-${edge.id}-out`,
      source: `action-${edge.id}`,
      target: `stage-${edge.to_stage_id}`,
      targetHandle: `${edge.to_stage_id}-target`,
      type: 'bezier',
      animated: edge.is_return,
      style: baseStyle,
      markerEnd: marker,
      selectable: false,
    })
  }

  return result
})

function load(versionId: number) {
  void fetchGraph(versionId)
}

watch(() => props.version.id, (id) => load(id), { immediate: true })

function handleConnect(conn: Connection) {
  // Extract numeric IDs from 'stage-N' format
  const from = Number(conn.source?.replace('stage-', ''))
  const to = Number(conn.target?.replace('stage-', ''))
  if (from && to) emit('connect', from, to)
}

function handleNodeClick({ node }: NodeMouseEvent) {
  if (node.type === 'stage') emit('inspectNode', node.data.stageId as number)
  if (node.type === 'action') emit('inspectEdge', node.data.transitionId as number)
}

function handleZoomIn() { void flowZoomIn({ duration: 200 }) }
function handleZoomOut() { void flowZoomOut({ duration: 200 }) }
function handleFitView() { void fitView({ duration: 300, padding: 0.12 }) }
</script>

<template>
  <section class="space-y-3" aria-label="لوحة مسار العمل">
    <!-- Header row -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="font-section text-sm font-semibold">لوحة مسار العمل</h3>
        <p class="text-muted-foreground mt-1 text-xs">
          عرض مرئي للمراحل والانتقالات.
          <span v-if="editable">السحب متاح للمراحل. اسحب من المقبض لإنشاء انتقال.</span>
          <span v-else>التعديل متاح فقط للنسخ المسودة.</span>
        </p>
      </div>

      <div class="flex items-center gap-2">
        <Badge v-if="editable" variant="secondary" class="gap-1">
          <span class="h-1.5 w-1.5 rounded-full bg-[var(--severity-green)]" />
          قابلة للتعديل
        </Badge>
        <Badge v-else data-testid="workflow-canvas-readonly" variant="outline" class="gap-1">
          <Lock class="h-3 w-3" />
          للعرض فقط
        </Badge>
        <Button v-if="editable" size="sm" variant="outline" @click="emit('addStage')">
          <Plus class="h-3.5 w-3.5" />
          مرحلة
        </Button>
        <Button v-if="editable" size="sm" @click="emit('addTransition')">
          <Plus class="h-3.5 w-3.5" />
          انتقال
        </Button>
      </div>
    </div>

    <!-- Error -->
    <Alert v-if="error" variant="destructive" role="alert">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>تعذّر تحميل لوحة مسار العمل</AlertTitle>
      <AlertDescription>{{ error }}</AlertDescription>
    </Alert>

    <!-- Loading -->
    <Skeleton v-else-if="loading" class="h-[520px] w-full rounded-xl" />

    <!-- Empty -->
    <Empty v-else-if="!graph || graph.nodes.length === 0">
      <EmptyHeader>
        <EmptyTitle>لا توجد مراحل لعرضها</EmptyTitle>
        <EmptyDescription>أضف مراحل وانتقالات في العرض التفصيلي.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <!-- Canvas -->
    <div
      v-else
      data-testid="workflow-canvas"
      class="border-border relative overflow-hidden rounded-xl border canvas-surface"
      style="height: 520px;"
    >
      <VueFlow
        class="h-full w-full"
        :nodes="nodes"
        :edges="edges"
        :node-types="nodeTypes"
        :nodes-draggable="editable"
        :edges-updatable="false"
        :connectable="editable"
        :zoom-on-scroll="true"
        :pan-on-drag="[2]"
        :fit-view-on-init="true"
        :fit-view-on-init-options="{ padding: 0.12 }"
        :min-zoom="0.25"
        :max-zoom="2.5"
        :default-edge-options="{ type: 'bezier' }"
        dir="ltr"
        @connect="handleConnect"
        @node-click="handleNodeClick"
      >
        <!-- Theme-aware dotted background -->
        <template #background>
          <svg
            width="100%"
            height="100%"
            xmlns="http://www.w3.org/2000/svg"
            style="position:absolute;inset:0;pointer-events:none"
          >
            <defs>
              <pattern id="canvas-dots" x="0" y="0" width="24" height="24" patternUnits="userSpaceOnUse">
                <circle cx="1.5" cy="1.5" r="1.5" class="canvas-dot" />
              </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#canvas-dots)" />
          </svg>
        </template>

        <!-- Controls panel (bottom-left) -->
        <Panel position="bottom-left" class="flex flex-col gap-1 p-2">
          <button
            type="button"
            class="canvas-ctrl-btn"
            aria-label="تكبير"
            @click="handleZoomIn"
          >
            <ZoomIn class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="canvas-ctrl-btn"
            aria-label="تصغير"
            @click="handleZoomOut"
          >
            <ZoomOut class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="canvas-ctrl-btn"
            aria-label="ملاءمة الشاشة"
            @click="handleFitView"
          >
            <Expand class="h-4 w-4" />
          </button>
        </Panel>

        <!-- Add stage shortcut (bottom-right, draft only) -->
        <Panel v-if="editable" position="bottom-right" class="p-2">
          <button
            type="button"
            class="canvas-ctrl-btn gap-1.5 px-3"
            style="width:auto"
            @click="emit('addStage')"
          >
            <Plus class="h-3.5 w-3.5" />
            <span class="text-xs font-medium">مرحلة جديدة</span>
          </button>
        </Panel>
      </VueFlow>
    </div>
  </section>
</template>

<style scoped>
/* Canvas surface — theme-aware background */
.canvas-surface {
  background-color: var(--canvas-bg, oklch(0.97 0 0));
}

:global(.dark) .canvas-surface {
  background-color: oklch(0.16 0.005 265);
}

/* Dot color adapts to theme */
.canvas-dot {
  fill: oklch(0.82 0 0);
}

:global(.dark) .canvas-dot {
  fill: oklch(0.28 0.005 265);
}

/* Zoom/fit control buttons */
.canvas-ctrl-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 32px;
  width: 32px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--muted-foreground);
  box-shadow: 0 1px 3px oklch(0 0 0 / 0.1);
  transition: background 0.15s, color 0.15s;
  cursor: pointer;
}

.canvas-ctrl-btn:hover {
  background: var(--muted);
  color: var(--foreground);
}

/* Override VueFlow defaults */
:deep(.vue-flow__edge-path) {
  stroke-linecap: round;
}

:deep(.vue-flow__handle) {
  cursor: crosshair;
}

:deep(.vue-flow__handle-connecting) {
  background: var(--primary) !important;
}

:deep(.vue-flow__handle-valid) {
  background: var(--severity-green) !important;
}

:deep(.vue-flow__node.selected > div) {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 30%, transparent);
}

/* Hide VueFlow's built-in attribution */
:deep(.vue-flow__attribution) {
  display: none;
}
</style>
