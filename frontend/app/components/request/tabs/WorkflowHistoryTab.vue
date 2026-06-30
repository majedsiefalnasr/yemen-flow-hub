<script setup lang="ts">
import { ClipboardList } from 'lucide-vue-next'
import { onMounted, ref, watch } from 'vue'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import AuditTimeline from '@/components/workflow/AuditTimeline.vue'
import { useRequests } from '@/composables/useRequests'
import type { RequestStageHistory } from '@/types/models'

const props = defineProps<{
  requestId: number | null
}>()

const { fetchRequestHistory } = useRequests()
const entries = ref<RequestStageHistory[]>([])

async function loadHistory() {
  if (!props.requestId) {
    entries.value = []
    return
  }
  entries.value = await fetchRequestHistory(props.requestId)
}

onMounted(loadHistory)
watch(() => props.requestId, loadHistory)
</script>

<template>
  <AuditTimeline v-if="requestId" :entries="entries" />
  <Empty v-else>
    <EmptyMedia variant="icon">
      <ClipboardList />
    </EmptyMedia>
    <EmptyHeader>
      <EmptyTitle>لا يوجد سجل بعد</EmptyTitle>
      <EmptyDescription>سيظهر سجل سير العمل بعد تقديم الطلب</EmptyDescription>
    </EmptyHeader>
  </Empty>
</template>
