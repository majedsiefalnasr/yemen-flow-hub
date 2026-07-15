<!-- app/pages/workflows/instances/[id].vue -->
<script setup lang="ts">
import { computed, onMounted, ref, toRaw, toRef } from 'vue'
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
import DynamicForm from '@/components/workflow/DynamicForm.vue'
import EngineFieldDocumentsGroup from '@/components/workflow/EngineFieldDocumentsGroup.vue'
import EngineDocumentsPanel from '@/components/workflow/EngineDocumentsPanel.vue'
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

// One DynamicForm instance per group that contains non-FILE fields in edit
// mode. The ref index matches that group's position in editableGroupSections;
// FILE-only groups leave a sparse slot that runAction safely skips.
const dynamicFormRefs = ref<Array<InstanceType<typeof DynamicForm> | undefined>>([])
function setDynamicFormRef(el: unknown, index: number) {
  if (el) {
    dynamicFormRefs.value[index] = el as InstanceType<typeof DynamicForm>
  } else {
    dynamicFormRefs.value[index] = undefined
  }
}

// Tracks which (request version, stage id) formData currently reflects, so a
// reload that lands on a different version or stage (another user's edit
// landed first, or this page's own successful action moved the stage) resets
// formData from the fresh store.current.data instead of silently keeping
// stale edited values bound to fields that may no longer exist or be
// editable. A structuredClone (not a shallow spread) because field values can
// themselves be arrays (FILE fields hold number[] document ids). store.current
// is Pinia reactive state, so it (and its nested `data`) is a Vue Proxy —
// structuredClone throws DataCloneError on a Proxy, hence toRaw() first.
const loadedFormKey = ref<string | null>(null)
function resetFormState() {
  if (!store.current) return
  const key = `${store.current.version}:${store.current.current_stage?.id ?? 'none'}`
  if (loadedFormKey.value === key) return
  formData.value = structuredClone(toRaw(store.current.data ?? {}))
  loadedFormKey.value = key
  dynamicFormRefs.value = []
}

// UI-RBAC-002: a failed loadInstance (403 for an out-of-scope request, 404 for a
// missing one, 429, or a server error) previously left store.current null with
// no error branch, so the page rendered a blank shell. Capture the HTTP status
// so the template can show ErrorState with the right code + a retry action.
const loadErrorCode = ref<number | null>(null)

async function load() {
  loadErrorCode.value = null
  try {
    await store.loadInstance(requestId.value)
    resetFormState()
    await fetchSchema(requestId.value)
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

// Edit mode is derived purely from Designer-configured StageFieldRule data
// (fieldGroups, already fetched by fetchSchema) plus canAct — never a stage
// code or is_initial check. Any stage the Designer marks with at least one
// editable+visible field becomes editable here, whether that's the initial
// stage, a "needs correction" return stage, or any future stage.
const hasEditableFields = computed(() =>
  fieldGroups.value.some((group) =>
    group.fields.some((field) => field.is_visible && field.is_editable),
  ),
)

// canAct already folds in canExecute, claimLost, and claim ownership when the
// stage requires a claim (see computed above) — document mutations and
// workflow actions both require EXECUTE + (if applicable) claim ownership,
// not just a field rule saying "this field is editable in principle".
const isEditMode = computed(() => canAct.value && hasEditableFields.value)

const orderedFieldGroups = computed(() =>
  [...fieldGroups.value].sort((a, b) => a.sort_order - b.sort_order),
)

// Designer groups may mix ordinary and FILE fields. Keep one tab per original
// group, while sending each renderer only the field type it owns: DynamicForm
// must never receive FILE fields, and the document wrapper must never receive
// ordinary form controls.
const editableGroupSections = computed(() =>
  orderedFieldGroups.value.map((group) => {
    const nonFileFields = group.fields.filter((field) => field.type !== 'FILE')
    const fileFields = group.fields.filter((field) => field.type === 'FILE')
    return {
      group,
      nonFileGroup: nonFileFields.length ? { ...group, fields: nonFileFields } : null,
      fileGroup: fileFields.length ? { ...group, fields: fileFields } : null,
    }
  }),
)

// General documents (field_id=null) and documents whose field no longer
// exists in the complete current schema are rendered once at page level.
// Including hidden fields in knownFieldIds prevents their documents from being
// mislabeled as stale simply because their field panel is not visible.
const otherDocuments = computed(() => {
  const knownFieldIds = new Set(
    fieldGroups.value.flatMap((group) => group.fields.map((field) => field.id)),
  )
  return store.documents.filter(
    (document) => document.field_id === null || !knownFieldIds.has(document.field_id),
  )
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

// Document mutations run independently of workflow actions — a document can
// be added/removed at any time while in edit mode, not just when submitting
// a transition. Both reuse the store's existing uploadDocument/removeDocument
// actions, which already call the composable, refetch, and reassign
// store.documents.
async function onDocumentUpload(fieldId: number, file: File) {
  try {
    // Use the created document returned directly by the store action, not a
    // post-refetch lookup by field_id — a field can hold multiple documents
    // (EngineFieldDocumentsGroup filters by field_id into a plural list), so
    // finding "the" document for a field_id after a second/later upload would
    // pick an arbitrary existing match instead of the one just created.
    const uploaded = await store.uploadDocument(requestId.value, file, fieldId)
    const field = fieldGroups.value.flatMap((g) => g.fields).find((f) => f.id === fieldId)
    if (field) {
      const current = (formData.value[field.key] as number[] | undefined) ?? []
      if (!current.includes(uploaded.id)) {
        formData.value = { ...formData.value, [field.key]: [...current, uploaded.id] }
      }
    }
  } catch (err) {
    toast.error(extractApiErrorMessage(err, 'تعذّر رفع المستند.'))
  }
}

async function onDocumentRemove(documentId: number) {
  const removedDoc = store.documents.find((d) => d.id === documentId)
  try {
    await store.removeDocument(requestId.value, documentId)
    if (removedDoc?.field_id !== null && removedDoc?.field_id !== undefined) {
      const field = fieldGroups.value
        .flatMap((g) => g.fields)
        .find((f) => f.id === removedDoc.field_id)
      if (field) {
        const current = (formData.value[field.key] as number[] | undefined) ?? []
        formData.value = {
          ...formData.value,
          [field.key]: current.filter((id) => id !== documentId),
        }
      }
    }
  } catch (err) {
    toast.error(extractApiErrorMessage(err, 'تعذّر حذف المستند.'))
  }
}

async function runAction(transitionId: number, requiresComment: boolean) {
  if (requiresComment && !comment.value.trim()) {
    return
  }
  // In edit mode, every mounted DynamicForm must independently validate before
  // any transition executes. Each form only owns the fields in its own group,
  // so its returned `values` may hold stale/default data for fields that
  // belong to a DIFFERENT group's form — never merge `values` across forms
  // into formData here. formData is already the single source of truth (kept
  // current by each DynamicForm's v-model + the document handlers in Task 3);
  // validate() is called purely to surface field-level errors and block
  // submission, its returned `values` are discarded.
  if (isEditMode.value) {
    for (const form of dynamicFormRefs.value) {
      if (!form) continue
      const { valid } = await form.validate()
      if (!valid) return
    }
  }

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
    actionBusy.value = false
    return
  }

  // The transition already succeeded above; this reload only refreshes the
  // page's graph/history/documents/schema. A stage transition routinely moves
  // the request into a stage the acting user has no VIEW grant on (e.g. a bank
  // reviewer approving into CBY's internal queue), so load() legitimately
  // fails (via loadErrorCode, it never throws) here even though the action
  // worked — that must never be reported as a failed action. Send the user
  // back to their queue instead.
  await load()
  if (loadErrorCode.value === 403) {
    toast.success('تم تنفيذ الإجراء بنجاح.')
    returnToQueue()
  } else if (loadErrorCode.value !== null) {
    toast.error('تم تنفيذ الإجراء، لكن تعذّر تحديث الصفحة. أعد التحميل يدوياً.')
  }
  actionBusy.value = false
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
                    v-if="!isEditMode"
                    :field-groups="fieldGroups"
                    :data="store.current.data"
                    :documents="store.documents"
                    :request-id="requestId"
                  />
                  <template v-else>
                    <Tabs
                      :default-value="orderedFieldGroups[0]?.name"
                      :unmount-on-hide="false"
                      dir="rtl"
                    >
                      <TabsList class="flex-wrap">
                        <TabsTrigger
                          v-for="group in orderedFieldGroups"
                          :key="group.id"
                          :value="group.name"
                        >
                          {{ group.label }}
                        </TabsTrigger>
                      </TabsList>
                      <TabsContent
                        v-for="(section, index) in editableGroupSections"
                        :key="section.group.id"
                        :value="section.group.name"
                        class="mt-4"
                      >
                        <div class="flex flex-col gap-6">
                          <DynamicForm
                            v-if="section.nonFileGroup"
                            :ref="(el) => setDynamicFormRef(el, index)"
                            v-model="formData"
                            :field-groups="[section.nonFileGroup]"
                            mode="edit"
                            :request-id="requestId"
                            :upload-target="{ type: 'request', requestId }"
                          />
                          <EngineFieldDocumentsGroup
                            v-if="section.fileGroup"
                            :group="section.fileGroup"
                            :documents="store.documents"
                            :request-id="requestId"
                            :can-manage="canAct"
                            @upload="onDocumentUpload"
                            @remove="onDocumentRemove"
                          />
                        </div>
                      </TabsContent>
                    </Tabs>

                    <div
                      v-if="otherDocuments.length"
                      class="mt-6 flex flex-col gap-2 border-t pt-4"
                    >
                      <h4 class="text-muted-foreground text-sm font-semibold">مرفقات أخرى</h4>
                      <EngineDocumentsPanel
                        :documents="otherDocuments"
                        :request-id="requestId"
                        :can-manage="false"
                      />
                    </div>
                  </template>
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
