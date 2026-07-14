<!-- app/pages/workflows/instances/[id].vue -->
<script setup lang="ts">
import { computed, onMounted, ref, toRef } from 'vue'
import { toast } from 'vue-sonner'
import { extractApiErrorCode, extractApiErrorMessage, extractHttpStatus } from '@/utils/apiErrors'
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
import EngineFxConfirmationPanel from '@/components/workflow/EngineFxConfirmationPanel.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { useEngineProgress } from '@/composables/useEngineProgress'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert'
import { Skeleton } from '@/components/ui/skeleton'
import ErrorState from '@/components/shared/ErrorState.vue'
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
const { claim, isHeldByMe, heldByOther, claimedBy, claimLost, markClaimLost, resetClaimLost } =
  useEngineClaim(requestId, currentUserId)

const formData = ref<Record<string, unknown>>({})
const comment = ref('')
const actionBusy = ref(false)

// UI-RBAC-002: a failed loadInstance (403 for an out-of-scope request, 404 for a
// missing one, 429, or a server error) previously left store.current null with
// no error branch, so the page rendered a blank shell. Capture the HTTP status
// so the template can show ErrorState with the right code + a retry action.
const loadErrorCode = ref<number | null>(null)

async function load() {
  loadErrorCode.value = null
  try {
    await store.loadInstance(requestId.value)
    await fetchSchema(requestId.value)
    formData.value = store.current?.data ?? {}
    claimedBy.value = store.current?.claimed_by ?? null
  } catch (cause: unknown) {
    loadErrorCode.value = extractHttpStatus(cause) ?? 500
  }
}

onMounted(load)

// Whether the signed-in user may execute the current stage (server-derived from
// stage permissions). Drives the action panel and edit mode; a viewer who only
// holds VIEW access never sees actions or an editable form.
const canExecute = computed(() => store.current?.can_execute === true)
const isViewOnly = computed(() => !canExecute.value)

// Workflow progress for the current-stage banner.
const graphRef = toRef(store, 'graph')
const currentRef = toRef(store, 'current')
const { percent, currentIndex, total } = useEngineProgress(graphRef, currentRef)

// "مسار العمل" (workflow name + version) shown under the page title, so
// reviewers can tell which workflow definition/version produced this request.
const workflowLabel = computed(() => {
  const v = store.current?.workflow_version
  return v?.definition ? `${v.definition.name} v${v.version_number}` : null
})

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
const canAct = computed(
  () => canExecute.value && !claimLost.value && (!stageRequiresClaim.value || isHeldByMe.value),
)
const claimRequiredButNotHeld = computed(
  () => canExecute.value && stageRequiresClaim.value && !isHeldByMe.value,
)

async function startReview() {
  resetClaimLost()
  try {
    await claim()
  } catch {
    toast.error('تعذّرت المتابعة — قد يكون الطلب مطالباً من مستخدم آخر')
  }
}

function returnToQueue() {
  router.push('/workflows')
}

function onClaimLost(code: string) {
  markClaimLost(code)
  toast.error('فُقدت مطالبة هذا الطلب أو انتهت صلاحيتها')
}

async function runAction(transitionId: number, requiresComment: boolean) {
  if (requiresComment && !comment.value.trim()) {
    return
  }
  // The view page shows the request data read-only; acting on a stage submits
  // the existing data unchanged with an optional comment. Field edits happen
  // during creation (see /workflows/new-request/[versionId]), not here.
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
  } catch (err) {
    const code = extractApiErrorCode(err)
    if (code === 'CLAIM_NOT_HELD') {
      onClaimLost('CLAIM_NOT_HELD')
    } else if (code === 'REQUEST_STALE') {
      // 409: another user changed the request first — reload so the
      // acting user sees current state instead of retrying blind.
      toast.error('تم تعديل الطلب من قِبل مستخدم آخر. تم تحديث البيانات.')
      await load()
    } else if (code === 'TRANSITION_NOT_AVAILABLE') {
      toast.error('هذا الإجراء لم يعد متاحاً في المرحلة الحالية للطلب.')
      await load()
    } else if (code === 'STAGE_FIELDS_INVALID' || extractHttpStatus(err) === 422) {
      toast.error(extractApiErrorMessage(err, 'تعذّر التحقق من بيانات الطلب.'))
    } else if (extractHttpStatus(err) === 429) {
      toast.error('عدد كبير من الطلبات خلال وقت قصير. الرجاء الانتظار قليلاً والمحاولة مرة أخرى.')
    } else {
      // Phase E8: every other transition failure (500, unknown codes) was
      // previously silently swallowed — the button just stopped spinning
      // with no feedback at all.
      toast.error('تعذّر تنفيذ الإجراء. حاول مرة أخرى.')
    }
  } finally {
    actionBusy.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-6 p-6 py-2" dir="rtl">
    <div v-if="store.loading && !store.current">
      <Skeleton class="mb-4 h-8 w-64" />
      <Skeleton class="h-48 w-full" />
    </div>

    <!-- UI-RBAC-002: the request could not be loaded (out-of-scope 403, missing
         404, rate-limited 429, or server error). Show a coded error state with a
         retry instead of a blank shell. -->
    <ErrorState
      v-else-if="loadErrorCode !== null && !store.current"
      :code="loadErrorCode === 429 ? '429' : loadErrorCode"
      :title="loadErrorCode === 429 ? 'تم إيقاف التحميل مؤقتاً' : undefined"
      :description="
        loadErrorCode === 429
          ? 'تم إيقاف التحميل مؤقتاً بسبب كثرة الطلبات. حاول مرة أخرى بعد قليل.'
          : undefined
      "
      :actions="[
        { label: 'إعادة المحاولة', variant: 'default', onClick: load },
        {
          label: 'العودة إلى الطلبات',
          variant: 'outline',
          onClick: () => router.push('/workflows'),
        },
      ]"
    />

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
      <p v-if="workflowLabel" class="text-muted-foreground -mt-4 mb-2 text-sm">
        مسار العمل: {{ workflowLabel }}
      </p>

      <EngineStageBanner
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

      <ClaimBanner v-if="heldByOther" :holder-name="claimHolderName ?? 'مستخدم آخر'" />

      <Alert v-if="claimLost" variant="destructive" role="alert">
        <AlertTriangle class="h-4 w-4" />
        <AlertTitle>فُقدت مطالبة الطلب</AlertTitle>
        <AlertDescription>
          انتهت صلاحية مطالبتك أو انتقل الطلب إلى مرحلة أخرى. لا يمكنك تعديل الطلب أو تنفيذ إجراءات
          عليه حالياً.
        </AlertDescription>
        <div class="mt-3 flex flex-wrap gap-2">
          <Button size="sm" variant="outline" @click="returnToQueue">العودة إلى الطابور</Button>
          <Button v-if="stageRequiresClaim && !heldByOther" size="sm" @click="startReview">
            محاولة المتابعة مجدداً
          </Button>
        </div>
      </Alert>

      <!-- View / act mode: two-column detail. -->
      <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="flex min-w-0 flex-col gap-4">
          <Tabs default-value="data" dir="rtl">
            <TabsList>
              <TabsTrigger value="data">بيانات الطلب</TabsTrigger>
              <TabsTrigger value="history">السجل</TabsTrigger>
            </TabsList>

            <TabsContent value="data" class="mt-4">
              <Card class="border-0 shadow">
                <CardContent>
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
                <CardContent>
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

          <EngineFxConfirmationPanel
            v-if="store.current.fx_panel?.visible"
            :request-id="requestId"
            :capabilities="store.current.fx_panel"
            :declaration="store.current.customs_declaration ?? null"
            @refresh="load"
          />
        </aside>
      </div>
    </template>
  </div>
</template>
