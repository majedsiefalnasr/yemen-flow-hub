<!-- app/pages/workflows/instances/[id].vue -->
<script setup lang="ts">
import { computed, ref, toRef } from 'vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useEngineFormSchema } from '@/composables/useEngineFormSchema'
import { useEngineRequestActions } from '@/composables/useEngineRequestActions'
import { useEngineClaim } from '@/composables/useEngineClaim'
import { useAuthStore } from '@/stores/auth.store'
import ClaimBanner from '@/components/workflow/ClaimBanner.vue'
import EngineStageBanner from '@/components/workflow/EngineStageBanner.vue'
import EngineRequestDataTabs from '@/components/workflow/EngineRequestDataTabs.vue'
import EngineOrgProcessRail from '@/components/workflow/EngineOrgProcessRail.vue'
import EngineQuickInfo from '@/components/workflow/EngineQuickInfo.vue'
import EngineTimeline from '@/components/workflow/EngineTimeline.vue'
import EngineActionsRail from '@/components/workflow/EngineActionsRail.vue'
import EngineRequestWizard from '@/components/workflow/EngineRequestWizard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { useEngineProgress } from '@/composables/useEngineProgress'
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

// Whether the signed-in user may execute the current stage (server-derived from
// stage permissions). Drives the action panel and edit mode; a viewer who only
// holds VIEW access never sees actions or an editable form.
const canExecute = computed(() => store.current?.can_execute === true)
const isViewOnly = computed(() => !canExecute.value)

// Workflow progress for the current-stage banner.
const graphRef = toRef(store, 'graph')
const currentRef = toRef(store, 'current')
const { percent, currentIndex, total } = useEngineProgress(graphRef, currentRef)

const availableActions = computed(() => {
  if (!store.current?.current_stage || !store.graph) return []
  // Non-executors never see stage actions, even if the graph has outgoing edges.
  if (!canExecute.value) return []
  return store.graph.edges.filter((edge) => edge.from_stage_id === store.current!.current_stage!.id)
})

const stageRequiresClaim = computed(() => store.current?.current_stage?.requires_claim === true)
const isUnclaimed = computed(() => claimedBy.value === null)
const showClaimButton = computed(
  () => canExecute.value && stageRequiresClaim.value && isUnclaimed.value && !heldByOther.value,
)
const claimHolderName = computed(() => store.current?.claimed_by_user?.name ?? null)
const canAct = computed(() => canExecute.value && (!stageRequiresClaim.value || isHeldByMe.value))
const claimRequiredButNotHeld = computed(
  () => canExecute.value && stageRequiresClaim.value && !isHeldByMe.value,
)

async function startReview() {
  await claim()
}

async function runAction(transitionId: number, requiresComment: boolean) {
  if (requiresComment && !comment.value.trim()) return
  // The view page shows the request data read-only; acting on a stage submits the
  // existing data unchanged with an optional comment. Field edits happen in the
  // creator wizard, not here.
  actionBusy.value = true
  try {
    await executeAction(
      requestId.value,
      transitionId,
      comment.value || null,
      formData.value,
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
</script>

<template>
  <div class="flex flex-col gap-6 py-2 p-6" dir="rtl">
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

      <EngineStageBanner
        v-if="!wizardMode"
        :request="store.current"
        :percent="percent"
        :current-index="currentIndex"
        :total="total"
        :can-execute="canExecute"
      />

      <Alert v-if="store.duplicateWarnings.length" variant="destructive" role="alert">
        <AlertTriangle class="h-4 w-4" />
        <AlertTitle>تنبيه: فاتورة مكرّرة محتملة</AlertTitle>
        <AlertDescription>
          رقم الفاتورة يطابق طلبات نشطة أخرى:
          {{ store.duplicateWarnings[0]?.duplicates.map((d) => d.reference).join('، ') }}.
        </AlertDescription>
      </Alert>

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
        :documents="store.documents"
        @submitted="onWizardSubmitted"
      />

      <!-- View / act mode: two-column detail. -->
      <div v-else class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="flex min-w-0 flex-col gap-4">
          <Tabs default-value="data" dir="rtl">
            <TabsList>
              <TabsTrigger value="data">بيانات الطلب</TabsTrigger>
              <TabsTrigger value="history">السجل</TabsTrigger>
            </TabsList>

            <TabsContent value="data" class="mt-4">
              <Card class="border-0 shadow">
                <CardContent class="p-4">
                  <EngineRequestDataTabs
                    :field-groups="fieldGroups"
                    :data="store.current.data"
                    :documents="store.documents"
                    :request-id="requestId"
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

        <aside class="flex flex-col gap-4">
          <EngineActionsRail
            v-model:comment="comment"
            :available-actions="availableActions"
            :can-act="canAct"
            :claim-required-but-not-held="claimRequiredButNotHeld"
            :show-claim-button="showClaimButton"
            :view-only="isViewOnly"
            :busy="actionBusy"
            @run="runAction"
            @claim="startReview"
          />

          <EngineOrgProcessRail
            :graph="store.graph"
            :current-stage-id="store.current.current_stage?.id ?? null"
            :history="store.history"
          />

          <EngineQuickInfo :request="store.current" />
        </aside>
      </div>
    </template>
  </div>
</template>
