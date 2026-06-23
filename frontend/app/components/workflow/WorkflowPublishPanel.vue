<script setup lang="ts">
import { ref } from 'vue'
import { toast } from 'vue-sonner'
import { AlertCircle, CheckCircle2 } from 'lucide-vue-next'
import type { WorkflowValidationError, WorkflowVersion } from '@/types/models'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { useWorkflows } from '@/composables/useWorkflows'

const props = defineProps<{ version: WorkflowVersion }>()
const emit = defineEmits<{ published: [] }>()

const { validateVersion, publishVersion } = useWorkflows()

const errors = ref<WorkflowValidationError[] | null>(null)
const validating = ref(false)
const publishing = ref(false)
const confirmOpen = ref(false)

const isDraft = props.version.state === 'DRAFT'

async function runValidate() {
  validating.value = true
  try {
    errors.value = await validateVersion(props.version.id)
    if (errors.value.length === 0) {
      toast.success('النسخة صالحة للنشر')
    }
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر التحقق من النسخة'))
  } finally {
    validating.value = false
  }
}

async function confirmPublish() {
  publishing.value = true
  try {
    await publishVersion(props.version)
    toast.success('تم نشر النسخة')
    emit('published')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر نشر النسخة'))
  } finally {
    publishing.value = false
    confirmOpen.value = false
  }
}
</script>

<template>
  <div v-if="isDraft" class="space-y-3">
    <div class="flex items-center gap-2">
      <Button variant="outline" size="sm" :disabled="validating" @click="runValidate">
        التحقق من الصحة
      </Button>
      <ScreenGuard screen="workflow_designer" capability="UPDATE">
        <Button
          size="sm"
          :disabled="errors === null || errors.length > 0 || publishing"
          @click="confirmOpen = true"
        >
          نشر النسخة
        </Button>
      </ScreenGuard>
    </div>

    <Alert v-if="errors !== null && errors.length === 0">
      <CheckCircle2 class="h-4 w-4 text-[var(--severity-green)]" />
      <AlertTitle>النسخة صالحة</AlertTitle>
      <AlertDescription>لا توجد أخطاء؛ يمكنك نشر النسخة.</AlertDescription>
    </Alert>

    <Alert v-else-if="errors !== null && errors.length > 0" variant="destructive" role="alert">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>{{ errors.length }} أخطاء يجب إصلاحها قبل النشر</AlertTitle>
      <AlertDescription>
        <ul class="mt-1 list-disc space-y-0.5 ps-4">
          <li v-for="(item, index) in errors" :key="`${item.code}-${index}`">
            {{ item.message }}
          </li>
        </ul>
      </AlertDescription>
    </Alert>

    <AlertDialog v-model:open="confirmOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>تأكيد نشر النسخة</AlertDialogTitle>
          <AlertDialogDescription>
            سيتم تجميد هذه النسخة كنسخة منشورة فعّالة، وأرشفة النسخة المنشورة السابقة. لا يمكن
            التراجع — التعديل يتطلب استنساخ نسخة جديدة.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>إلغاء</AlertDialogCancel>
          <AlertDialogAction :disabled="publishing" @click="confirmPublish"
            >تأكيد النشر</AlertDialogAction
          >
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
