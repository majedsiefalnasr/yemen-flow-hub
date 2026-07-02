<script setup lang="ts">
import { computed, ref, toRef } from 'vue'
import type { ResolvedFieldGroup } from '@/types/models'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useEngineWizard } from '@/composables/useEngineWizard'
import DynamicForm from '@/components/workflow/DynamicForm.vue'
import {
  Stepper,
  StepperItem,
  StepperIndicator,
  StepperSeparator,
  StepperTitle,
  StepperTrigger,
} from '@/components/ui/stepper'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Check, AlertTriangle } from 'lucide-vue-next'

const props = defineProps<{
  requestId: number
  fieldGroups: ResolvedFieldGroup[]
  version: number
  initialData: Record<string, unknown>
}>()

const emit = defineEmits<{ submitted: [] }>()

const store = useEngineRequestsStore()
const formRef = ref<InstanceType<typeof DynamicForm> | null>(null)
const formData = ref<Record<string, unknown>>({ ...props.initialData })
const submitError = ref<string | null>(null)

const groupsRef = toRef(props, 'fieldGroups')

const wizard = useEngineWizard(groupsRef, {
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

const stepGroups = computed(() => (wizard.currentGroup.value ? [wizard.currentGroup.value] : []))
const stepperValue = computed(() => wizard.stepIndex.value + 1)

async function validateThen(action: (data: Record<string, unknown>) => Promise<void>) {
  submitError.value = null
  const result = await formRef.value?.validate()
  if (result && !result.valid) return
  const data = result?.values ?? formData.value
  try {
    await action(data)
  } catch {
    if (!submitError.value) submitError.value = 'تعذر حفظ الخطوة. حاول مرة أخرى.'
  }
}

async function onNext() {
  await validateThen((data) => wizard.next(data))
}

async function onSubmit() {
  await validateThen(async (data) => {
    await wizard.finish(data)
    emit('submitted')
  })
}
</script>

<template>
  <div dir="rtl" class="flex flex-col gap-6">
    <Stepper :model-value="stepperValue" orientation="vertical" class="w-full">
      <StepperItem
        v-for="(group, index) in [...fieldGroups].sort((a, b) => a.sort_order - b.sort_order)"
        :key="group.id"
        :step="index + 1"
        :completed="index < wizard.stepIndex.value"
        :disabled="true"
      >
        <StepperTrigger class="pointer-events-none">
          <StepperIndicator>
            <Check v-if="index < wizard.stepIndex.value" class="h-4 w-4" />
            <span v-else>{{ index + 1 }}</span>
          </StepperIndicator>
          <StepperTitle>{{ group.label }}</StepperTitle>
        </StepperTrigger>
        <StepperSeparator v-if="index < fieldGroups.length - 1" />
      </StepperItem>
    </Stepper>

    <Card class="border-0 shadow">
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

    <div class="flex items-center justify-between gap-2">
      <Button
        variant="outline"
        :disabled="wizard.isFirst.value || wizard.busy.value"
        @click="wizard.back()"
      >
        السابق
      </Button>
      <Button v-if="!wizard.isLast.value" :disabled="wizard.busy.value" @click="onNext">
        حفظ ومتابعة
      </Button>
      <Button v-else :disabled="wizard.busy.value" @click="onSubmit"> إرسال الطلب </Button>
    </div>
  </div>
</template>
