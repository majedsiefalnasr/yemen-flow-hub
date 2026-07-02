<!-- app/pages/workflows/instances/[id].vue -->
<script setup lang="ts">
import { computed, ref } from 'vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useEngineFormSchema } from '@/composables/useEngineFormSchema'
import { useEngineRequestActions } from '@/composables/useEngineRequestActions'
import { useEngineClaim } from '@/composables/useEngineClaim'
import { useAuthStore } from '@/stores/auth.store'
import DynamicForm from '@/components/workflow/DynamicForm.vue'
import ClaimBanner from '@/components/workflow/ClaimBanner.vue'
import EngineStageStepper from '@/components/workflow/EngineStageStepper.vue'
import EngineRequestSummary from '@/components/workflow/EngineRequestSummary.vue'
import EngineTimeline from '@/components/workflow/EngineTimeline.vue'
import EngineDocumentsPanel from '@/components/workflow/EngineDocumentsPanel.vue'
import EngineActionsRail from '@/components/workflow/EngineActionsRail.vue'
import EngineRequestWizard from '@/components/workflow/EngineRequestWizard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert'
import { Skeleton } from '@/components/ui/skeleton'
import { AlertTriangle } from 'lucide-vue-next'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'requests' })

const route = useRoute()
const router = useRouter()
const requestId = computed(() => Number(route.params.id))

const store = useEngineRequestsStore()
const { fieldGroups, fetchSchema } = useEngineFormSchema()
const { executeAction, conflictError } = useEngineRequestActions()

const auth = useAuthStore()
const currentUserId = computed(() => auth.user?.id ?? null)
const { claim, isHeldByMe, heldByOther, claimedBy } = useEngineClaim(requestId, currentUserId)

const formRef = ref<InstanceType<typeof DynamicForm> | null>(null)
const formData = ref<Record<string, unknown>>({})
const comment = ref('')
const actionBusy = ref(false)

async function load() {
  await store.loadInstance(requestId.value)
  await fetchSchema(requestId.value)
  formData.value = store.current?.data ?? {}
  claimedBy.value = store.current?.claimed_by ?? null
}

onMounted(load)

const wizardMode = computed(
  () =>
    route.query.mode === 'wizard' &&
    store.current?.current_stage?.is_initial === true &&
    store.current?.created_by === auth.user?.id,
)

const availableActions = computed(() => {
  if (!store.current?.current_stage || !store.graph) return []
  return store.graph.edges.filter((edge) => edge.from_stage_id === store.current!.current_stage!.id)
})

const stageRequiresClaim = computed(() => store.current?.current_stage?.requires_claim === true)
const isUnclaimed = computed(() => claimedBy.value === null)
const showClaimButton = computed(
  () => stageRequiresClaim.value && isUnclaimed.value && !heldByOther.value,
)
const claimHolderName = computed(() => store.current?.claimed_by_user?.name ?? null)
const canAct = computed(() => !stageRequiresClaim.value || isHeldByMe.value)
const claimRequiredButNotHeld = computed(() => stageRequiresClaim.value && !isHeldByMe.value)

async function startReview() {
  await claim()
}

async function runAction(transitionId: number, requiresComment: boolean) {
  if (requiresComment && !comment.value.trim()) return
  const validation = await formRef.value?.validate()
  if (validation && !validation.valid) return
  actionBusy.value = true
  try {
    await executeAction(
      requestId.value,
      transitionId,
      comment.value || null,
      validation?.values ?? formData.value,
      store.current!.version,
    )
    comment.value = ''
    await load()
  } catch {
    // conflictError / fieldErrors already surfaced by the composable
  } finally {
    actionBusy.value = false
  }
}

async function onWizardSubmitted() {
  // Drop wizard mode and reload into the view/act layout.
  await router.replace({ path: route.path })
  await load()
}

async function onUpload(file: File) {
  await store.uploadDocument(requestId.value, file, null)
}

async function onRemove(documentId: number) {
  await store.removeDocument(requestId.value, documentId)
}
</script>

<template>
  <div class="flex flex-col gap-6 p-6" dir="rtl">
    <div v-if="store.loading && !store.current">
      <Skeleton class="mb-4 h-8 w-64" />
      <Skeleton class="h-48 w-full" />
    </div>

    <template v-else-if="store.current">
      <PageHeader
        :title="store.current.reference"
        subtitle="تفاصيل طلب التمويل والإجراءات المتاحة في المرحلة الحالية"
        :breadcrumbs="[
          { label: 'الرئيسية', to: '/dashboard' },
          { label: 'طلبات التمويل', to: '/workflows' },
          { label: store.current.reference },
        ]"
      />

      <EngineStageStepper
        :graph="store.graph"
        :current-stage-id="store.current.current_stage?.id ?? null"
        :history="store.history"
      />

      <EngineRequestSummary :request="store.current" />

      <Alert v-if="conflictError" variant="destructive" role="alert">
        <AlertTriangle class="h-4 w-4" />
        <AlertTitle>تعارض في التحديث</AlertTitle>
        <AlertDescription
          >تم تحديث الطلب من مستخدم آخر. تم تحديث البيانات المعروضة.</AlertDescription
        >
      </Alert>

      <ClaimBanner v-if="heldByOther" :holder-name="claimHolderName ?? 'مراجع آخر'" />

      <!-- Wizard mode: draft creator on the initial stage collects inputs step by step. -->
      <EngineRequestWizard
        v-if="wizardMode"
        :request-id="requestId"
        :field-groups="fieldGroups"
        :version="store.current.version"
        :initial-data="formData"
        @submitted="onWizardSubmitted"
      />

      <!-- View / act mode: two-column detail. -->
      <div v-else class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="min-w-0">
          <Tabs default-value="data" dir="rtl">
            <TabsList>
              <TabsTrigger value="data">البيانات</TabsTrigger>
              <TabsTrigger value="documents">المرفقات</TabsTrigger>
              <TabsTrigger value="history">السجل</TabsTrigger>
            </TabsList>

            <TabsContent value="data" class="mt-4">
              <Card class="border-0 shadow">
                <CardContent class="p-4">
                  <DynamicForm
                    ref="formRef"
                    v-model="formData"
                    :field-groups="fieldGroups"
                    :mode="canAct ? 'edit' : 'readonly'"
                    :request-id="requestId"
                  />
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="documents" class="mt-4">
              <Card class="border-0 shadow">
                <CardContent class="p-4">
                  <EngineDocumentsPanel
                    :documents="store.documents"
                    :request-id="requestId"
                    :can-manage="canAct"
                    @upload="onUpload"
                    @remove="onRemove"
                  />
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="history" class="mt-4">
              <Card class="border-0 shadow">
                <CardContent class="p-4">
                  <EngineTimeline :entries="store.history" />
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>

        <aside>
          <EngineActionsRail
            v-model:comment="comment"
            :available-actions="availableActions"
            :can-act="canAct"
            :claim-required-but-not-held="claimRequiredButNotHeld"
            :show-claim-button="showClaimButton"
            :busy="actionBusy"
            @run="runAction"
            @claim="startReview"
          />
        </aside>
      </div>
    </template>
  </div>
</template>
