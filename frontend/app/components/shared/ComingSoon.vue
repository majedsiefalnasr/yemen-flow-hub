<script setup lang="ts">
import { Construction } from 'lucide-vue-next'

withDefaults(defineProps<{
  /** Feature name shown in heading */
  feature?: string
  /** Optional description */
  description?: string
}>(), {
  feature: 'هذه الميزة',
  description: 'هذا القسم قيد التطوير وسيكون متاحاً قريباً.',
})
</script>

<template>
  <div class="grid min-h-[40vh] place-items-center p-6 text-center">
    <div>
      <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-muted text-muted-foreground">
        <Construction class="h-7 w-7" />
      </div>
      <h3 class="mt-4 text-lg font-semibold">
        {{ feature }} قادمة قريباً
      </h3>
      <p class="mt-2 text-sm text-muted-foreground">
        {{ description }}
      </p>
      <slot />
    </div>
  </div>
</template>
