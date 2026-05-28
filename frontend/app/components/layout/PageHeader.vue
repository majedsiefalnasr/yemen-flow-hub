<script setup lang="ts">
import { ChevronLeft } from 'lucide-vue-next'

type Breadcrumb = {
  label: string
  to?: string
}

defineProps<{
  title: string
  subtitle?: string
  breadcrumbs?: Breadcrumb[]
  /** Optional status summary line shown below the title row */
  statusSummary?: string
  /** Timestamp label shown in muted text at end of title row */
  lastUpdated?: string
}>()
</script>

<template>
  <div class="mb-6 pt-2">
    <nav
      v-if="breadcrumbs?.length"
      class="mb-3 flex min-w-0 items-center gap-1 text-xs text-muted-foreground"
      aria-label="مسار التنقل"
    >
      <template
        v-for="(breadcrumb, index) in breadcrumbs"
        :key="`${breadcrumb.label}-${index}`"
      >
        <ChevronLeft v-if="index > 0" class="h-3 w-3 shrink-0 opacity-50" aria-hidden="true" />
        <NuxtLink
          v-if="breadcrumb.to"
          :to="breadcrumb.to"
          class="truncate transition-colors hover:text-foreground"
        >
          {{ breadcrumb.label }}
        </NuxtLink>
        <span v-else class="truncate font-medium text-foreground">{{ breadcrumb.label }}</span>
      </template>
    </nav>

    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-bold tracking-tight">
          {{ title }}
        </h1>
        <p v-if="subtitle" class="mt-1 text-sm text-muted-foreground">
          {{ subtitle }}
        </p>
        <p v-if="statusSummary" class="mt-1 text-sm text-muted-foreground">
          {{ statusSummary }}
        </p>
      </div>

      <div class="flex shrink-0 flex-col items-end gap-2">
        <div v-if="$slots.actions" class="flex items-center gap-2">
          <slot name="actions" />
        </div>
        <span v-if="lastUpdated" class="text-xs text-muted-foreground/60">
          آخر تحديث: {{ lastUpdated }}
        </span>
      </div>
    </div>

    <!-- Toolbar slot: secondary action row (filters, view toggles, etc.) -->
    <div v-if="$slots.toolbar" class="mt-3 flex flex-wrap items-center gap-2">
      <slot name="toolbar" />
    </div>

    <!-- Filter bar slot: full-width filter inputs row -->
    <div v-if="$slots['filter-bar']" class="mt-2">
      <slot name="filter-bar" />
    </div>
  </div>
</template>
