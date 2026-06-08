<script setup lang="ts">
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { Save } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Spinner } from '@/components/ui/spinner'
import TraderCompaniesField from '@/components/trader/TraderCompaniesField.vue'
import TraderOwnersField from '@/components/trader/TraderOwnersField.vue'
import type {
  CreateTraderPayload,
  Trader,
  TraderCompany,
  TraderFormValues,
  TraderOwner,
  UpdateTraderPayload,
} from '@/types/trader'
import { buildTraderPayload, traderFormSchema, traderToFormValues } from '@/types/trader'

const props = withDefaults(
  defineProps<{
    mode: 'create' | 'edit'
    trader?: Trader | null
    submitting?: boolean
    serverErrors?: Record<string, string | undefined>
  }>(),
  {
    trader: null,
    submitting: false,
    serverErrors: () => ({}),
  },
)

const emit = defineEmits<{
  submit: [payload: CreateTraderPayload | UpdateTraderPayload]
}>()

const { errors, handleSubmit, setErrors, setFieldValue, setValues, values } =
  useForm<TraderFormValues>({
    validationSchema: toTypedSchema(traderFormSchema),
    initialValues: traderToFormValues(props.trader),
  })

watch(
  () => props.trader,
  (trader) => {
    setValues(traderToFormValues(trader))
  },
)

watch(
  () => props.serverErrors,
  (serverErrors) => {
    setErrors(serverErrors)
  },
  { deep: true },
)

const displayErrors = computed<Record<string, string | undefined>>(() => ({
  ...(errors.value as Record<string, string | undefined>),
  ...props.serverErrors,
}))

const title = computed(() => (props.mode === 'create' ? 'إضافة تاجر' : 'تعديل بيانات التاجر'))
const description = computed(() =>
  props.mode === 'create'
    ? 'أدخل بيانات التاجر والشركات والملاك المرتبطين به.'
    : 'حدّث بيانات التاجر والشركات والملاك المرتبطين به.',
)

const onSubmit = handleSubmit((formValues) => {
  emit('submit', buildTraderPayload(formValues))
})
</script>

<template>
  <form class="flex flex-col gap-6" @submit.prevent="onSubmit">
    <Card class="border shadow">
      <CardHeader>
        <CardTitle>{{ title }}</CardTitle>
        <CardDescription>{{ description }}</CardDescription>
      </CardHeader>
      <CardContent class="grid gap-4 md:grid-cols-2">
        <FormField v-slot="{ componentField }" name="tax_number">
          <FormItem>
            <FormLabel>الرقم الضريبي</FormLabel>
            <FormControl>
              <Input v-bind="componentField" placeholder="أدخل الرقم الضريبي" />
            </FormControl>
            <FormMessage />
          </FormItem>
        </FormField>

        <FormField v-slot="{ componentField }" name="trader_name">
          <FormItem>
            <FormLabel>اسم التاجر</FormLabel>
            <FormControl>
              <Input v-bind="componentField" placeholder="أدخل اسم التاجر" />
            </FormControl>
            <FormMessage />
          </FormItem>
        </FormField>

        <FormField v-slot="{ componentField }" name="tax_card_expiry">
          <FormItem>
            <FormLabel>تاريخ انتهاء البطاقة الضريبية</FormLabel>
            <FormControl>
              <Input v-bind="componentField" type="date" />
            </FormControl>
            <FormMessage />
          </FormItem>
        </FormField>

        <FormField v-slot="{ componentField }" name="commercial_registration_number">
          <FormItem>
            <FormLabel>رقم السجل التجاري</FormLabel>
            <FormControl>
              <Input v-bind="componentField" placeholder="أدخل رقم السجل التجاري" />
            </FormControl>
            <FormMessage />
          </FormItem>
        </FormField>

        <FormField v-slot="{ componentField }" name="commercial_registration_expiry">
          <FormItem>
            <FormLabel>تاريخ انتهاء السجل التجاري</FormLabel>
            <FormControl>
              <Input v-bind="componentField" type="date" />
            </FormControl>
            <FormMessage />
          </FormItem>
        </FormField>
      </CardContent>
    </Card>

    <Card class="border shadow">
      <CardContent class="space-y-6 p-4">
        <TraderCompaniesField
          :model-value="(values.companies ?? []) as TraderCompany[]"
          :errors="displayErrors"
          @update:model-value="setFieldValue('companies', $event)"
        />
        <TraderOwnersField
          :model-value="(values.owners ?? []) as TraderOwner[]"
          :errors="displayErrors"
          @update:model-value="setFieldValue('owners', $event)"
        />
      </CardContent>
    </Card>

    <div class="flex items-center justify-end gap-3">
      <Button type="submit" :disabled="submitting">
        <Spinner v-if="submitting" class="me-2 h-4 w-4" />
        <Save v-else class="me-2 h-4 w-4" aria-hidden="true" />
        {{ submitting ? 'جارٍ الحفظ' : 'حفظ التاجر' }}
      </Button>
    </div>
  </form>
</template>
