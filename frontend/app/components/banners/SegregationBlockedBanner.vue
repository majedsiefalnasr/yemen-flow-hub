<script setup lang="ts">
import { ShieldAlert } from 'lucide-vue-next'
import { Alert, AlertDescription } from '../ui/alert'
import { Badge } from '../ui/badge'
</script>

<template>
  <Alert  class="flex items-center gap-3 border-border bg-muted/30">
    <ShieldAlert class="h-5 w-5 flex-shrink-0 text-blue-600" aria-hidden="true" />
    <AlertDescription class="flex-1 text-sm font-medium text-foreground">
      لا يمكنك مراجعة هذا الطلب لأنك من أنشأه — مبدأ الفصل بين المهام يمنع مراجعة الموظف لطلباته الخاصة
    </AlertDescription>
    <Badge variant="secondary" class="flex-shrink-0">مقيّد</Badge>
  </Alert>
</template>
