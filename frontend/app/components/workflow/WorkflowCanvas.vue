<script setup lang="ts">
import { computed, h, nextTick, ref, watch } from 'vue'
import {
  VueFlow,
  Panel,
  Handle,
  Position,
  MarkerType,
  type Edge,
  type Node,
  type NodeTypesObject,
  type Connection,
  type NodeMouseEvent,
  type EdgeMouseEvent,
  type EdgeUpdateEvent,
  type GraphEdge,
  useVueFlow,
} from '@vue-flow/core'
import { Background, BackgroundVariant } from '@vue-flow/background'
import '@vue-flow/core/dist/style.css'
import {
  AlertCircle,
  Expand,
  GitBranch,
  LayoutGrid,
  Lock,
  Play,
  Plus,
  Square,
  ZoomIn,
  ZoomOut,
} from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useWorkflowGraph } from '@/composables/useWorkflowGraph'
import { useWorkflowStages, type WorkflowStagePayload } from '@/composables/useWorkflowStages'
import { useWorkflowTransitions } from '@/composables/useWorkflowTransitions'
import { useWorkflowActions } from '@/composables/useWorkflowActions'
import type { WorkflowGraphNode, WorkflowStage, WorkflowVersion } from '@/types/models'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { z } from 'zod'

const props = defineProps<{ version: WorkflowVersion }>()

const { graph, loading, error, fetchGraph } = useWorkflowGraph()
const { stages, fetchStages, createStage, updateStage } = useWorkflowStages()
const { createTransition, updateTransition, deleteTransition, transitions, fetchTransitions } =
  useWorkflowTransitions()
const { actions, fetchActions } = useWorkflowActions()

const { zoomIn: flowZoomIn, zoomOut: flowZoomOut, fitView } = useVueFlow()

// ── Layout constants ──────────────────────────────────────────────────────────
const STAGE_W = 220
const STAGE_H = 88
// Gap between nodes horizontally (within a row)
const H_GAP = 160
// Gap between rows (depth levels)
const V_GAP = 120

const editable = computed(() => props.version.state === 'DRAFT' && props.version.is_editable)

// ── Node position tracking (persists user drag) ───────────────────────────────
const nodePositions = ref(new Map<string, { x: number; y: number }>())

// ── Manual edge handle overrides (persists drag-reconnect until next graph load)
const edgeHandleOverrides = ref(new Map<string, { sourceHandle: string; targetHandle: string }>())

function computeAutoPositions(
  graphNodes: WorkflowGraphNode[],
  graphEdges: { from_stage_id: number; to_stage_id: number; is_return?: boolean }[],
): Map<number, { x: number; y: number }> {
  const SLOT_W = STAGE_W + H_GAP
  const SLOT_H = STAGE_H + V_GAP
  const PAD_X = 40
  const PAD_Y = 40

  // Build adjacency for BFS. Only forward edges drive the depth/topology —
  // return/reject edges flow backwards and would corrupt the longest-path depth
  // (collapsing a linear flow into one row), so they are excluded here.
  const outEdges = new Map<number, number[]>()
  const inDegree = new Map<number, number>()
  for (const n of graphNodes) {
    outEdges.set(n.id, [])
    inDegree.set(n.id, 0)
  }
  for (const e of graphEdges) {
    if (e.from_stage_id === e.to_stage_id) continue // skip self-loops
    if (e.is_return) continue // skip backward/return edges
    outEdges.get(e.from_stage_id)?.push(e.to_stage_id)
    inDegree.set(e.to_stage_id, (inDegree.get(e.to_stage_id) ?? 0) + 1)
  }

  // Assign depth via longest-path BFS (Kahn's topological sort with max-depth tracking)
  const depth = new Map<number, number>()
  const queue: number[] = []
  const inDegCopy = new Map(inDegree)
  for (const n of graphNodes) {
    depth.set(n.id, 0)
    if ((inDegCopy.get(n.id) ?? 0) === 0) queue.push(n.id)
  }
  // BFS order respects sort_order for tie-breaking
  queue.sort((a, b) => {
    const na = graphNodes.find((n) => n.id === a)!
    const nb = graphNodes.find((n) => n.id === b)!
    return na.sort_order - nb.sort_order
  })
  while (queue.length) {
    const cur = queue.shift()!
    for (const next of outEdges.get(cur) ?? []) {
      const nd = (depth.get(cur) ?? 0) + 1
      if (nd > (depth.get(next) ?? 0)) depth.set(next, nd)
      const remaining = (inDegCopy.get(next) ?? 1) - 1
      inDegCopy.set(next, remaining)
      if (remaining === 0) queue.push(next)
    }
  }
  // Nodes not reached (in a cycle) fall back to sort_order-based depth
  for (const n of graphNodes) {
    if (!depth.has(n.id)) depth.set(n.id, n.sort_order)
  }

  // Group nodes by depth (= row)
  const rows = new Map<number, number[]>()
  for (const n of graphNodes) {
    const d = depth.get(n.id)!
    const row = rows.get(d) ?? []
    row.push(n.id)
    rows.set(d, row)
  }

  // Sort each row by sort_order for consistent column positions
  for (const [, ids] of rows) {
    ids.sort((a, b) => {
      const na = graphNodes.find((n) => n.id === a)!
      const nb = graphNodes.find((n) => n.id === b)!
      return na.sort_order - nb.sort_order
    })
  }

  // Determine canvas width based on max row width
  const maxRowSize = Math.max(...[...rows.values()].map((r) => r.length))
  const totalW = maxRowSize * SLOT_W

  // Position each node: centred within its row
  const map = new Map<number, { x: number; y: number }>()
  for (const [d, ids] of rows) {
    const rowW = ids.length * SLOT_W
    const startX = PAD_X + (totalW - rowW) / 2
    ids.forEach((id, i) => {
      map.set(id, { x: startX + i * SLOT_W, y: PAD_Y + d * SLOT_H })
    })
  }
  return map
}

const autoPositions = computed(() =>
  computeAutoPositions(graph.value?.nodes ?? [], graph.value?.edges ?? []),
)

// ── Parallel edge helpers ─────────────────────────────────────────────────────
// Multiple transitions between the same (from, to) pair get spread handle positions
// so their bezier curves and labels separate visually.
// Returns X% position along the node's top/bottom edge for a given slot.
function parallelOffsetPct(slot: number, total: number): number {
  if (total === 1) return 50
  const spread = Math.min(64, total * 20)
  return 50 - spread / 2 + slot * (spread / (total - 1))
}

type HandleDesc = { id: string; offsetPct: number }

// Precompute per-node handle descriptors from the edge list so StageNode can
// render exactly one VueFlow Handle per incoming/outgoing transition at the
// right X offset.
const nodeHandles = computed(() => {
  const raw = graph.value?.edges ?? []
  const pairCount = new Map<string, number>()
  for (const e of raw) {
    const k = `${e.from_stage_id}->${e.to_stage_id}`
    pairCount.set(k, (pairCount.get(k) ?? 0) + 1)
  }
  const pairSlot = new Map<string, number>()
  const srcHandles = new Map<number, HandleDesc[]>()
  const tgtHandles = new Map<number, HandleDesc[]>()
  for (const e of raw) {
    const k = `${e.from_stage_id}->${e.to_stage_id}`
    const slot = pairSlot.get(k) ?? 0
    pairSlot.set(k, slot + 1)
    const pct = parallelOffsetPct(slot, pairCount.get(k)!)
    const src = srcHandles.get(e.from_stage_id) ?? []
    src.push({ id: `${e.from_stage_id}-b-${e.id}`, offsetPct: pct })
    srcHandles.set(e.from_stage_id, src)
    const tgt = tgtHandles.get(e.to_stage_id) ?? []
    tgt.push({ id: `${e.to_stage_id}-t-${e.id}`, offsetPct: pct })
    tgtHandles.set(e.to_stage_id, tgt)
  }
  return { srcHandles, tgtHandles }
})

// ── Custom StageNode ──────────────────────────────────────────────────────────
// Handle layout by node role (top-to-bottom canvas, direction-neutral):
//   initial → per-edge bottom source handles only
//   final   → per-edge top target handles only
//   default → per-edge top target + bottom source handles
const StageNode = {
  name: 'StageNode',
  props: ['data'],
  setup(p: {
    data: {
      label: string
      code: string
      isInitial: boolean
      isFinal: boolean
      stageId: number
      editable: boolean
      srcHandles: HandleDesc[]
      tgtHandles: HandleDesc[]
    }
  }) {
    return () => {
      const {
        label,
        code,
        isInitial,
        isFinal,
        stageId,
        editable: isEditable,
        srcHandles,
        tgtHandles,
      } = p.data
      const hcBase = isEditable ? 'fh' : 'fh fh-ro'
      const nodeCls = ['sn', isInitial ? 'node-initial' : isFinal ? 'node-final' : '']
      const icoCls = isInitial
        ? 'sn-icon sn-icon--start'
        : isFinal
          ? 'sn-icon sn-icon--end'
          : 'sn-icon'
      const IcoComponent = isInitial ? Play : isFinal ? Square : GitBranch

      // Fall back to single centred handle when node has no edges yet
      const tHandles: HandleDesc[] = tgtHandles?.length
        ? tgtHandles
        : [{ id: `${stageId}-t`, offsetPct: 50 }]
      const bHandles: HandleDesc[] = srcHandles?.length
        ? srcHandles
        : [{ id: `${stageId}-b`, offsetPct: 50 }]

      return h('div', { class: 'snw' }, [
        // Target handles on top — non-initial nodes
        !isInitial &&
          tHandles.map((hd) =>
            h(Handle, {
              id: hd.id,
              type: 'target',
              position: Position.Top,
              class: hcBase,
              style: { left: `${hd.offsetPct}%`, transform: 'translateX(-50%)' },
              connectable: isEditable,
            }),
          ),
        h(
          'div',
          {
            class: nodeCls,
            style: { width: `${STAGE_W}px` },
            'data-testid': `workflow-canvas-node-${stageId}`,
          },
          [
            h('div', { class: 'sn-row' }, [
              h('div', { class: icoCls }, [h(IcoComponent, { class: 'sn-ico' })]),
              h('div', { class: 'sn-body' }, [
                h('div', { class: 'sn-name' }, label),
                h('div', { class: 'sn-code' }, code),
              ]),
              (isInitial || isFinal) &&
                h(
                  'span',
                  {
                    class: isInitial ? 'sn-tag sn-tag--start' : 'sn-tag sn-tag--end',
                  },
                  isInitial ? 'بداية' : 'نهاية',
                ),
            ]),
          ],
        ),
        // Source handles on bottom — non-final nodes
        !isFinal &&
          bHandles.map((hd) =>
            h(Handle, {
              id: hd.id,
              type: 'source',
              position: Position.Bottom,
              class: hcBase,
              style: { left: `${hd.offsetPct}%`, transform: 'translateX(-50%)' },
              connectable: isEditable,
            }),
          ),
      ])
    }
  },
}

const nodeTypes: NodeTypesObject = { stage: StageNode as any }

// ── Nodes: only stage nodes ───────────────────────────────────────────────────
const nodes = computed<Node[]>(() =>
  (graph.value?.nodes ?? []).map((n) => ({
    id: `stage-${n.id}`,
    type: 'stage',
    position: nodePositions.value.get(`stage-${n.id}`) ??
      autoPositions.value.get(n.id) ?? { x: 0, y: 0 },
    data: {
      label: n.display_label || n.name,
      code: n.code,
      isInitial: n.is_initial,
      isFinal: n.is_final,
      stageId: n.id,
      editable: editable.value,
      srcHandles: nodeHandles.value.srcHandles.get(n.id) ?? [],
      tgtHandles: nodeHandles.value.tgtHandles.get(n.id) ?? [],
    },
    draggable: true,
    selectable: true,
    connectable: editable.value,
  })),
)

// ── Edges: stage→stage with action label ─────────────────────────────────────
// Each edge uses its own unique handle id (includes edge DB id) so parallel
// transitions between the same node pair exit/enter at different X positions.
const edges = computed<Edge[]>(() =>
  (graph.value?.edges ?? []).map((e) => {
    const edgeId = `e${e.id}`
    const override = edgeHandleOverrides.value.get(edgeId)
    const color = e.is_return ? 'var(--color-edge-return)' : 'var(--color-edge-fwd)'
    const style = {
      stroke: color,
      strokeWidth: 1.5,
      strokeDasharray: e.is_return ? '6 3' : undefined,
    }
    const marker = { type: MarkerType.Arrow, color, width: 18, height: 18 }
    const label = e.action_name || e.action_code || `#${e.action_id}`
    return {
      id: edgeId,
      source: `stage-${e.from_stage_id}`,
      sourceHandle: override?.sourceHandle ?? `${e.from_stage_id}-b-${e.id}`,
      target: `stage-${e.to_stage_id}`,
      targetHandle: override?.targetHandle ?? `${e.to_stage_id}-t-${e.id}`,
      type: 'default',
      animated: e.is_return,
      label,
      labelStyle: { fill: 'var(--nd-txt)', fontSize: '11px', fontWeight: '600' },
      labelBgStyle: { fill: 'var(--nd-bg)', fillOpacity: 0.92 },
      labelBgPadding: [6, 4] as [number, number],
      labelBgBorderRadius: 6,
      style,
      markerEnd: marker,
      selectable: editable.value,
      data: { transitionId: e.id },
    }
  }),
)

// ── Drag position tracking ────────────────────────────────────────────────────
function onNodeDragStop({ node }: { node: Node }) {
  nodePositions.value = new Map(nodePositions.value).set(node.id, { ...node.position })
}

// ── Auto-arrange ──────────────────────────────────────────────────────────────
function autoArrange() {
  nodePositions.value = new Map()
  void nextTick(() => fitView({ duration: 400, padding: 0.12 }))
}

// ── Stage dialog ──────────────────────────────────────────────────────────────
const stageDialogOpen = ref(false)
const stageDialogMode = ref<'create' | 'edit'>('create')
const editingStage = ref<WorkflowStage | null>(null)
const stageIsInitial = ref(false)
const stageIsFinal = ref(false)

const stageSchema = toTypedSchema(
  z.object({
    code: z
      .string()
      .min(1, 'الرمز مطلوب')
      .regex(/^[A-Za-z0-9_-]+$/, 'يسمح بالحروف والأرقام والشرطات فقط'),
    name: z.string().min(1, 'الاسم مطلوب'),
  }),
)
const stageForm = useForm({ validationSchema: stageSchema })

function openAddStage() {
  stageDialogMode.value = 'create'
  editingStage.value = null
  stageIsInitial.value = false
  stageIsFinal.value = false
  stageForm.resetForm({ values: { code: '', name: '' } })
  stageDialogOpen.value = true
}

function openEditStage(stage: WorkflowStage) {
  stageDialogMode.value = 'edit'
  editingStage.value = stage
  stageIsInitial.value = stage.is_initial
  stageIsFinal.value = stage.is_final
  stageForm.resetForm({ values: { code: stage.code, name: stage.name } })
  stageDialogOpen.value = true
}

const onStageSubmit = stageForm.handleSubmit(async (values) => {
  try {
    const payload: WorkflowStagePayload = {
      code: values.code,
      name: values.name,
      is_initial: stageIsInitial.value,
      is_final: stageIsFinal.value,
    }
    if (stageDialogMode.value === 'edit' && editingStage.value) {
      await updateStage(editingStage.value, payload)
      toast.success('تم تحديث المرحلة')
    } else {
      await createStage(props.version.id, payload)
      toast.success('تمت إضافة المرحلة')
    }
    stageDialogOpen.value = false
    await fetchGraph(props.version.id)
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حفظ المرحلة'))
  }
})

// ── Transition dialog ─────────────────────────────────────────────────────────
const transDialogOpen = ref(false)
const transFromId = ref('')
const transToId = ref('')
const transActionId = ref('')
const transRequiresComment = ref(false)
const transError = ref<string | null>(null)

const canSubmitTrans = computed(() => transFromId.value && transToId.value && transActionId.value)

function openAddTransition(fromStageId?: number, toStageId?: number) {
  transFromId.value = fromStageId ? String(fromStageId) : ''
  transToId.value = toStageId ? String(toStageId) : ''
  transActionId.value = ''
  transRequiresComment.value = false
  transError.value = null
  transDialogOpen.value = true
}

async function submitTransition() {
  if (!canSubmitTrans.value) return
  transError.value = null
  try {
    await createTransition(props.version.id, {
      from_stage_id: Number(transFromId.value),
      action_id: Number(transActionId.value),
      to_stage_id: Number(transToId.value),
      requires_comment: transRequiresComment.value,
    })
    toast.success('تمت إضافة الانتقال')
    transDialogOpen.value = false
    await fetchGraph(props.version.id)
  } catch (cause) {
    transError.value = extractApiErrorMessage(cause, 'تعذّر حفظ الانتقال')
  }
}

// ── Connection drag → pre-fill transition dialog ──────────────────────────────
function onConnect(conn: Connection) {
  const from = Number(conn.source?.replace('stage-', ''))
  const to = Number(conn.target?.replace('stage-', ''))
  if (from && to && editable.value) openAddTransition(from, to)
}

// ── Edge drag-to-reconnect ────────────────────────────────────────────────────
// When user drags an edge endpoint to a new stage, update the transition via API.
const { updateEdge } = useVueFlow()

async function onEdgeUpdate({ edge, connection }: EdgeUpdateEvent) {
  if (!editable.value) return
  const transitionId = edge.data?.transitionId as number | undefined
  if (!transitionId) return
  const transition = transitions.value.find((t) => t.id === transitionId)
  if (!transition) return
  const newFrom = Number(connection.source?.replace('stage-', ''))
  const newTo = Number(connection.target?.replace('stage-', ''))
  if (!newFrom || !newTo) return
  // No change — user dropped back on same handles
  if (newFrom === transition.from_stage_id && newTo === transition.to_stage_id) return
  // Save handle choice before API call so computed doesn't snap back on graph refresh
  const newSourceHandle = connection.sourceHandle ?? edge.sourceHandle ?? `${newFrom}-r`
  const newTargetHandle = connection.targetHandle ?? edge.targetHandle ?? `${newTo}-l`
  edgeHandleOverrides.value = new Map(edgeHandleOverrides.value).set(edge.id, {
    sourceHandle: newSourceHandle,
    targetHandle: newTargetHandle,
  })
  try {
    updateEdge(edge as GraphEdge, connection)
    if (newFrom === transition.from_stage_id) {
      // Only target changed → PATCH; edge id stays the same, override stays keyed to it
      await updateTransition(transition, { to_stage_id: newTo })
    } else {
      // Source changed → delete + create; new row gets a new DB id → re-key the override
      await deleteTransition(transition)
      const created = await createTransition(props.version.id, {
        from_stage_id: newFrom,
        action_id: transition.action_id,
        to_stage_id: newTo,
        requires_comment: transition.requires_comment,
      })
      // Move override from old edge id to new edge id before fetchGraph re-renders
      const overrides = new Map(edgeHandleOverrides.value)
      overrides.delete(edge.id)
      overrides.set(`e${created.id}`, {
        sourceHandle: newSourceHandle,
        targetHandle: newTargetHandle,
      })
      edgeHandleOverrides.value = overrides
    }
    await fetchGraph(props.version.id)
    toast.success('تم تحديث الانتقال')
  } catch (cause) {
    // On failure drop override so edge reverts to auto-routed position
    const overrides = new Map(edgeHandleOverrides.value)
    overrides.delete(edge.id)
    edgeHandleOverrides.value = overrides
    toast.error(extractApiErrorMessage(cause, 'تعذّر تحديث الانتقال'))
    await fetchGraph(props.version.id)
  }
}

// ── Edge double-click → delete transition ────────────────────────────────────
async function onEdgeDblClick({ edge }: EdgeMouseEvent) {
  if (!editable.value) return
  const transitionId = edge.data?.transitionId as number | undefined
  if (!transitionId) return
  const transition = transitions.value.find((t) => t.id === transitionId)
  if (!transition) return
  if (!confirm(`حذف الانتقال "${edge.label}"؟`)) return
  try {
    await deleteTransition(transition)
    await fetchGraph(props.version.id)
    await fetchTransitions(props.version.id)
    toast.success('تم حذف الانتقال')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر حذف الانتقال'))
  }
}

// ── Delete key on selected edges ─────────────────────────────────────────────
// VueFlow fires edges-change with type 'remove' when Delete/Backspace pressed on selected edge.
// We intercept and call the API instead of letting VueFlow remove it from local state.
async function onEdgesChange(changes: Array<{ type: string; id?: string }>) {
  if (!editable.value) return
  const removals = changes.filter((c) => c.type === 'remove')
  for (const c of removals) {
    const edgeId = c.id
    if (!edgeId) continue
    // edgeId format: "e{transitionId}"
    const transitionId = Number(edgeId.replace('e', ''))
    if (!transitionId) continue
    const transition = transitions.value.find((t) => t.id === transitionId)
    if (!transition) continue
    try {
      await deleteTransition(transition)
      toast.success('تم حذف الانتقال')
    } catch (cause) {
      toast.error(extractApiErrorMessage(cause, 'تعذّر حذف الانتقال'))
    }
    await fetchGraph(props.version.id)
    await fetchTransitions(props.version.id)
  }
}

// ── Double-click stage → edit dialog ─────────────────────────────────────────
function onNodeDblClick({ node }: NodeMouseEvent) {
  if (!editable.value) return
  if (node.type === 'stage') {
    const stage = stages.value.find((s) => s.id === (node.data.stageId as number))
    if (stage) openEditStage(stage)
  }
}

// ── Load / refresh ────────────────────────────────────────────────────────────
async function load(versionId: number) {
  await Promise.all([
    fetchGraph(versionId),
    fetchStages(versionId),
    fetchActions(),
    fetchTransitions(versionId),
  ])
}

watch(
  () => props.version.id,
  (id) => void load(id),
  { immediate: true },
)

function handleZoomIn() {
  void flowZoomIn({ duration: 180 })
}
function handleZoomOut() {
  void flowZoomOut({ duration: 180 })
}
function handleFit() {
  void fitView({ duration: 300, padding: 0.12 })
}
</script>

<template>
  <section class="cs" aria-label="لوحة مسار العمل">
    <!-- Toolbar -->
    <div class="ctb">
      <div class="ctb-l">
        <div class="ctb-title-row">
          <h3 class="ctb-title">لوحة مسار العمل</h3>
          <Badge v-if="editable" class="bdg-e"> <span class="bdg-dot" />قابلة للتعديل </Badge>
          <Badge v-else data-testid="workflow-canvas-readonly" variant="outline" class="gap-1">
            <Lock class="h-3 w-3" />للعرض فقط
          </Badge>
        </div>
        <p class="ctb-sub">
          <span v-if="editable"
            >عرض بصري تفاعلي للمراحل وانتقالاتها · اسحب من المقبض لربط المراحل · انقر مرتين
            للتعديل</span
          >
          <span v-else>للعرض فقط — استنسخ نسخة مسودة لإجراء تعديلات</span>
        </p>
      </div>
      <div v-if="editable" class="ctb-r">
        <Button size="sm" variant="outline" @click="autoArrange">
          <LayoutGrid class="h-3.5 w-3.5" />ترتيب تلقائي
        </Button>
        <Button size="sm" variant="outline" @click="openAddStage">
          <Plus class="h-3.5 w-3.5" />مرحلة
        </Button>
        <Button size="sm" @click="openAddTransition()"> <Plus class="h-3.5 w-3.5" />انتقال </Button>
      </div>
    </div>

    <!-- States -->
    <Alert v-if="error" variant="destructive" role="alert">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>تعذّر تحميل لوحة مسار العمل</AlertTitle>
      <AlertDescription>{{ error }}</AlertDescription>
    </Alert>

    <Skeleton v-else-if="loading" class="h-[600px] w-full rounded-xl" />

    <Empty v-else-if="!graph || graph.nodes.length === 0">
      <EmptyHeader>
        <EmptyTitle>لا توجد مراحل لعرضها</EmptyTitle>
        <EmptyDescription>أضف مراحل وانتقالات لبناء المسار.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <!-- Canvas -->
    <div v-else data-testid="workflow-canvas" class="cf">
      <VueFlow
        class="cv"
        :nodes="nodes"
        :edges="edges"
        :node-types="nodeTypes"
        :nodes-draggable="true"
        :edges-updatable="editable"
        :connectable="editable"
        :zoom-on-scroll="true"
        :pan-on-drag="true"
        :pan-on-scroll="false"
        :auto-pan-on-node-drag="true"
        :auto-pan-on-connect="true"
        :fit-view-on-init="true"
        :fit-view-on-init-options="{ padding: 0.12 }"
        :min-zoom="0.2"
        :max-zoom="3"
        :delete-key-code="editable ? 'Delete' : null"
        dir="ltr"
        @node-drag-stop="onNodeDragStop"
        @connect="onConnect"
        @edge-update="onEdgeUpdate"
        @edge-double-click="onEdgeDblClick"
        @edges-change="onEdgesChange"
        @node-double-click="onNodeDblClick"
      >
        <Background :variant="BackgroundVariant.Dots" :size="1.5" :gap="24" />

        <Panel position="bottom-left" class="ccp">
          <button type="button" class="ccb" aria-label="تكبير" @click="handleZoomIn">
            <ZoomIn class="h-4 w-4" />
          </button>
          <button type="button" class="ccb" aria-label="تصغير" @click="handleZoomOut">
            <ZoomOut class="h-4 w-4" />
          </button>
          <button type="button" class="ccb" aria-label="ملاءمة" @click="handleFit">
            <Expand class="h-4 w-4" />
          </button>
        </Panel>
      </VueFlow>
    </div>

    <!-- Stage dialog (add / edit) -->
    <Dialog v-model:open="stageDialogOpen">
      <DialogContent class="max-w-md">
        <DialogHeader>
          <DialogTitle>{{
            stageDialogMode === 'edit' ? 'تعديل المرحلة' : 'إضافة مرحلة'
          }}</DialogTitle>
          <DialogDescription>عرّف بيانات المرحلة ضمن النسخة المسودة.</DialogDescription>
        </DialogHeader>
        <form class="flex flex-col gap-4" @submit="onStageSubmit">
          <FormField v-slot="{ componentField }" name="code">
            <FormItem>
              <FormLabel>الرمز</FormLabel>
              <FormControl
                ><Input v-bind="componentField" placeholder="INTAKE" dir="ltr"
              /></FormControl>
              <FormMessage />
            </FormItem>
          </FormField>
          <FormField v-slot="{ componentField }" name="name">
            <FormItem>
              <FormLabel>الاسم</FormLabel>
              <FormControl
                ><Input v-bind="componentField" placeholder="استلام الطلب"
              /></FormControl>
              <FormMessage />
            </FormItem>
          </FormField>
          <div class="flex gap-6">
            <div class="flex items-center gap-2">
              <Checkbox id="s-init" v-model:checked="stageIsInitial" />
              <Label for="s-init">مرحلة البداية</Label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox id="s-fin" v-model:checked="stageIsFinal" />
              <Label for="s-fin">مرحلة النهاية</Label>
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" @click="stageDialogOpen = false">إلغاء</Button>
            <Button type="submit">{{ stageDialogMode === 'edit' ? 'حفظ' : 'إضافة' }}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Transition dialog -->
    <Dialog v-model:open="transDialogOpen">
      <DialogContent class="max-w-md">
        <DialogHeader>
          <DialogTitle>إضافة انتقال</DialogTitle>
          <DialogDescription>اربط مرحلتين بإجراء.</DialogDescription>
        </DialogHeader>
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <Label>من المرحلة</Label>
            <Select v-model="transFromId">
              <SelectTrigger class="w-full"
                ><SelectValue placeholder="اختر المرحلة"
              /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="s in stages" :key="s.id" :value="String(s.id)">{{
                  s.name
                }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="flex flex-col gap-1.5">
            <Label>الإجراء</Label>
            <Select v-model="transActionId">
              <SelectTrigger class="w-full"
                ><SelectValue placeholder="اختر الإجراء"
              /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="a in actions" :key="a.id" :value="String(a.id)">{{
                  a.name
                }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="flex flex-col gap-1.5">
            <Label>إلى المرحلة</Label>
            <Select v-model="transToId">
              <SelectTrigger class="w-full"
                ><SelectValue placeholder="اختر المرحلة"
              /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="s in stages" :key="s.id" :value="String(s.id)">{{
                  s.name
                }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="flex items-center gap-2">
            <Checkbox id="t-cmt" v-model:checked="transRequiresComment" />
            <Label for="t-cmt">يتطلب تعليق</Label>
          </div>
          <p v-if="transError" class="text-xs text-[var(--severity-red)]" role="alert">
            {{ transError }}
          </p>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="transDialogOpen = false">إلغاء</Button>
          <Button :disabled="!canSubmitTrans" @click="submitTransition">إضافة</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </section>
</template>

<style>
/* ── Theme tokens: canvas + nodes ─────────────────────────────────────────── */
:root {
  --cv-bg: oklch(0.976 0 0);
  --cv-dot: oklch(0.84 0 0);
  --nd-bg: #ffffff;
  --nd-brd: #e2e5ea;
  --nd-brd-h: #b8bec9;
  --nd-shd: 0 1px 3px rgb(0 0 0/0.08), 0 0 0 1px rgb(0 0 0/0.04);
  --nd-shd-h: 0 4px 14px rgb(0 0 0/0.12), 0 0 0 1px rgb(0 0 0/0.06);
  --nd-txt: #1c222b;
  --nd-sub: #6c757d;
  --nd-ico-bg: #f1f3f6;
  --color-edge-fwd: #9ba4b1;
  --color-edge-return: var(--severity-amber);
  --hd-bg: #9ba4b1;
  --hd-brd: #ffffff;
}
.dark {
  --cv-bg: oklch(0.145 0.004 265);
  --cv-dot: oklch(0.25 0.005 265);
  --nd-bg: oklch(0.205 0.005 265);
  --nd-brd: oklch(0.3 0.006 265);
  --nd-brd-h: oklch(0.4 0.006 265);
  --nd-shd: 0 1px 4px rgb(0 0 0/0.45), 0 0 0 1px rgb(255 255 255/0.05);
  --nd-shd-h: 0 4px 16px rgb(0 0 0/0.55), 0 0 0 1px rgb(255 255 255/0.08);
  --nd-txt: oklch(0.92 0 0);
  --nd-sub: oklch(0.6 0 0);
  --nd-ico-bg: oklch(0.26 0.005 265);
  --color-edge-fwd: oklch(0.48 0.005 265);
  --hd-bg: oklch(0.48 0.005 265);
  --hd-brd: oklch(0.205 0.005 265);
}
</style>

<style scoped>
/* ── Section ─────────────────────────────────────────────────────────────── */
.cs {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* ── Toolbar ─────────────────────────────────────────────────────────────── */
.ctb {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 4px;
}
.ctb-l {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.ctb-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.ctb-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--foreground);
  font-family: var(--font-section, inherit);
}
.ctb-sub {
  font-size: 12px;
  color: var(--muted-foreground);
}
.ctb-r {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.bdg-e {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  border: 1px solid color-mix(in srgb, var(--severity-green) 30%, transparent);
  background: color-mix(in srgb, var(--severity-green) 10%, transparent);
  color: var(--severity-green);
}
.bdg-dot {
  width: 6px;
  height: 6px;
  border-radius: 9999px;
  background: var(--severity-green);
}

/* ── Canvas frame ─────────────────────────────────────────────────────────── */
.cf {
  position: relative;
  height: 600px;
  border-radius: 12px;
  border: 1px solid var(--border);
  overflow: hidden;
  background: var(--cv-bg);
}
.cv {
  width: 100%;
  height: 100%;
  background: transparent !important;
}

/* Background dot colour via CSS var */
:deep(.vue-flow__background) {
  background: var(--cv-bg);
}
:deep(.vue-flow__background pattern circle) {
  fill: var(--cv-dot);
}
:deep(.vue-flow__attribution) {
  display: none;
}

/* ── Stage node ─────────────────────────────────────────────────────────── */
:deep(.snw) {
  position: relative;
}
:deep(.sn) {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1.5px solid var(--nd-brd);
  background: var(--nd-bg);
  padding: 10px 12px;
  box-shadow: var(--nd-shd);
  cursor: grab;
  transition:
    border-color 0.15s,
    box-shadow 0.15s;
}
:deep(.sn:hover) {
  border-color: var(--nd-brd-h);
  box-shadow: var(--nd-shd-h);
}
:deep(.vue-flow__node.selected .sn) {
  border-color: #0066cc;
  box-shadow:
    0 0 0 3px color-mix(in srgb, #0066cc 20%, transparent),
    var(--nd-shd-h);
}
:deep(.node-initial) {
  border-color: color-mix(in srgb, var(--severity-green) 60%, var(--nd-brd));
  border-top: 3px solid var(--severity-green);
}
:deep(.node-final) {
  border-color: color-mix(in srgb, var(--severity-amber) 60%, var(--nd-brd));
  border-bottom: 3px solid var(--severity-amber);
}

:deep(.sn-row) {
  display: flex;
  align-items: center;
  gap: 10px;
}
:deep(.sn-icon) {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: var(--nd-ico-bg);
  flex-shrink: 0;
}
:deep(.sn-icon--start) {
  background: color-mix(in srgb, var(--severity-green) 14%, transparent);
}
:deep(.sn-icon--end) {
  background: color-mix(in srgb, var(--severity-amber) 14%, transparent);
}
:deep(.sn-ico) {
  width: 14px;
  height: 14px;
  color: var(--nd-sub);
}
:deep(.sn-icon--start .sn-ico) {
  color: var(--severity-green);
}
:deep(.sn-icon--end .sn-ico) {
  color: var(--severity-amber);
}
:deep(.sn-body) {
  flex: 1;
  min-width: 0;
}
:deep(.sn-name) {
  font-size: 12px;
  font-weight: 600;
  color: var(--nd-txt);
  line-height: 1.35;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
:deep(.sn-code) {
  font-size: 10px;
  font-family: monospace;
  color: var(--nd-sub);
  margin-top: 2px;
  letter-spacing: 0.02em;
}

/* Inline role tag (replaces old badge row) */
:deep(.sn-tag) {
  flex-shrink: 0;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.04em;
  padding: 2px 7px;
  border-radius: 9999px;
  line-height: 1.5;
}
:deep(.sn-tag--start) {
  background: color-mix(in srgb, var(--severity-green) 12%, transparent);
  color: var(--severity-green);
  border: 1px solid color-mix(in srgb, var(--severity-green) 28%, transparent);
}
:deep(.sn-tag--end) {
  background: color-mix(in srgb, var(--severity-amber) 12%, transparent);
  color: var(--severity-amber);
  border: 1px solid color-mix(in srgb, var(--severity-amber) 28%, transparent);
}

/* ── Handles: hidden by default, visible on node hover or connecting ─────── */
:deep(.fh) {
  width: 10px !important;
  height: 10px !important;
  background: var(--hd-bg) !important;
  border: 2px solid var(--hd-brd) !important;
  border-radius: 9999px;
  opacity: 0;
  transition:
    background 0.1s,
    transform 0.1s,
    opacity 0.1s;
}
:deep(.vue-flow__node:hover .fh) {
  opacity: 1;
}
:deep(.fh:hover),
:deep(.fh.vue-flow__handle-connecting) {
  background: #0066cc !important;
  transform: scale(1.4);
  opacity: 1;
}
:deep(.fh.vue-flow__handle-valid) {
  background: var(--severity-green) !important;
  opacity: 1;
}
:deep(.fh-ro) {
  cursor: default !important;
  pointer-events: none;
}

/* ── Edges ──────────────────────────────────────────────────────────────── */
:deep(.vue-flow__edge-path) {
  stroke-linecap: round;
  stroke-linejoin: round;
}
:deep(.vue-flow__edge:hover .vue-flow__edge-path) {
  stroke-width: 2.5 !important;
}
:deep(.vue-flow__edge.selected .vue-flow__edge-path) {
  stroke-width: 2.5 !important;
}
:deep(.vue-flow__edge-label) {
  cursor: pointer;
}
:deep(.vue-flow__edgelabel-renderer) {
  pointer-events: all;
}

/* ── Controls panel ─────────────────────────────────────────────────────── */
.ccp {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 6px;
}
.ccb {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--muted-foreground);
  box-shadow: 0 1px 3px rgb(0 0 0/0.08);
  cursor: pointer;
  transition:
    background 0.12s,
    color 0.12s;
}
.ccb:hover {
  background: var(--muted);
  color: var(--foreground);
}
</style>
