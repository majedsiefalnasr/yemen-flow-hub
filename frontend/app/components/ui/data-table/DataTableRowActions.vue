<script setup lang="ts" generic="TData">
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
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

export interface RowAction<T> {
  label: string
  icon?: object
  /** When true, shows a destructive confirmation dialog before calling onAction */
  destructive?: boolean
  confirmTitle?: string
  confirmDescription?: string
  onAction: (row: T) => void | Promise<void>
}

const props = defineProps<{
  row: TData
  actions: RowAction<TData>[]
}>()

const pendingAction = ref<RowAction<TData> | null>(null)
const confirmOpen = ref(false)

function handleClick(action: RowAction<TData>) {
  if (action.destructive) {
    pendingAction.value = action
    confirmOpen.value = true
  } else {
    action.onAction(props.row)
  }
}

async function confirmDestructive() {
  if (pendingAction.value) {
    await pendingAction.value.onAction(props.row)
    pendingAction.value = null
  }
}
</script>

<template>
  <AlertDialog v-model:open="confirmOpen">
    <DropdownMenu>
      <DropdownMenuTrigger as-child>
        <Button variant="ghost" size="icon" class="h-7 w-7 p-0">
          <MoreHorizontal class="h-4 w-4" />
          <span class="sr-only">إجراءات الصف</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuLabel class="text-xs">إجراءات</DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          v-for="action in actions"
          :key="action.label"
          :class="['text-xs cursor-pointer', action.destructive ? 'text-destructive focus:text-destructive' : '']"
          @click="handleClick(action)"
        >
          <component :is="action.icon" v-if="action.icon" class="me-2 h-3.5 w-3.5" />
          {{ action.label }}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>

    <AlertDialogContent dir="rtl">
      <AlertDialogHeader>
        <AlertDialogTitle>{{ pendingAction?.confirmTitle ?? 'تأكيد الإجراء' }}</AlertDialogTitle>
        <AlertDialogDescription>
          {{ pendingAction?.confirmDescription ?? 'هل أنت متأكد من تنفيذ هذا الإجراء؟ لا يمكن التراجع عنه.' }}
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>إلغاء</AlertDialogCancel>
        <AlertDialogAction
          class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
          @click="confirmDestructive"
        >
          تأكيد
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
