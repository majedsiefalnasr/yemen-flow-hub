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
      <div class="min-w-0">
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-bold tracking-tight">
            {{ title }}
          </h1>
          <span v-if="statusSummary" class="text-sm font-medium text-muted-foreground">
            ({{ statusSummary }})
          </span>
        </div>
        <p
          v-if="subtitle"
          class="mt-1 text-sm text-muted-foreground"
        >
          {{ subtitle }}
        </p>
        <p v-if="lastUpdated" class="mt-1 text-xs text-muted-foreground/70">
          آخر تحديث: {{ lastUpdated }}
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <slot name="toolbar" />
        <slot name="actions" />
      </div>
    </div>
    <slot name="filter-bar" />
  </div>
</template>
