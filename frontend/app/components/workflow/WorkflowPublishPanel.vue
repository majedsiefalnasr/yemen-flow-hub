<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import { AlertCircle, CheckCircle2, ShieldCheck } from 'lucide-vue-next'
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
import { Spinner } from '@/components/ui/spinner'
import { useWorkflows } from '@/composables/useWorkflows'

const props = defineProps<{ version: WorkflowVersion }>()
const emit = defineEmits<{ published: [] }>()

const { validateVersion, publishVersion } = useWorkflows()

const errors = ref<WorkflowValidationError[] | null>(null)
const validating = ref(false)
const publishing = ref(false)
const confirmOpen = ref(false)
const versionEpoch = ref(0)

const isDraft = computed(() => props.version.state === 'DRAFT' && props.version.is_editable)

watch(
  () => [props.version.id, props.version.state, props.version.is_editable] as const,
  () => {
    versionEpoch.value += 1
    errors.value = null
    validating.value = false
    publishing.value = false
    confirmOpen.value = false
  },
)

async function runValidate() {
  const epoch = versionEpoch.value
  const versionId = props.version.id
  validating.value = true
  try {
    const validationErrors = await validateVersion(versionId)
    if (epoch !== versionEpoch.value) return
    errors.value = validationErrors
    if (validationErrors.length === 0) {
      toast.success('النسخة صالحة للنشر')
    }
  } catch (cause) {
    if (epoch === versionEpoch.value) {
      toast.error(extractApiErrorMessage(cause, 'تعذّر التحقق من النسخة'))
    }
  } finally {
    if (epoch === versionEpoch.value) validating.value = false
  }
}

async function confirmPublish() {
  const epoch = versionEpoch.value
  const version = props.version
  publishing.value = true
  try {
    await publishVersion(version)
    if (epoch !== versionEpoch.value) return
    toast.success('تم نشر النسخة')
    emit('published')
  } catch (cause) {
    if (epoch === versionEpoch.value) {
      toast.error(extractApiErrorMessage(cause, 'تعذّر نشر النسخة'))
    }
  } finally {
    if (epoch === versionEpoch.value) {
      publishing.value = false
      confirmOpen.value = false
    }
  }
}
</script>

<template>
  <div v-if="isDraft" class="border-border bg-muted/30 space-y-3 rounded-lg border p-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <ShieldCheck class="text-muted-foreground h-4 w-4" aria-hidden="true" />
        <span class="text-sm font-medium">جاهزية النشر</span>
      </div>
      <div class="flex items-center gap-2">
        <Button variant="outline" size="sm" :disabled="validating" @click="runValidate">
          <Spinner v-if="validating" class="h-3.5 w-3.5" />
          التحقق من الصحة
        </Button>
        <ScreenGuard screen="workflow_designer" capability="MANAGE">
          <Button
            size="sm"
            :disabled="errors === null || errors.length > 0 || publishing"
            @click="confirmOpen = true"
          >
            نشر النسخة
          </Button>
        </ScreenGuard>
      </div>
    </div>

    <p v-if="errors === null" class="text-muted-foreground text-xs">
      شغّل «التحقق من الصحة» للتأكد من اكتمال المراحل والانتقالات قبل نشر النسخة.
    </p>

    <Alert v-else-if="errors.length === 0" class="border-0 bg-[var(--severity-green)]/5">
      <CheckCircle2 class="h-4 w-4 text-[var(--severity-green)]" />
      <AlertTitle>النسخة جاهزة للنشر</AlertTitle>
      <AlertDescription
        >اجتازت النسخة جميع الفحوصات ولا توجد أخطاء. يمكنك نشرها الآن.</AlertDescription
      >
    </Alert>

    <Alert v-else variant="destructive" role="alert" class="border-0">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>
        {{ errors.length === 1 ? 'خطأ واحد يمنع النشر' : `${errors.length} أخطاء تمنع النشر` }}
      </AlertTitle>
      <AlertDescription>
        <p class="mb-1.5">صحّح المشكلات التالية ثم أعد التحقق من الصحة:</p>
        <ul class="space-y-1">
          <li
            v-for="(item, index) in errors"
            :key="`${item.code}-${index}`"
            class="flex items-start gap-2"
          >
            <span
              class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--severity-red)]"
              aria-hidden="true"
            />
            <span class="min-w-0">{{ item.message }}</span>
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
