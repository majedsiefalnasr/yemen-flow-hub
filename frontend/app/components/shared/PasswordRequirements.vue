<script setup lang="ts">
import { CheckCircle2, XCircle } from 'lucide-vue-next'

interface Rule {
  label: string
  met: boolean
}

defineProps<{
  /** Password string to validate */
  password: string
  /** Custom rule set — if omitted defaults are used */
  rules?: Rule[]
}>()

function defaultRules(password: string): Rule[] {
  return [
    { label: 'على الأقل 8 أحرف', met: password.length >= 8 },
    { label: 'حرف كبير واحد على الأقل', met: /[A-Z]/.test(password) },
    { label: 'حرف صغير واحد على الأقل', met: /[a-z]/.test(password) },
    { label: 'رقم واحد على الأقل', met: /\d/.test(password) },
    { label: 'رمز خاص واحد على الأقل (!@#$…)', met: /[^A-Za-z0-9]/.test(password) },
  ]
}
</script>

<template>
  <ul class="mt-2 space-y-1" aria-live="polite">
    <li
      v-for="rule in (rules ?? defaultRules(password))"
      :key="rule.label"
      class="flex items-center gap-2 text-xs"
    >
      <CheckCircle2
        v-if="rule.met"
        class="h-3.5 w-3.5 shrink-0 text-[var(--severity-green)]"
        aria-hidden="true"
      />
      <XCircle
        v-else
        class="h-3.5 w-3.5 shrink-0 text-muted-foreground/50"
        aria-hidden="true"
      />
      <span :class="rule.met ? 'text-foreground' : 'text-muted-foreground'">{{ rule.label }}</span>
    </li>
  </ul>
</template>
