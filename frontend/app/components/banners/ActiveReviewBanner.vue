<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { AlarmClock } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'
import { Badge } from '../ui/badge'
import { Button } from '../ui/button'
import { Tooltip, TooltipContent, TooltipTrigger } from '../ui/tooltip'

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
  const minutes = Math.floor(totalSeconds / 60)
    .toString()
    .padStart(2, '0')
  const seconds = (totalSeconds % 60).toString().padStart(2, '0')
  return `متبقي ${minutes}:${seconds} من 15:00`
})

// Skip ticks while the page is hidden so background tabs don't burn CPU/battery
// keeping a countdown alive that no one is watching. The first visibility change
// catches up the displayed time.
function tick() {
  if (typeof document === 'undefined' || document.visibilityState === 'visible') {
    now.value = Date.now()
  }
}

onMounted(() => {
  timer = setInterval(tick, 1000)
  if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', tick)
  }
})

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
  if (typeof document !== 'undefined') {
    document.removeEventListener('visibilitychange', tick)
  }
})
</script>

<template>
  <Alert class="flex items-center gap-3 border-[var(--voting)]/30 bg-[var(--voting)]/5">
    <AlarmClock class="h-5 w-5 flex-shrink-0 text-[var(--voting)]" aria-hidden="true" />
    <AlertDescription class="text-foreground flex-1 text-sm font-medium">
      <span class="font-semibold text-[var(--voting)]"
        >أنت المراجع النشط. الطلب محجوز لك حالياً</span
      >
      <span class="text-muted-foreground mx-2">·</span>
      <span class="text-muted-foreground">{{ remainingLabel }}</span>
    </AlertDescription>
    <Tooltip>
      <TooltipTrigger as-child>
        <span class="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
          <span
            class="h-2 w-2 rounded-full"
            :class="
              heartbeatActive ? 'animate-pulse bg-[var(--severity-green)]' : 'bg-muted-foreground'
            "
            aria-hidden="true"
          />
          {{ heartbeatActive ? 'الاتصال نشط' : 'انقطع الاتصال' }}
        </span>
      </TooltipTrigger>
      <TooltipContent>
        <p>
          {{
            heartbeatActive
              ? 'يُجدَّد حجزك تلقائياً كل دقيقة طالما الصفحة مفتوحة'
              : 'توقّف تجديد الحجز. قد يُحرَّر الطلب بعد انتهاء المهلة'
          }}
        </p>
      </TooltipContent>
    </Tooltip>
    <Button size="sm" variant="outline" class="flex-shrink-0" @click="$emit('release')">
      تحرير الحجز
    </Button>
    <Badge class="flex-shrink-0 bg-[var(--voting)] text-white hover:bg-[var(--voting)]"
      >مراجعة نشطة</Badge
    >
  </Alert>
</template>
