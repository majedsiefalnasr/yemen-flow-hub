<script setup lang="ts">
import { computed } from 'vue'
import { Check, X } from 'lucide-vue-next'

const props = defineProps<{
  password: string
}>()

interface Rule {
  label: string
  test: (password: string) => boolean
}

const rules: Rule[] = [
  { label: 'على الأقل 8 أحرف', test: (p) => p.length >= 8 },
  { label: 'حرف كبير واحد على الأقل', test: (p) => /[A-Z]/.test(p) },
  { label: 'حرف صغير واحد على الأقل', test: (p) => /[a-z]/.test(p) },
  { label: 'رقم واحد على الأقل', test: (p) => /\d/.test(p) },
  { label: 'رمز خاص واحد على الأقل', test: (p) => /[^A-Z0-9]/i.test(p) },
]

const checkedRules = computed(() =>
  rules.map(rule => ({
    label: rule.label,
    passed: rule.test(props.password),
  })),
)
</script>

<template>
  <ul class="mt-2 space-y-1" role="list" aria-label="متطلبات كلمة المرور">
    <li
      v-for="rule in checkedRules"
      :key="rule.label"
      class="flex items-center gap-2 text-xs"
      :class="rule.passed ? 'text-[var(--severity-green)]' : 'text-muted-foreground'"
    >
      <Check v-if="rule.passed" class="h-3.5 w-3.5 shrink-0" />
      <X v-else class="h-3.5 w-3.5 shrink-0" />
      {{ rule.label }}
    </li>
  </ul>
</template>
