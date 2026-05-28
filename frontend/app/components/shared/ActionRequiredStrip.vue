<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, Info } from 'lucide-vue-next'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'

const props = withDefaults(defineProps<{
  /** Number of items requiring action. Strip is hidden when 0. */
  count: number
  /** Primary message displayed in the strip (no count prefix needed — it's prepended automatically). */
  message: string
  /** Optional secondary detail line (e.g. reference number or reason snippet). */
  detail?: string
  /** CTA button label */
  ctaLabel: string
  /** Route to navigate to on CTA click */
  ctaRoute: string
  /** Semantic severity level — drives color tokens */
  severity?: 'amber' | 'red' | 'blue' | 'indigo'
  /** Override the accessible aria-label */
  ariaLabel?: string
}>(), {
  severity: 'amber',
})

const router = useRouter()

const severityToken = computed(() => {
  const map = {
    amber: 'var(--severity-amber)',
    red: 'var(--severity-red)',
    blue: 'var(--primary)',
    indigo: 'var(--voting)',
  }
  return map[props.severity]
})

const borderClass = computed(() => {
  const map = {
    amber: 'border-s-[var(--severity-amber)]',
    red: 'border-s-[var(--severity-red)]',
    blue: 'border-s-primary',
    indigo: 'border-s-[var(--voting)]',
  }
  return map[props.severity]
})

const bgClass = computed(() => {
  const map = {
    amber: 'bg-[var(--severity-amber)]/5',
    red: 'bg-[var(--severity-red)]/5',
    blue: 'bg-primary/5',
    indigo: 'bg-[var(--voting)]/5',
  }
  return map[props.severity]
})

const iconClass = computed(() => {
  const map = {
    amber: 'text-[var(--severity-amber)]',
    red: 'text-[var(--severity-red)]',
    blue: 'text-primary',
    indigo: 'text-[var(--voting)]',
  }
  return map[props.severity]
})

const IconComponent = computed(() =>
  props.severity === 'blue' || props.severity === 'indigo' ? Info : AlertTriangle,
)

const computedAriaLabel = computed(() =>
  props.ariaLabel ?? `${props.count} ${props.message}`,
)
</script>

<template>
  <Card
    v-if="count > 0"
    class="border-0 border-s-4 shadow-sm"
    :class="[borderClass, bgClass]"
    role="alert"
    :aria-label="computedAriaLabel"
  >
    <CardContent class="flex items-center gap-3 pb-4 pt-4">
      <component
        :is="IconComponent"
        class="h-5 w-5 shrink-0"
        :class="iconClass"
        aria-hidden="true"
      />
      <div class="min-w-0 flex-1">
        <span class="text-sm font-semibold text-foreground">{{ count }} {{ message }}</span>
        <p v-if="detail" class="mt-0.5 truncate text-xs text-muted-foreground">
          {{ detail }}
        </p>
      </div>
      <Button
        size="sm"
        class="shrink-0 text-white hover:opacity-90"
        :style="{ backgroundColor: severityToken }"
        @click="router.push(ctaRoute)"
      >
        {{ ctaLabel }}
      </Button>
    </CardContent>
  </Card>
</template>
