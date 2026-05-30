<script setup lang="ts">
import { Users } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'
import { Badge } from '../ui/badge'

defineProps<{
  claimerName: string
}>()
</script>

<template>
  <Alert  class="flex items-center gap-3 border-warning bg-amber-50/10">
    <Users class="h-5 w-5 flex-shrink-0 text-amber-600" aria-hidden="true" />
    <AlertDescription class="flex-1 text-sm font-medium">
      محجوز بواسطة <strong>{{ claimerName }}</strong> — يمكنك الاطلاع على الطلب فقط
    </AlertDescription>
    <Badge variant="secondary" class="flex-shrink-0 bg-amber-50/100 text-white hover:bg-amber-50">قيد المراجعة</Badge>
  </Alert>
</template>
