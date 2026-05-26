<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { AlarmClock } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'
import { Badge } from '../ui/badge'

const props = defineProps<{
  claimedUntil?: string | null
  heartbeatActive?: boolean
}>()

defineEmits<{
  release: []
}>()

const now = ref(Date.now())
let timer: ReturnType<typeof setInterval> | null = null

const remainingLabel = computed(() => {
  if (!props.claimedUntil) return 'متبقي --:-- من 15:00'
  const remainingMs = Math.max(0, new Date(props.claimedUntil).getTime() - now.value)
  const totalSeconds = Math.floor(remainingMs / 1000)
  const minutes = Math.floor(totalSeconds / 60).toString().padStart(2, '0')
  const seconds = (totalSeconds % 60).toString().padStart(2, '0')
  return `متبقي ${minutes}:${seconds} من 15:00`
})

onMounted(() => {
  timer = setInterval(() => { now.value = Date.now() }, 1000)
})

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>

<template>
  <Alert dir="rtl" class="flex items-center gap-3 border-[#5856d6]/30 bg-[#5856d6]/5">
    <AlarmClock class="h-5 w-5 flex-shrink-0 text-[#5856d6]" aria-hidden="true" />
    <AlertDescription class="flex-1 text-sm font-medium text-foreground">
      <span class="font-semibold text-[#5856d6]">أنت المراجع النشط — الطلب محجوز لك حالياً</span>
      <span class="mx-2 text-muted-foreground">·</span>
      <span class="text-muted-foreground">{{ remainingLabel }}</span>
    </AlertDescription>
    <span class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
      <span
        class="h-2 w-2 rounded-full"
        :class="heartbeatActive ? 'bg-green-600 animate-pulse' : 'bg-muted-foreground'"
        aria-hidden="true"
      />
      heartbeat
    </span>
    <button
      class="flex-shrink-0 px-3 py-1.5 bg-background border border-[#5856d6]/30 text-[#5856d6] text-xs font-semibold rounded-xl hover:bg-[#5856d6]/10 transition-colors"
      @click="$emit('release')"
    >
      تحرير الحجز
    </button>
    <Badge class="flex-shrink-0 bg-[#5856d6] text-white hover:bg-[#5856d6]">مراجعة نشطة</Badge>
  </Alert>
</template>
