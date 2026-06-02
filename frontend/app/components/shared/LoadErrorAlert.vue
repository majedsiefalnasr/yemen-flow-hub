<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'

const props = withDefaults(defineProps<{
  message: string
  title?: string
  retryLabel?: string
  showRetry?: boolean
}>(), {
  title: 'تعذّر التحميل',
  retryLabel: 'إعادة المحاولة',
  showRetry: true,
})

const emit = defineEmits<{
  retry: []
}>()
</script>

<template>
  <Alert variant="destructive" role="alert" aria-live="assertive" class="min-w-0">
    <AlertCircle class="h-4 w-4 shrink-0" aria-hidden="true" />
    <AlertTitle>{{ props.title }}</AlertTitle>
    <AlertDescription class="break-words">
      {{ props.message }}
    </AlertDescription>
    <AlertAction v-if="props.showRetry">
      <Button variant="outline" size="sm" @click="emit('retry')">
        {{ props.retryLabel }}
      </Button>
    </AlertAction>
  </Alert>
</template>
