<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Bank } from '@/types/models'
import { FormField, FormItem, FormLabel, FormControl, FormMessage } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import {
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

const CATEGORIES = [
  'مواد غذائية',
  'أدوية ومستلزمات طبية',
  'مشتقات نفطية',
  'قطع غيار',
  'مواد بناء',
  'إلكترونيات',
]

export interface MerchantFormData {
  name: string
  commercial_register: string
  tax_number: string
  address: string
  phone: string
  business_type: string
  is_active: boolean
  bank_id: number | null
}

const props = defineProps<{
  title: string
  banks: Bank[]
  initial?: MerchantFormData | null
  defaultBankId?: number | null
  lockBank?: boolean
}>()

const emit = defineEmits<{
  save: [data: MerchantFormData]
}>()

const formSchema = toTypedSchema(
  z.object({
    name: z.string().trim().min(1, 'أدخل اسم المستورد أو الشركة.'),
    commercial_register: z.string().trim().min(1, 'أدخل رقم السجل التجاري.'),
    tax_number: z.string().trim().min(1, 'أدخل الرقم الضريبي.'),
    address: z.string().optional().default(''),
    phone: z.string().optional().default(''),
    business_type: z.string().optional().default(''),
    is_active: z.string().default('active'),
    bank_id: z.string().optional().default(''),
  }),
)

const { handleSubmit, meta } = useForm({
  validationSchema: formSchema,
  initialValues: {
    name: props.initial?.name ?? '',
    commercial_register: props.initial?.commercial_register ?? '',
    tax_number: props.initial?.tax_number ?? '',
    address: props.initial?.address ?? '',
    phone: props.initial?.phone ?? '',
    business_type: props.initial?.business_type ?? CATEGORIES[0] ?? '',
    is_active: (props.initial?.is_active ?? true) ? 'active' : 'suspended',
    bank_id: props.initial?.bank_id?.toString() ?? props.defaultBankId?.toString() ?? '',
  },
})

const submit = handleSubmit((values) => {
  emit('save', {
    name: values.name.trim(),
    commercial_register: values.commercial_register.trim(),
    tax_number: values.tax_number.trim(),
    address: values.address ?? '',
    phone: values.phone ?? '',
    business_type: values.business_type ?? '',
    is_active: values.is_active !== 'suspended',
    bank_id: values.bank_id ? Number(values.bank_id) : null,
  })
})
</script>

<template>
  <DialogContent class="sm:max-w-lg">
    <DialogHeader>
      <DialogTitle>{{ title }}</DialogTitle>
      <DialogDescription>أدخل بيانات المستورد كما تظهر في السجل التجاري.</DialogDescription>
    </DialogHeader>

    <form class="grid gap-3 py-2 sm:grid-cols-2" @submit.prevent="submit">
      <!-- Name -->
      <FormField v-slot="{ componentField }" name="name">
        <FormItem>
          <FormLabel class="text-xs">
            اسم المستورد / الشركة <span class="text-destructive">*</span>
          </FormLabel>
          <FormControl>
            <Input v-bind="componentField" placeholder="مثال: شركة الكميم للأدوية" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <!-- Commercial register -->
      <FormField v-slot="{ componentField }" name="commercial_register">
        <FormItem>
          <FormLabel class="text-xs"
            >رقم السجل التجاري <span class="text-destructive">*</span></FormLabel
          >
          <FormControl>
            <Input v-bind="componentField" placeholder="CR-12345" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <!-- Tax number -->
      <FormField v-slot="{ componentField }" name="tax_number">
        <FormItem>
          <FormLabel class="text-xs"
            >الرقم الضريبي <span class="text-destructive">*</span></FormLabel
          >
          <FormControl>
            <Input v-bind="componentField" placeholder="4123456" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <!-- Phone -->
      <FormField v-slot="{ componentField }" name="phone">
        <FormItem>
          <FormLabel class="text-xs">هاتف التواصل</FormLabel>
          <FormControl>
            <Input v-bind="componentField" placeholder="مثال: +96771234567" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <!-- Business type -->
      <FormField v-slot="{ componentField }" name="business_type">
        <FormItem>
          <FormLabel class="text-xs">القطاع / النشاط</FormLabel>
          <Select v-bind="componentField">
            <FormControl>
              <SelectTrigger><SelectValue /></SelectTrigger>
            </FormControl>
            <SelectContent>
              <SelectItem v-for="category in CATEGORIES" :key="category" :value="category">
                {{ category }}
              </SelectItem>
            </SelectContent>
          </Select>
          <FormMessage />
        </FormItem>
      </FormField>

      <!-- Status -->
      <FormField v-slot="{ componentField }" name="is_active">
        <FormItem>
          <FormLabel class="text-xs">الحالة</FormLabel>
          <Select v-bind="componentField">
            <FormControl>
              <SelectTrigger><SelectValue /></SelectTrigger>
            </FormControl>
            <SelectContent>
              <SelectItem value="active">نشط</SelectItem>
              <SelectItem value="suspended">موقوف</SelectItem>
            </SelectContent>
          </Select>
          <FormMessage />
        </FormItem>
      </FormField>

      <!-- Bank -->
      <FormField v-slot="{ componentField }" name="bank_id">
        <FormItem class="sm:col-span-2">
          <FormLabel class="text-xs">البنك التابع له</FormLabel>
          <Select
            :model-value="componentField.modelValue"
            :disabled="lockBank"
            @update:model-value="componentField['onUpdate:modelValue']"
          >
            <FormControl>
              <SelectTrigger><SelectValue /></SelectTrigger>
            </FormControl>
            <SelectContent>
              <SelectItem v-for="bank in banks" :key="bank.id" :value="bank.id.toString()">
                {{ bank.name_ar }}
              </SelectItem>
            </SelectContent>
          </Select>
          <FormMessage />
        </FormItem>
      </FormField>

      <!-- Address -->
      <FormField v-slot="{ componentField }" name="address">
        <FormItem class="sm:col-span-2">
          <FormLabel class="text-xs">العنوان</FormLabel>
          <FormControl>
            <Input v-bind="componentField" placeholder="مثال: صنعاء، شارع الستين" />
          </FormControl>
          <FormMessage />
        </FormItem>
      </FormField>

      <DialogFooter class="sm:col-span-2">
        <Button type="submit" :disabled="!meta.valid">
          {{ initial ? 'حفظ بيانات المستورد' : 'حفظ المستورد' }}
        </Button>
      </DialogFooter>
    </form>
  </DialogContent>
</template>
