<script setup lang="ts">
import { ChevronLeft } from 'lucide-vue-next'
import { SidebarTrigger } from '@/components/ui/sidebar'
import { Separator } from '@/components/ui/separator'
import SearchForm from '@/components/SearchForm.vue'

type Breadcrumb = {
  label: string
  to?: string
}

defineProps<{
  title: string
  subtitle?: string
  breadcrumbs?: Breadcrumb[]
}>()
</script>

<template>
  <div class="mb-6 pt-4">
    <!-- Trigger row: sidebar toggler + breadcrumb trail + search -->
    <div class="mb-3 flex items-center gap-2">
      <SidebarTrigger class="-ms-1 shrink-0" aria-label="تبديل الشريط الجانبي" />
      <Separator orientation="vertical" class="h-4 data-[orientation=vertical]:h-4" />
      <nav
        v-if="breadcrumbs?.length"
        class="flex min-w-0 flex-1 items-center gap-1 text-xs text-muted-foreground"
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
      <div v-else class="flex-1" />
      <SearchForm />
    </div>

    <!-- Title + actions row -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight">
          {{ title }}
        </h1>
        <p
          v-if="subtitle"
          class="mt-1 text-sm text-muted-foreground"
        >
          {{ subtitle }}
        </p>
      </div>

      <div
        v-if="$slots.actions"
        class="flex items-center gap-2"
      >
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
