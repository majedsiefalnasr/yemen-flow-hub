<script setup lang="ts">
import { Clock } from 'lucide-vue-next'
import { Card, CardContent } from '@/components/ui/card'

defineProps<{
  title?: string
  description?: string
}>()
</script>

<template>
  <div class="flex min-h-[50vh] items-center justify-center" dir="rtl">
    <Card class="max-w-sm border border-dashed text-center shadow-none">
      <CardContent class="flex flex-col items-center gap-4 p-8">
        <div class="rounded-full bg-muted p-4">
          <Clock class="h-8 w-8 text-muted-foreground" />
        </div>
        <div>
          <h2 class="text-lg font-semibold">{{ title || 'قريبًا' }}</h2>
          <p class="mt-1 text-sm text-muted-foreground">
            {{ description || 'هذه الصفحة قيد التطوير وستكون متاحة قريبًا.' }}
          </p>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
