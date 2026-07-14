<!-- app/pages/workflows/new-request/[versionId].vue -->
<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { extractHttpStatus } from '@/utils/apiErrors'
import { useEngineFormSchema } from '@/composables/useEngineFormSchema'
import EngineRequestWizard from '@/components/workflow/EngineRequestWizard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Skeleton } from '@/components/ui/skeleton'
import ErrorState from '@/components/shared/ErrorState.vue'
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogCancel,
  AlertDialogAction,
} from '@/components/ui/alert-dialog'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'requests' })

const route = useRoute()
const router = useRouter()
const workflowVersionId = computed(() => Number(route.params.versionId))

const { fieldGroups, fetchInitialSchema } = useEngineFormSchema()
const loading = ref(true)
const loadErrorCode = ref<number | null>(null)

async function load() {
  loading.value = true
  loadErrorCode.value = null
  try {
    await fetchInitialSchema(workflowVersionId.value)
    if (fieldGroups.value.length === 0) {
      // fetchInitialSchema swallows its own errors into a message string,
      // not a rethrow — an empty result with no groups at all after a
      // genuine failure still needs to surface as an error, not a blank
      // wizard with nothing to fill in.
      loadErrorCode.value = 500
    }
  } catch (cause: unknown) {
    loadErrorCode.value = extractHttpStatus(cause) ?? 500
  } finally {
    loading.value = false
  }
}

onMounted(load)

// Native browser guard: warns on hard refresh or tab close while the wizard
// has data that would be lost, complementing the in-app onBeforeRouteLeave
// guard below (which only covers in-SPA navigation).
function handleBeforeUnload(event: BeforeUnloadEvent) {
  if (!hasUnsavedWizardChanges()) return
  event.preventDefault()
}

onMounted(() => window.addEventListener('beforeunload', handleBeforeUnload))
onUnmounted(() => window.removeEventListener('beforeunload', handleBeforeUnload))

const wizardRef = ref<InstanceType<typeof EngineRequestWizard> | null>(null)
const leaveDialogOpen = ref(false)
let pendingLeave: (() => void) | null = null

function hasUnsavedWizardChanges(): boolean {
  return wizardRef.value?.hasUnsavedChanges === true
}

onBeforeRouteLeave((_to, _from, next) => {
  if (!hasUnsavedWizardChanges()) {
    next()
    return
  }
  leaveDialogOpen.value = true
  pendingLeave = () => next()
})

function confirmLeave() {
  leaveDialogOpen.value = false
  pendingLeave?.()
  pendingLeave = null
}

function cancelLeave() {
  leaveDialogOpen.value = false
  pendingLeave = null
}

async function onSubmitted(requestId: number) {
  // Bypass the leave-guard for the deliberate post-submit navigation — the
  // wizard's data is no longer "unsaved," it's the request that now exists.
  pendingLeave = null
  await router.replace(`/workflows/instances/${requestId}`)
}
</script>

<template>
  <div class="flex flex-col gap-6 p-6 py-2" dir="rtl">
    <PageHeader
      title="طلب تمويل جديد"
      subtitle="أدخل بيانات الطلب خطوة بخطوة، ثم راجع وأرسل."
      :breadcrumbs="[
        { label: 'الرئيسية', to: '/dashboard' },
        { label: 'طلبات التمويل', to: '/workflows' },
        { label: 'طلب جديد', to: '/workflows/new' },
        { label: 'إدخال البيانات' },
      ]"
    />

    <div v-if="loading">
      <Skeleton class="mb-4 h-8 w-64" />
      <Skeleton class="h-64 w-full" />
    </div>

    <ErrorState
      v-else-if="loadErrorCode !== null"
      :code="loadErrorCode"
      :actions="[
        { label: 'إعادة المحاولة', variant: 'default', onClick: load },
        {
          label: 'العودة إلى اختيار مسار العمل',
          variant: 'outline',
          onClick: () => router.push('/workflows/new'),
        },
      ]"
    />

    <EngineRequestWizard
      v-else
      ref="wizardRef"
      :workflow-version-id="workflowVersionId"
      :field-groups="fieldGroups"
      @submitted="onSubmitted"
    />

    <AlertDialog v-model:open="leaveDialogOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>مغادرة الصفحة دون حفظ</AlertDialogTitle>
          <AlertDialogDescription>
            لديك بيانات لم يتم إرسالها بعد. سيتم فقدانها إذا غادرت الصفحة الآن دون إتمام الإرسال.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter class="gap-2 sm:justify-start">
          <AlertDialogCancel @click="cancelLeave">البقاء في الصفحة</AlertDialogCancel>
          <AlertDialogAction @click="confirmLeave">مغادرة دون حفظ</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
