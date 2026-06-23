<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { ArrowLeft, CircleDot, Flag, RotateCcw } from 'lucide-vue-next'
import type { WorkflowGraphNode, WorkflowVersion } from '@/types/models'
import { Badge } from '@/components/ui/badge'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { useWorkflowGraph } from '@/composables/useWorkflowGraph'

const props = defineProps<{ version: WorkflowVersion }>()

const { graph, loading, error, fetchGraph } = useWorkflowGraph()

const nodesById = computed<Map<number, WorkflowGraphNode>>(
  () => new Map((graph.value?.nodes ?? []).map((node) => [node.id, node])),
)

function nodeName(id: number): string {
  const node = nodesById.value.get(id)
  return node?.display_label || node?.name || `#${id}`
}

onMounted(() => fetchGraph(props.version.id))
</script>

<template>
  <div class="space-y-3">
    <h3 class="font-section text-sm font-semibold">مخطط سير العمل</h3>

    <p v-if="error" class="text-xs text-[var(--severity-red)]" role="alert">{{ error }}</p>

    <div v-else-if="loading" class="grid gap-2">
      <Skeleton v-for="n in 3" :key="n" class="h-12 w-full rounded-md" />
    </div>

    <Empty v-else-if="!graph || graph.nodes.length === 0">
      <EmptyHeader>
        <EmptyTitle>لا يوجد مخطط</EmptyTitle>
        <EmptyDescription>أضف مراحل وانتقالات ليُولَّد المخطط تلقائياً.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <template v-else>
      <div class="flex flex-wrap gap-2">
        <div
          v-for="node in graph.nodes"
          :key="node.id"
          class="border-border bg-card flex items-center gap-2 rounded-md border px-3 py-2"
          :class="{
            'border-s-4 border-s-[var(--severity-green)]': node.is_initial,
            'border-s-4 border-s-[var(--brand-color)]': node.is_final && !node.is_initial,
          }"
        >
          <CircleDot
            v-if="node.is_initial"
            class="h-4 w-4 text-[var(--severity-green)]"
            aria-hidden="true"
          />
          <Flag
            v-else-if="node.is_final"
            class="h-4 w-4 text-[var(--brand-color)]"
            aria-hidden="true"
          />
          <div class="flex flex-col">
            <span class="text-sm font-medium">{{ node.display_label || node.name }}</span>
            <span class="text-muted-foreground font-mono text-xs">{{ node.code }}</span>
          </div>
          <Badge v-if="node.is_initial" variant="secondary">بداية</Badge>
          <Badge v-if="node.is_final" variant="secondary">نهاية</Badge>
        </div>
      </div>

      <div v-if="graph.edges.length > 0" class="space-y-1.5">
        <h4 class="font-section text-muted-foreground text-xs font-semibold">الانتقالات</h4>
        <ul class="space-y-1">
          <li
            v-for="edge in graph.edges"
            :key="edge.id"
            class="flex flex-wrap items-center gap-2 text-xs"
          >
            <span>{{ nodeName(edge.from_stage_id) }}</span>
            <ArrowLeft class="text-muted-foreground h-3.5 w-3.5" aria-hidden="true" />
            <Badge
              :class="
                edge.is_return
                  ? 'border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]'
                  : ''
              "
              variant="secondary"
            >
              <RotateCcw v-if="edge.is_return" class="me-1 h-3 w-3" aria-hidden="true" />
              {{ edge.action_name || edge.action_code }}
            </Badge>
            <ArrowLeft class="text-muted-foreground h-3.5 w-3.5" aria-hidden="true" />
            <span>{{ nodeName(edge.to_stage_id) }}</span>
            <Badge v-if="edge.is_self_loop" variant="outline">حلقة ذاتية</Badge>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>
