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
  statusSummary?: string
  lastUpdated?: string
}>()
</script>

<template>
  <div class="mb-6 pt-2">
    <nav
      v-if="breadcrumbs?.length"
      class="font-section text-muted-foreground mb-3 flex min-w-0 items-center gap-1 text-xs leading-5"
      aria-label="مسار التنقل"
    >
      <template v-for="(breadcrumb, index) in breadcrumbs" :key="`${breadcrumb.label}-${index}`">
        <ChevronLeft v-if="index > 0" class="h-3 w-3 shrink-0 opacity-50" aria-hidden="true" />
        <NuxtLink
          v-if="breadcrumb.to"
          :to="breadcrumb.to"
          class="hover:text-foreground truncate transition-colors"
        >
          {{ breadcrumb.label }}
        </NuxtLink>
        <span v-else class="text-foreground truncate font-medium">{{ breadcrumb.label }}</span>
      </template>
    </nav>

    <div
      class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center sm:gap-4"
    >
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <h1 class="font-heading text-foreground text-2xl leading-8 font-semibold">
            {{ title }}
          </h1>
          <span
            v-if="statusSummary"
            class="font-section text-muted-foreground text-sm leading-5 font-medium"
          >
            ({{ statusSummary }})
          </span>
        </div>
        <p v-if="subtitle" class="text-muted-foreground mt-1 max-w-[72ch] text-sm leading-6">
          {{ subtitle }}
        </p>
        <p v-if="lastUpdated" class="text-muted-foreground/70 mt-1 text-xs leading-5 tabular-nums">
          آخر تحديث: {{ lastUpdated }}
        </p>
      </div>

      <div class="flex shrink-0 flex-wrap items-center gap-2">
        <slot name="toolbar" />
        <slot name="actions" />
      </div>
    </div>
    <slot name="filter-bar" />
  </div>
</template>
