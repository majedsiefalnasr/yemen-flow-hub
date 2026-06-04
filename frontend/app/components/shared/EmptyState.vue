<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    variant?: 'staff'
    title?: string
    description?: string
  }>(),
  {
    variant: 'staff',
    title: 'لا يوجد موظفون مسجّلون',
    description: 'ابدأ بإضافة أول موظف في بنكك لمنحه صلاحيات الدخول للنظام.',
  },
)
</script>

<template>
  <div
    class="flex flex-col items-center justify-center gap-4 px-6 py-20"
    :data-empty-state-variant="props.variant"
  >
    <div
      class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded-full bg-[var(--color-surface-subtle)]"
    >
      <svg
        width="48"
        height="48"
        viewBox="0 0 24 24"
        fill="none"
        stroke="var(--color-locked)"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <polyline points="16 11 18 13 22 9" />
      </svg>
    </div>
    <h2 class="text-xl font-semibold text-[var(--color-text-primary)]">{{ props.title }}</h2>
    <p class="max-w-sm text-center text-sm text-[var(--color-text-subtle)]">
      {{ props.description }}
    </p>
    <div class="mt-2 flex justify-center">
      <slot />
    </div>
  </div>
</template>
