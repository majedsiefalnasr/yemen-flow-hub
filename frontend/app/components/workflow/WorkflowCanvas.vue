<script setup lang="ts">
import { computed, h, watch } from 'vue'
import { VueFlow, Panel, MarkerType, type Edge, type Node, type NodeTypesObject, type EdgeMouseEvent } from '@vue-flow/core'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import { AlertCircle, GitBranch, Lock, Minus, Plus, ZoomIn, ZoomOut } from 'lucide-vue-next'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { useWorkflowGraph } from '@/composables/useWorkflowGraph'
import type { WorkflowGraphNode, WorkflowVersion } from '@/types/models'
import { useVueFlow } from '@vue-flow/core'

const props = defineProps<{
  version: WorkflowVersion
}>()

const emit = defineEmits<{
  inspectNode: [stageId: number]
  inspectEdge: [transitionId: number]
  addTransition: []
}>()

const { graph, loading, error, fetchGraph } = useWorkflowGraph()
const { zoomIn: flowZoomIn, zoomOut: flowZoomOut, fitView } = useVueFlow()

const NODE_WIDTH = 200
const NODE_HEIGHT = 88
const COL_GAP = 100
const ROW_GAP = 80
const COLS_PER_ROW = 4

const editable = computed(() => props.version.state === 'DRAFT' && props.version.is_editable)

/**
 * Grid layout: sort nodes by sort_order, then wrap into rows of COLS_PER_ROW.
 * This keeps the canvas reasonably compact regardless of how many stages exist.
 */
function computePositions(graphNodes: WorkflowGraphNode[]): Map<number, { x: number; y: number }> {
  const sorted = [...graphNodes].sort((a, b) => a.sort_order - b.sort_order)
  const positions = new Map<number, { x: number; y: number }>()
  sorted.forEach((node, i) => {
    const col = i % COLS_PER_ROW
    const row = Math.floor(i / COLS_PER_ROW)
    positions.set(node.id, {
      x: 40 + col * (NODE_WIDTH + COL_GAP),
      y: 40 + row * (NODE_HEIGHT + ROW_GAP),
    })
  })
  return positions
}

const positions = computed(() => computePositions(graph.value?.nodes ?? []))

// Custom node component rendered inline via NodeTypes
const StageNode = {
  name: 'StageNode',
  props: ['data', 'id'],
  emits: ['inspect'],
  setup(nodeProps: { data: { label: string; code: string; isInitial: boolean; isFinal: boolean; stageId: number; editable: boolean }; id: string }) {
    return () => {
      const { label, code, isInitial, isFinal, stageId, editable: isEditable } = nodeProps.data
      return h(
        'button',
        {
          type: 'button',
          class: [
            'group relative flex flex-col gap-2 rounded-xl border bg-card p-4 text-start shadow-md',
            'transition-all duration-150',
            'hover:shadow-lg hover:border-primary/40',
            'focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            isInitial ? 'border-[var(--severity-green)]/50 ring-1 ring-[var(--severity-green)]/20' : 'border-border',
            isFinal ? 'border-[var(--severity-amber)]/50 ring-1 ring-[var(--severity-amber)]/20' : '',
          ],
          style: { width: `${NODE_WIDTH}px`, minHeight: `${NODE_HEIGHT}px` },
          'data-testid': `workflow-canvas-node-${stageId}`,
          onClick: () => emit('inspectNode', stageId),
        },
        [
          h('div', { class: 'flex items-start justify-between gap-2' }, [
            h('div', { class: 'flex-1 min-w-0' }, [
              h('div', { class: 'truncate text-sm font-semibold text-foreground leading-snug' }, label),
              h('div', { class: 'font-mono text-[10px] text-muted-foreground mt-0.5 truncate' }, code),
            ]),
            h(GitBranch, {
              class: [
                'h-3.5 w-3.5 shrink-0 mt-0.5',
                isEditable ? 'text-primary' : 'text-muted-foreground',
              ],
            }),
          ]),
          (isInitial || isFinal) && h('div', { class: 'flex gap-1 flex-wrap' }, [
            isInitial && h(
              'span',
              { class: 'inline-flex items-center rounded-full border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 px-1.5 py-0.5 text-[10px] font-medium text-[var(--severity-green)]' },
              'بداية',
            ),
            isFinal && h(
              'span',
              { class: 'inline-flex items-center rounded-full border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 px-1.5 py-0.5 text-[10px] font-medium text-[var(--severity-amber)]' },
              'نهاية',
            ),
          ]),
        ],
      )
    }
  },
}

const nodeTypes: NodeTypesObject = { stage: StageNode as any }

const nodes = computed<Node[]>(() =>
  (graph.value?.nodes ?? []).map((node) => ({
    id: String(node.id),
    type: 'stage',
    position: positions.value.get(node.id) ?? { x: 0, y: 0 },
    data: {
      label: node.display_label || node.name,
      code: node.code,
      isInitial: node.is_initial,
      isFinal: node.is_final,
      stageId: node.id,
      editable: editable.value,
    },
    draggable: false,
    selectable: false,
    connectable: false,
  })),
)

const edges = computed<Edge[]>(() =>
  (graph.value?.edges ?? []).map((edge) => {
    const rawLabel = edge.action_name || edge.action_code || `#${edge.action_id}`
    const label = edge.requires_comment ? `${rawLabel} · تعليق` : rawLabel
    return {
      id: String(edge.id),
      source: String(edge.from_stage_id),
      target: String(edge.to_stage_id),
      type: 'smoothstep',
      label,
      animated: edge.is_return,
      labelStyle: { fontSize: '11px', fontFamily: 'IBM Plex Sans Arabic, sans-serif', fill: '#6c757d' },
      labelBgStyle: { fill: '#ffffff', stroke: '#cccccc', strokeWidth: 1 },
      labelBgPadding: [6, 3] as [number, number],
      labelBgBorderRadius: 9999,
      style: {
        stroke: edge.is_return ? 'var(--severity-amber)' : '#0066cc',
        strokeWidth: 1.5,
      },
      markerEnd: {
        type: MarkerType.ArrowClosed,
        color: edge.is_return ? 'var(--severity-amber)' : '#0066cc',
      },
      data: { transitionId: edge.id },
      selectable: true,
    }
  }),
)

function load(versionId: number) {
  void fetchGraph(versionId)
}

watch(
  () => props.version.id,
  (versionId) => {
    load(versionId)
  },
  { immediate: true },
)

function handleEdgeClick({ edge }: EdgeMouseEvent) {
  if (edge.data?.transitionId) {
    emit('inspectEdge', edge.data.transitionId as number)
  }
}

function handleZoomIn() {
  void flowZoomIn({ duration: 200 })
}
function handleZoomOut() {
  void flowZoomOut({ duration: 200 })
}
function handleFitView() {
  void fitView({ duration: 300, padding: 0.15 })
}
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
      <Skeleton class="h-[520px] w-full rounded-xl" />
    </div>

    <Empty v-else-if="!graph || graph.nodes.length === 0">
      <EmptyHeader>
        <EmptyTitle>لا توجد مراحل لعرضها</EmptyTitle>
        <EmptyDescription>أضف مراحل وانتقالات في العرض التفصيلي.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div
      v-else
      data-testid="workflow-canvas"
      class="border-border bg-[#f8f9fb] relative h-[480px] overflow-hidden rounded-xl border"
    >
      <VueFlow
        class="h-full w-full"
        :nodes="nodes"
        :edges="edges"
        :node-types="nodeTypes"
        :nodes-draggable="false"
        :edges-updatable="false"
        :connectable="false"
        :zoom-on-scroll="true"
        :pan-on-drag="true"
        :fit-view-on-init="true"
        :fit-view-on-init-options="{ padding: 0.08 }"
        :min-zoom="0.3"
        :max-zoom="2"
        dir="ltr"
        @edge-click="handleEdgeClick"
      >
        <!-- Grid dot background pattern -->
        <template #background>
          <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position:absolute;inset:0;pointer-events:none">
            <defs>
              <pattern id="canvas-dot" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                <circle cx="1" cy="1" r="1" fill="#d1d5db" />
              </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#canvas-dot)" />
          </svg>
        </template>

        <!-- Zoom controls panel -->
        <Panel position="bottom-left" class="flex flex-col gap-1 p-1">
          <button
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-card shadow-sm hover:bg-muted transition-colors"
            aria-label="تكبير"
            @click="handleZoomIn"
          >
            <ZoomIn class="h-4 w-4 text-muted-foreground" />
          </button>
          <button
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-card shadow-sm hover:bg-muted transition-colors"
            aria-label="تصغير"
            @click="handleZoomOut"
          >
            <ZoomOut class="h-4 w-4 text-muted-foreground" />
          </button>
          <button
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-card shadow-sm hover:bg-muted transition-colors"
            aria-label="ملاءمة الشاشة"
            @click="handleFitView"
          >
            <Minus class="h-4 w-4 text-muted-foreground" />
          </button>
        </Panel>
      </VueFlow>
    </div>
  </section>
</template>
