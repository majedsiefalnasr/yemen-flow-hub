<script setup lang="ts">
import { computed, ref, toRef } from 'vue'
import type { ResolvedFieldGroup, EngineRequestDocument } from '@/types/models'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useEngineWizard } from '@/composables/useEngineWizard'
import DynamicForm from '@/components/workflow/DynamicForm.vue'
import EngineRequestDataTabs from '@/components/workflow/EngineRequestDataTabs.vue'
import {
  Stepper,
  StepperItem,
  StepperIndicator,
  StepperSeparator,
  StepperTitle,
  StepperTrigger,
} from '@/components/ui/stepper'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Check, AlertTriangle } from 'lucide-vue-next'

const props = defineProps<{
  requestId: number
  fieldGroups: ResolvedFieldGroup[]
  version: number
  initialData: Record<string, unknown>
  documents?: EngineRequestDocument[]
}>()

const emit = defineEmits<{ submitted: [] }>()

const store = useEngineRequestsStore()
const formRef = ref<InstanceType<typeof DynamicForm> | null>(null)
const formData = ref<Record<string, unknown>>({ ...props.initialData })
const lastSavedData = ref<Record<string, unknown>>({ ...props.initialData })
const submitError = ref<string | null>(null)

const groupsRef = toRef(props, 'fieldGroups')

const wizard = useEngineWizard(groupsRef, {
  extraSteps: 1,
  saveDraft: async (data) => {
    await store.saveDraftData(props.requestId, data, store.current?.version ?? props.version)
  },
  submit: async (data) => {
    const edges = store.graph?.edges ?? []
    const initial = edges.find((e) => e.from_stage_id === store.current?.current_stage?.id)
    if (!initial) {
      submitError.value = 'لا يوجد إجراء بدء متاح لهذا الطلب.'
      throw new Error('no initial transition')
    }
    await store.executeTransition(
      props.requestId,
      initial.id,
      null,
      data,
      store.current?.version ?? props.version,
    )
  },
})

const orderedGroups = computed(() =>
  [...props.fieldGroups].sort((a, b) => a.sort_order - b.sort_order),
)

// A synthetic final "review" step follows the field-group steps.
const groupCount = computed(() => orderedGroups.value.length)
const totalSteps = computed(() => groupCount.value + 1)
// 1-based number of the step the user is on (review = last).
const stepNumber = computed(() => wizard.stepIndex.value + 1)
const onReview = computed(() => wizard.stepIndex.value >= groupCount.value)

// Steps rendered in the horizontal stepper: one per group + the review step.
const stepperSteps = computed(() => [
  ...orderedGroups.value.map((g) => ({ id: g.id, label: g.label })),
  { id: -1, label: 'المراجعة والإرسال' },
])

const stepGroups = computed(() => (wizard.currentGroup.value ? [wizard.currentGroup.value] : []))

async function validateThen(action: (data: Record<string, unknown>) => Promise<void>) {
  submitError.value = null
  const result = await formRef.value?.validate()
  if (result && !result.valid) return
  const data = result?.values ?? formData.value
  formData.value = { ...formData.value, ...data }
  try {
    await action(data)
    lastSavedData.value = { ...formData.value }
  } catch {
    if (!submitError.value) submitError.value = 'تعذر حفظ الخطوة. حاول مرة أخرى.'
  }
}

// Advance from a field-group step to the next step (or into review).
async function onNext() {
  await validateThen((data) => wizard.next(data))
}

// From the last field-group step, save and move into the review step.
async function onGoToReview() {
  await validateThen(async (data) => {
    await wizard.next(data)
  })
}

// Submit from the review step using the accumulated data.
async function onSubmit() {
  submitError.value = null
  try {
    await wizard.finish(formData.value)
    lastSavedData.value = { ...formData.value }
    emit('submitted')
  } catch {
    if (!submitError.value) submitError.value = 'تعذر إرسال الطلب. حاول مرة أخرى.'
  }
}

function onBack() {
  wizard.back()
}

const isLastGroup = computed(() => wizard.stepIndex.value === groupCount.value - 1)

// Exposed so the parent page can decide whether to intercept navigation away
// from an in-progress wizard step (see onBeforeRouteLeave in [id].vue).
const hasUnsavedChanges = computed(
  () => JSON.stringify(formData.value) !== JSON.stringify(lastSavedData.value),
)

defineExpose({ hasUnsavedChanges })
</script>

<template>
  <div dir="rtl" class="flex flex-col gap-6 py-2">
    <!-- Horizontal numbered stepper: one step per field group + review. -->
    <div class="w-full overflow-x-auto">
      <Stepper :model-value="stepNumber" class="min-w-max">
        <StepperItem
          v-for="(step, index) in stepperSteps"
          :key="step.id"
          :step="index + 1"
          :completed="index < wizard.stepIndex.value"
          :disabled="true"
          class="flex-1"
        >
          <StepperTrigger class="pointer-events-none flex-col gap-1">
            <StepperIndicator>
              <Check v-if="index < wizard.stepIndex.value" class="h-4 w-4" />
              <span v-else>{{ index + 1 }}</span>
            </StepperIndicator>
            <StepperTitle
              :class="
                index === wizard.stepIndex.value
                  ? 'text-foreground font-semibold'
                  : 'text-muted-foreground'
              "
            >
              {{ step.label }}
            </StepperTitle>
          </StepperTrigger>
          <StepperSeparator v-if="index < stepperSteps.length - 1" class="mx-2" />
        </StepperItem>
      </Stepper>
    </div>

    <p class="text-muted-foreground text-xs">خطوة {{ stepNumber }} من {{ totalSteps }}</p>

    <!-- Review step: read-only grouped summary before submission. -->
    <Card v-if="onReview" class="border-0 shadow">
      <CardHeader class="pb-2">
        <CardTitle class="text-sm font-semibold">مراجعة الطلب قبل الإرسال</CardTitle>
      </CardHeader>
      <CardContent class="p-4 pt-0">
        <EngineRequestDataTabs
          :field-groups="fieldGroups"
          :data="formData"
          :documents="documents ?? []"
          :request-id="requestId"
        />
      </CardContent>
    </Card>

    <!-- Field-group step: editable form for the current group. -->
    <Card v-else class="border-0 shadow">
      <CardContent class="p-4">
        <DynamicForm
          ref="formRef"
          v-model="formData"
          :field-groups="stepGroups"
          mode="edit"
          :request-id="requestId"
        />
      </CardContent>
    </Card>

    <Alert v-if="submitError" variant="destructive" role="alert">
      <AlertTriangle class="h-4 w-4" />
      <AlertDescription>{{ submitError }}</AlertDescription>
    </Alert>

    <!-- Bottom actions panel. -->
    <Card class="border-0 shadow">
      <CardContent class="flex items-center justify-between gap-2 p-4">
        <Button
          variant="outline"
          :disabled="wizard.isFirst.value || wizard.busy.value"
          @click="onBack"
        >
          السابق
        </Button>

        <Button v-if="onReview" :disabled="wizard.busy.value" @click="onSubmit">
          إرسال الطلب
        </Button>
        <Button v-else-if="isLastGroup" :disabled="wizard.busy.value" @click="onGoToReview">
          مراجعة الطلب
        </Button>
        <Button v-else :disabled="wizard.busy.value" @click="onNext"> حفظ ومتابعة </Button>
      </CardContent>
    </Card>
  </div>
</template>
