<script setup lang="ts">
import { useForm, useFieldArray } from 'vee-validate'
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
import { Separator } from '@/components/ui/separator'
import { Plus, Trash2 } from 'lucide-vue-next'

export interface MerchantFormData {
  name: string
  tax_number: string
  tax_card_expiry: string
  address: string
  phone: string
  status: string
  bank_id: number | null
  version: number
  owners: { name: string; ownership_percentage: number }[]
  companies: {
    name: string
    commercial_registration_number: string
    commercial_registration_expiry: string
    is_active: boolean
  }[]
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

const ownerSchema = z.object({
  name: z.string().trim().min(1, 'أدخل اسم المالك.'),
  ownership_percentage: z.coerce.number().min(0, 'الحد الأدنى 0').max(100, 'الحد الأقصى 100'),
})

const companySchema = z.object({
  name: z.string().trim().min(1, 'أدخل اسم الشركة.'),
  commercial_registration_number: z.string().trim().min(1, 'أدخل رقم السجل التجاري.'),
  commercial_registration_expiry: z.string().optional().default(''),
  is_active: z.string().default('true'),
})

const formSchema = toTypedSchema(
  z.object({
    name: z.string().trim().min(1, 'أدخل اسم المستورد أو الشركة.'),
    tax_number: z.string().trim().min(1, 'أدخل الرقم الضريبي.'),
    tax_card_expiry: z.string().optional().default(''),
    address: z.string().optional().default(''),
    phone: z.string().optional().default(''),
    status: z.string().default('ACTIVE'),
    bank_id: z.string().optional().default(''),
    version: z.coerce.number().default(1),
    owners: z.array(ownerSchema).optional().default([]),
    companies: z.array(companySchema).optional().default([]),
  }),
)

const { handleSubmit, meta } = useForm({
  validationSchema: formSchema,
  initialValues: {
    name: props.initial?.name ?? '',
    tax_number: props.initial?.tax_number ?? '',
    tax_card_expiry: props.initial?.tax_card_expiry ?? '',
    address: props.initial?.address ?? '',
    phone: props.initial?.phone ?? '',
    status: props.initial?.status ?? 'ACTIVE',
    bank_id: props.initial?.bank_id?.toString() ?? props.defaultBankId?.toString() ?? '',
    version: props.initial?.version ?? 1,
    owners: (props.initial?.owners ?? []).map((o) => ({
      name: o.name,
      ownership_percentage: o.ownership_percentage,
    })),
    companies: (props.initial?.companies ?? []).map((c) => ({
      name: c.name,
      commercial_registration_number: c.commercial_registration_number,
      commercial_registration_expiry: c.commercial_registration_expiry ?? '',
      is_active: c.is_active ? 'true' : 'false',
    })),
  },
})

const {
  fields: ownerFields,
  push: addOwner,
  remove: removeOwner,
} = useFieldArray<{ name: string; ownership_percentage: number }>('owners')

const {
  fields: companyFields,
  push: addCompany,
  remove: removeCompany,
} = useFieldArray<{
  name: string
  commercial_registration_number: string
  commercial_registration_expiry: string
  is_active: string
}>('companies')

const submit = handleSubmit((values) => {
  emit('save', {
    name: values.name.trim(),
    tax_number: values.tax_number.trim(),
    tax_card_expiry: values.tax_card_expiry || '',
    address: values.address ?? '',
    phone: values.phone ?? '',
    status: values.status,
    bank_id: values.bank_id ? Number(values.bank_id) : null,
    version: values.version,
    owners: (values.owners ?? []).map((o: any) => ({
      name: o.name.trim(),
      ownership_percentage: Number(o.ownership_percentage),
    })),
    companies: (values.companies ?? []).map((c: any) => ({
      name: c.name.trim(),
      commercial_registration_number: c.commercial_registration_number.trim(),
      commercial_registration_expiry: c.commercial_registration_expiry || '',
      is_active: c.is_active !== 'false',
    })),
  })
})
</script>

<template>
  <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
    <DialogHeader>
      <DialogTitle>{{ title }}</DialogTitle>
      <DialogDescription>أدخل بيانات المستورد كما تظهر في السجل التجاري.</DialogDescription>
    </DialogHeader>

    <form class="grid gap-3 py-2 sm:grid-cols-2" @submit.prevent="submit">
      <!-- Name -->
      <FormField v-slot="{ componentField }" name="name">
        <FormItem class="sm:col-span-2">
          <FormLabel class="text-xs">
            اسم المستورد / الشركة <span class="text-destructive">*</span>
          </FormLabel>
          <FormControl>
            <Input v-bind="componentField" placeholder="مثال: شركة الكميم للأدوية" />
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

      <!-- Tax card expiry -->
      <FormField v-slot="{ componentField }" name="tax_card_expiry">
        <FormItem>
          <FormLabel class="text-xs">تاريخ انتهاء البطاقة الضريبية</FormLabel>
          <FormControl>
            <Input v-bind="componentField" type="date" />
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

      <!-- Status -->
      <FormField v-slot="{ componentField }" name="status">
        <FormItem>
          <FormLabel class="text-xs">الحالة</FormLabel>
          <Select v-bind="componentField">
            <FormControl>
              <SelectTrigger><SelectValue /></SelectTrigger>
            </FormControl>
            <SelectContent>
              <SelectItem value="ACTIVE">نشط</SelectItem>
              <SelectItem value="SUSPENDED">موقوف</SelectItem>
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

      <!-- Owners section -->
      <div class="sm:col-span-2">
        <Separator class="my-2" />
        <div class="mb-2 flex items-center justify-between">
          <span class="text-xs font-semibold">المالكون</span>
          <Button
            type="button"
            variant="outline"
            size="sm"
            class="h-7 gap-1 text-xs"
            @click="addOwner({ name: '', ownership_percentage: 0 })"
          >
            <Plus class="h-3 w-3" />
            إضافة مالك
          </Button>
        </div>
        <div v-for="(field, idx) in ownerFields" :key="field.key" class="mb-2 flex gap-2">
          <FormField v-slot="{ componentField }" :name="`owners[${idx}].name`">
            <FormItem class="flex-1">
              <FormControl>
                <Input v-bind="componentField" placeholder="اسم المالك" />
              </FormControl>
              <FormMessage />
            </FormItem>
          </FormField>
          <FormField v-slot="{ componentField }" :name="`owners[${idx}].ownership_percentage`">
            <FormItem class="w-24">
              <FormControl>
                <Input v-bind="componentField" type="number" min="0" max="100" placeholder="%" />
              </FormControl>
              <FormMessage />
            </FormItem>
          </FormField>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            class="text-destructive h-9 w-9 shrink-0"
            @click="removeOwner(idx)"
          >
            <Trash2 class="h-3.5 w-3.5" />
          </Button>
        </div>
      </div>

      <!-- Companies section -->
      <div class="sm:col-span-2">
        <Separator class="my-2" />
        <div class="mb-2 flex items-center justify-between">
          <span class="text-xs font-semibold">الشركات التابعة</span>
          <Button
            type="button"
            variant="outline"
            size="sm"
            class="h-7 gap-1 text-xs"
            @click="
              addCompany({
                name: '',
                commercial_registration_number: '',
                commercial_registration_expiry: '',
                is_active: 'true',
              })
            "
          >
            <Plus class="h-3 w-3" />
            إضافة شركة
          </Button>
        </div>
        <div
          v-for="(field, idx) in companyFields"
          :key="field.key"
          class="mb-3 rounded-md border p-3"
        >
          <div class="mb-2 flex items-start justify-between">
            <span class="text-muted-foreground text-xs">شركة {{ idx + 1 }}</span>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              class="text-destructive h-7 w-7"
              @click="removeCompany(idx)"
            >
              <Trash2 class="h-3 w-3" />
            </Button>
          </div>
          <div class="grid gap-2 sm:grid-cols-2">
            <FormField v-slot="{ componentField }" :name="`companies[${idx}].name`">
              <FormItem>
                <FormControl>
                  <Input v-bind="componentField" placeholder="اسم الشركة" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>
            <FormField
              v-slot="{ componentField }"
              :name="`companies[${idx}].commercial_registration_number`"
            >
              <FormItem>
                <FormControl>
                  <Input v-bind="componentField" placeholder="رقم السجل التجاري" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>
            <FormField
              v-slot="{ componentField }"
              :name="`companies[${idx}].commercial_registration_expiry`"
            >
              <FormItem>
                <FormControl>
                  <Input v-bind="componentField" type="date" placeholder="تاريخ الانتهاء" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>
            <FormField v-slot="{ componentField }" :name="`companies[${idx}].is_active`">
              <FormItem>
                <Select v-bind="componentField">
                  <FormControl>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="true">نشطة</SelectItem>
                    <SelectItem value="false">غير نشطة</SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            </FormField>
          </div>
        </div>
      </div>

      <DialogFooter class="sm:col-span-2">
        <Button type="submit" :disabled="!meta.valid">
          {{ initial ? 'حفظ بيانات المستورد' : 'حفظ المستورد' }}
        </Button>
      </DialogFooter>
    </form>
  </DialogContent>
</template>
