<script setup lang="ts">
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
  <div class="mb-6">
    <nav
      v-if="breadcrumbs?.length"
      class="mb-2 flex items-center gap-1.5 text-xs text-gray-600"
    >
      <template
        v-for="(breadcrumb, index) in breadcrumbs"
        :key="`${breadcrumb.label}-${index}`"
      >
        <span v-if="index > 0">/</span>
        <NuxtLink
          v-if="breadcrumb.to"
          :to="breadcrumb.to"
          class="hover:text-gray-900"
        >
          {{ breadcrumb.label }}
        </NuxtLink>
        <span v-else>{{ breadcrumb.label }}</span>
      </template>
    </nav>

    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight">
          {{ title }}
        </h1>
        <p
          v-if="subtitle"
          class="mt-1 text-sm text-gray-600"
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
