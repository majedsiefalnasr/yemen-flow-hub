<script setup lang="ts" generic="TData">
import type { Row } from '@tanstack/vue-table'
import { MoreHorizontal } from 'lucide-vue-next'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

export interface RowAction<TData> {
  label: string
  icon?: any
  disabled?: boolean | ((row: Row<TData>) => boolean)
  destructive?: boolean
  hidden?: boolean | ((row: Row<TData>) => boolean)
  confirm?: {
    title: string
    description: string
    confirmLabel?: string
    cancelLabel?: string
  }
  onClick: (row: Row<TData>) => void
}

const props = defineProps<{
  row: Row<TData>
  actions: RowAction<TData>[]
}>()

const showConfirm = ref(false)
const pendingAction = ref<RowAction<TData> | null>(null)

const visibleActions = computed(() =>
  props.actions.filter((action) => {
    if (typeof action.hidden === 'function') return !action.hidden(props.row)
    return !action.hidden
  }),
)

function handleAction(action: RowAction<TData>) {
  if (action.confirm) {
    pendingAction.value = action
    showConfirm.value = true
  } else {
    action.onClick(props.row)
  }
}

function confirmAction() {
  if (pendingAction.value) {
    pendingAction.value.onClick(props.row)
    pendingAction.value = null
  }
  showConfirm.value = false
}
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button
        variant="ghost"
        class="flex h-8 w-8 p-0 data-[state=open]:bg-muted"
      >
        <MoreHorizontal class="h-4 w-4" />
        <span class="sr-only">فتح القائمة</span>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-[160px]">
      <template v-for="(action, i) in visibleActions" :key="action.label">
        <DropdownMenuSeparator v-if="i > 0 && visibleActions[i - 1]?.destructive !== action.destructive" />
        <DropdownMenuItem
          :class="action.destructive ? 'text-destructive focus:text-destructive' : ''"
          :disabled="typeof action.disabled === 'function' ? action.disabled(row) : action.disabled"
          @click="handleAction(action)"
        >
          <component v-if="action.icon" :is="action.icon" class="me-2 h-4 w-4" />
          {{ action.label }}
        </DropdownMenuItem>
      </template>
    </DropdownMenuContent>
  </DropdownMenu>

  <AlertDialog v-model:open="showConfirm">
    <AlertDialogContent dir="rtl">
      <AlertDialogHeader>
        <AlertDialogTitle>{{ pendingAction?.confirm?.title }}</AlertDialogTitle>
        <AlertDialogDescription>{{ pendingAction?.confirm?.description }}</AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>{{ pendingAction?.confirm?.cancelLabel ?? 'إلغاء' }}</AlertDialogCancel>
        <AlertDialogAction
          class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
          @click="confirmAction"
        >
          {{ pendingAction?.confirm?.confirmLabel ?? 'تأكيد' }}
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
