<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Users } from 'lucide-vue-next'
import type { WorkflowStage, WorkflowVersion } from '@/types/models'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { useWorkflowStages } from '@/composables/useWorkflowStages'
import StagePermissionEditor from '@/components/workflow/StagePermissionEditor.vue'

const props = defineProps<{ version: WorkflowVersion }>()

const { stages, loading, error, fetchStages } = useWorkflowStages()

const orderedStages = ref<WorkflowStage[]>([])

// keep a stable, sort-ordered snapshot of stages for rendering.
async function load() {
  await fetchStages(props.version.id)
  orderedStages.value = [...stages.value].sort((a, b) => a.sort_order - b.sort_order)
}

onMounted(load)
</script>

<template>
  <div class="space-y-4">
    <div>
      <h3 class="font-section text-sm font-semibold">سير العملية التنظيمية</h3>
      <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
        كل مرحلة تُسند إلى جهة/فريق/دور مع تسمية ظاهرة (المسمى المعروض). هذه الإسنادات تحدد من يرى
        المرحلة ويُنفّذها، وهي مصدر صلاحيات شاشة الطلبات ودوري المهام.
      </p>
    </div>

    <p v-if="error" class="text-xs text-[var(--severity-red)]" role="alert">{{ error }}</p>

    <div v-else-if="loading" class="grid gap-2">
      <Skeleton v-for="n in 3" :key="n" class="h-32 w-full rounded-lg" />
    </div>

    <Empty v-else-if="orderedStages.length === 0" class="py-10">
      <EmptyMedia variant="icon">
        <Users />
      </EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>لا توجد مراحل</EmptyTitle>
        <EmptyDescription>أضف المراحل في تبويب «المراحل» لضبط سير العملية.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div v-else class="space-y-4">
      <Card v-for="stage in orderedStages" :key="stage.id" class="border-0 shadow">
        <CardContent class="space-y-3 p-4">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <Badge
                v-if="stage.is_initial"
                variant="outline"
                class="border-[var(--severity-green)]/40 text-[var(--severity-green)]"
              >
                البداية
              </Badge>
              <Badge
                v-else-if="stage.is_final"
                variant="outline"
                class="border-[var(--brand-color)]/40 text-[var(--brand-color)]"
              >
                النهاية
              </Badge>
              <span class="text-sm font-semibold">{{ stage.name }}</span>
              <span class="text-muted-foreground font-mono text-xs">{{ stage.code }}</span>
            </div>
            <Badge variant="secondary" class="text-xs">مرحلة {{ stage.sort_order }}</Badge>
          </div>
          <StagePermissionEditor :stage="stage" :version="version" />
        </CardContent>
      </Card>
    </div>
  </div>
</template>
