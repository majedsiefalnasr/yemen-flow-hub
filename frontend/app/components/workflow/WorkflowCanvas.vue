<script setup lang="ts">
import { computed, watch } from 'vue'
import { VueFlow, type Edge, type Node } from '@vue-flow/core'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import { AlertCircle, GitBranch, Lock, Plus } from 'lucide-vue-next'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { useWorkflowGraph } from '@/composables/useWorkflowGraph'
import type { WorkflowGraphEdge, WorkflowGraphNode, WorkflowVersion } from '@/types/models'

const props = defineProps<{
  version: WorkflowVersion
}>()

const emit = defineEmits<{
  inspectNode: [stageId: number]
  inspectEdge: [transitionId: number]
  addTransition: []
}>()

const { graph, loading, error, fetchGraph } = useWorkflowGraph()

const NODE_WIDTH = 176
const NODE_HEIGHT = 96

const editable = computed(() => props.version.state === 'DRAFT' && props.version.is_editable)
const canRenderVueFlow = computed(() => {
  return typeof SVGElement !== 'undefined' && typeof SVGElement.prototype.getBBox === 'function'
})

function nodePosition(node: WorkflowGraphNode) {
  const column = Math.max(node.sort_order, 0)
  return {
    x: 48 + column * 240,
    y: node.is_final ? 280 : 72 + (column % 2) * 160,
  }
}

function nodeLabel(node: WorkflowGraphNode) {
  return node.display_label || node.name
}

function edgeLabel(edge: WorkflowGraphEdge) {
  const label = edge.action_name || edge.action_code || `#${edge.action_id}`
  return edge.requires_comment ? `${label} · تعليق مطلوب` : label
}

const nodes = computed<Node[]>(() =>
  (graph.value?.nodes ?? []).map((node) => ({
    id: String(node.id),
    position: nodePosition(node),
    data: {
      label: nodeLabel(node),
      code: node.code,
      isInitial: node.is_initial,
      isFinal: node.is_final,
    },
    draggable: editable.value,
    selectable: true,
    connectable: false,
  })),
)

const edges = computed<Edge[]>(() =>
  (graph.value?.edges ?? []).map((edge) => ({
    id: String(edge.id),
    source: String(edge.from_stage_id),
    target: String(edge.to_stage_id),
    label: edgeLabel(edge),
    animated: edge.is_return,
    selectable: true,
    updatable: false,
  })),
)

const nodePositions = computed(() => {
  return new Map(
    (graph.value?.nodes ?? []).map((node) => [
      node.id,
      {
        ...nodePosition(node),
        node,
      },
    ]),
  )
})

const edgeBadges = computed(() =>
  (graph.value?.edges ?? []).map((edge) => {
    const source = nodePositions.value.get(edge.from_stage_id)
    const target = nodePositions.value.get(edge.to_stage_id)

    const sourceCenter = source
      ? { x: source.x + NODE_WIDTH / 2, y: source.y + NODE_HEIGHT / 2 }
      : { x: 0, y: 0 }
    const targetCenter = target
      ? { x: target.x + NODE_WIDTH / 2, y: target.y + NODE_HEIGHT / 2 }
      : { x: 0, y: 0 }

    return {
      edge,
      label: edgeLabel(edge),
      left: (sourceCenter.x + targetCenter.x) / 2,
      top: (sourceCenter.y + targetCenter.y) / 2 - 18,
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

    <div
      v-else
      data-testid="workflow-canvas"
      class="border-border bg-background relative h-[620px] overflow-auto rounded-lg border"
    >
      <VueFlow
        v-if="canRenderVueFlow"
        class="h-full min-w-[720px]"
        :nodes="nodes"
        :edges="edges"
        :nodes-draggable="editable"
        :edges-updatable="false"
        :connectable="false"
        :zoom-on-scroll="false"
        :pan-on-drag="true"
        :fit-view-on-init="true"
        dir="rtl"
      />

      <div class="pointer-events-none absolute inset-0 min-w-[720px]">
        <button
          v-for="entry in graph.nodes"
          :key="entry.id"
          type="button"
          class="border-border bg-card pointer-events-auto absolute w-44 rounded-md border p-3 text-start shadow-sm"
          :style="{
            left: `${nodePositions.get(entry.id)?.x ?? 0}px`,
            top: `${nodePositions.get(entry.id)?.y ?? 0}px`,
          }"
          :data-testid="`workflow-canvas-node-${entry.id}`"
          @click="emit('inspectNode', entry.id)"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="text-sm font-semibold">{{ nodeLabel(entry) }}</div>
              <div class="text-muted-foreground font-mono text-xs">{{ entry.code }}</div>
            </div>
            <GitBranch class="text-muted-foreground h-4 w-4 shrink-0" />
          </div>
          <div class="mt-2 flex flex-wrap gap-1">
            <Badge v-if="entry.is_initial" variant="secondary">بداية</Badge>
            <Badge v-if="entry.is_final" variant="secondary">نهاية</Badge>
          </div>
        </button>

        <button
          v-for="entry in edgeBadges"
          :key="entry.edge.id"
          type="button"
          class="bg-background/95 border-border text-foreground pointer-events-auto absolute -translate-x-1/2 rounded-full border px-2.5 py-1 text-xs shadow-sm"
          :style="{ left: `${entry.left}px`, top: `${entry.top}px` }"
          :data-testid="`workflow-canvas-edge-${entry.edge.id}`"
          @click="emit('inspectEdge', entry.edge.id)"
        >
          {{ entry.label }}
        </button>
      </div>
    </div>
  </section>
</template>
