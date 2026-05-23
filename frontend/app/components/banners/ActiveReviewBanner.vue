<script setup lang="ts">
import { CheckCircle2 } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'
import { Badge } from '../ui/badge'

// No props — banner is shown whenever the current user is the active claim holder
</script>

<template>
  <Alert dir="rtl" class="flex items-center gap-3 border-green-200 bg-green-50">
    <CheckCircle2 class="h-5 w-5 flex-shrink-0 text-green-600" aria-hidden="true" />
    <AlertDescription class="flex-1 text-sm font-medium text-green-900">
      أنت المراجع النشط — الطلب محجوز لك حالياً
    </AlertDescription>
    <Badge class="flex-shrink-0 bg-green-600 text-white hover:bg-green-700">مراجعة نشطة</Badge>
  </Alert>
</template>
