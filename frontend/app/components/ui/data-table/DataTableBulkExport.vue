<script setup lang="ts">
import { Download } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

defineEmits<{
  csv: []
  excel: []
  json: []
}>()
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs">
        <Download class="size-3.5" />
        تصدير
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-36">
      <DropdownMenuItem @click="$emit('csv')">تصدير CSV</DropdownMenuItem>
      <DropdownMenuItem @click="$emit('excel')">تصدير Excel</DropdownMenuItem>
      <DropdownMenuItem @click="$emit('json')">تصدير JSON</DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
