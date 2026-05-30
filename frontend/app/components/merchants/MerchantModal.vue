<script setup lang="ts">
import { computed, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Merchant } from '../../types/models'
import { AlertCircle } from 'lucide-vue-next'
import Dialog from '@/components/ui/dialog/Dialog.vue'
import DialogContent from '@/components/ui/dialog/DialogContent.vue'
import DialogFooter from '@/components/ui/dialog/DialogFooter.vue'
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue'
import DialogOverlay from '@/components/ui/dialog/DialogOverlay.vue'
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { FormField, FormItem, FormLabel, FormControl, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const BUSINESS_TYPE_OPTIONS = [
  { value: 'import', label: 'استيراد' },
  { value: 'export', label: 'تصدير' },
  { value: 'retail', label: 'تجارة تجزئة' },
  { value: 'wholesale', label: 'تجارة جملة' },
  { value: 'manufacturing', label: 'تصنيع' },
  { value: 'services', label: 'خدمات' },
]

const schema = toTypedSchema(z.object({
  name: z.string().trim().min(1, 'اسم التاجر مطلوب'),
  commercial_register: z.string().trim().min(1, 'رقم السجل التجاري مطلوب'),
  tax_number: z.string().trim().min(1, 'الرقم الضريبي مطلوب'),
  phone: z.string().optional().default(''),
  address: z.string().optional().default(''),
  business_type: z.string().optional().default(''),
  is_active: z.string().optional().default('true'),
  bank_id: z.string().optional().default(''),
}))

interface BankOption {
  id: number
  name: string
}

const props = defineProps<{
  merchant: Merchant | null
  saving: boolean
  serverError: string | null
  requiresBankSelection: boolean
  bankOptions: BankOption[]
  defaultBankId: number | null
  lockedBankName: string | null
}>()

const emit = defineEmits<{
  save: [data: {
    name: string
    commercial_register: string
    tax_number: string
    phone: string | null
    address: string | null
    business_type: string | null
    is_active: boolean | undefined
    bank_id: number | null
  }]
  close: []
}>()

const {
  handleSubmit,
  resetForm,
  meta,
  setFieldError,
  values,
} = useForm({
  validationSchema: schema,
  validateOnMount: true,
})

const isEditMode = computed(() => !!props.merchant)
const isBankRequiredForCreate = computed(() => props.requiresBankSelection && !props.merchant)
const showLockedBankField = computed(() => !props.requiresBankSelection && !!props.lockedBankName)
const isSaveDisabled = computed(() => (
  props.saving
  || !meta.value.valid
  || (isBankRequiredForCreate.value && !values.bank_id)
))

watch(() => props.merchant, (merchant) => {
  if (merchant) {
    resetForm({
      values: {
        name: merchant.name,
        commercial_register: merchant.commercial_register ?? '',
        tax_number: merchant.tax_number ?? '',
        phone: merchant.phone ?? '',
        address: merchant.address ?? '',
        business_type: merchant.business_type ?? '',
        is_active: merchant.is_active ? 'true' : 'false',
        bank_id: merchant.bank_id ? String(merchant.bank_id) : '',
      },
    })
  }
  else {
    resetForm({
      values: {
        name: '',
        commercial_register: '',
        tax_number: '',
        phone: '',
        address: '',
        business_type: '',
        is_active: 'true',
        bank_id: props.defaultBankId ? String(props.defaultBankId) : '',
      },
    })
  }
}, { immediate: true })

watch(() => props.defaultBankId, (newValue) => {
  if (!props.merchant) {
    resetForm({
      values: {
        name: values.name ?? '',
        commercial_register: values.commercial_register ?? '',
        tax_number: values.tax_number ?? '',
        phone: values.phone ?? '',
        address: values.address ?? '',
        business_type: values.business_type ?? '',
        is_active: values.is_active ?? 'true',
        bank_id: newValue ? String(newValue) : '',
      },
    })
  }
})

function requestClose() {
  if (!props.saving) {
    emit('close')
  }
}

function onDialogOpenChange(open: boolean) {
  if (!open) {
    requestClose()
  }
}

const onSubmit = handleSubmit((values) => {
  if (isBankRequiredForCreate.value && !values.bank_id) {
    setFieldError('bank_id', 'اختيار البنك مطلوب')
    return
  }

  emit('save', {
    name: values.name.trim(),
    commercial_register: values.commercial_register.trim(),
    tax_number: values.tax_number.trim(),
    phone: values.phone?.trim() || null,
    address: values.address?.trim() || null,
    business_type: values.business_type?.trim() || null,
    is_active: isEditMode.value ? values.is_active === 'true' : undefined,
    bank_id: values.bank_id ? Number(values.bank_id) : null,
  })
})
</script>

<template>
  <Dialog :open="true" @update:open="onDialogOpenChange">
    <div class="modal-layer">
      <DialogOverlay class="modal-backdrop" @click="requestClose" />
      <DialogContent
        class="modal"
        
        :aria-label="isEditMode ? 'تعديل بيانات التاجر' : 'تسجيل تاجر جديد'"
      >
        <DialogHeader class="flex items-start justify-between">
          <div>
            <DialogTitle class="text-xl font-semibold text-gray-900">
              {{ isEditMode ? 'تعديل بيانات التاجر' : 'تسجيل تاجر جديد' }}
            </DialogTitle>
            <p class="text-xs text-gray-600 mt-1">الحقول المعلّمة بـ * إلزامية.</p>
          </div>
          <button
            class="text-gray-600 hover:text-gray-900 disabled:opacity-50 disabled:cursor-not-allowed text-lg leading-none p-1"
            aria-label="إغلاق"
            :disabled="props.saving"
            @click="requestClose"
          >
            ✕
          </button>
        </DialogHeader>

        <Alert v-if="props.serverError" class="border-s-4 border-s-red-600 bg-red-700/10 border-0" role="alert">
          <AlertCircle class="h-4 w-4 text-red-700" aria-hidden="true" />
          <AlertDescription class="text-red-700 text-sm">{{ props.serverError }}</AlertDescription>
        </Alert>

        <form class="flex flex-col gap-5" @submit.prevent="onSubmit">
          <div class="grid grid-cols-2 gap-4">
            <!-- Bank selector for CBY Admin creating a new merchant -->
            <template v-if="isBankRequiredForCreate">
              <FormField name="bank_id" v-slot="{ componentField }">
                <FormItem class="col-span-2">
                  <FormLabel class="text-xs">البنك التابع له <span class="text-destructive">*</span></FormLabel>
                  <Select v-bind="componentField">
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="اختر البنك" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem v-for="bank in props.bankOptions" :key="bank.id" :value="String(bank.id)">
                        {{ bank.name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              </FormField>
            </template>

            <template v-else-if="showLockedBankField">
              <FormItem class="col-span-2">
                <FormLabel class="text-xs">البنك التابع له <span class="text-destructive">*</span></FormLabel>
                <FormControl>
                  <Input :value="props.lockedBankName ?? ''" type="text" readonly disabled class="bg-gray-50 text-gray-600 cursor-not-allowed" />
                </FormControl>
                <p class="text-xs text-muted-foreground">مرتبط بالبنك المسجل على حسابك.</p>
              </FormItem>
            </template>

            <!-- Name -->
            <FormField name="name" v-slot="{ componentField }">
              <FormItem class="col-span-2">
                <FormLabel class="text-xs">اسم التاجر / الشركة <span class="text-destructive">*</span></FormLabel>
                <FormControl>
                  <Input v-bind="componentField" type="text" placeholder="مثال: شركة الكميم للأدوية" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <!-- Commercial register -->
            <FormField name="commercial_register" v-slot="{ componentField }">
              <FormItem>
                <FormLabel class="text-xs">رقم السجل التجاري <span class="text-destructive">*</span></FormLabel>
                <FormControl>
                  <Input v-bind="componentField" type="text" placeholder="CR-12345" dir="ltr" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <!-- Tax number -->
            <FormField name="tax_number" v-slot="{ componentField }">
              <FormItem>
                <FormLabel class="text-xs">الرقم الضريبي <span class="text-destructive">*</span></FormLabel>
                <FormControl>
                  <Input v-bind="componentField" type="text" placeholder="4123456" dir="ltr" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <!-- Phone -->
            <FormField name="phone" v-slot="{ componentField }">
              <FormItem>
                <FormLabel class="text-xs">هاتف التواصل</FormLabel>
                <FormControl>
                  <Input v-bind="componentField" type="text" placeholder="+9677…" dir="ltr" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <!-- Business type -->
            <FormField name="business_type" v-slot="{ componentField }">
              <FormItem>
                <FormLabel class="text-xs">القطاع / النشاط</FormLabel>
                <Select v-bind="componentField">
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="اختر القطاع" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem v-for="opt in BUSINESS_TYPE_OPTIONS" :key="opt.value" :value="opt.value">
                      {{ opt.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            </FormField>

            <!-- Status — edit mode only -->
            <FormField v-if="isEditMode" name="is_active" v-slot="{ componentField }">
              <FormItem>
                <FormLabel class="text-xs">الحالة</FormLabel>
                <Select v-bind="componentField">
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="true">نشط</SelectItem>
                    <SelectItem value="false">موقوف</SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            </FormField>

            <!-- Address -->
            <FormField name="address" v-slot="{ componentField }">
              <FormItem class="col-span-2">
                <FormLabel class="text-xs">العنوان</FormLabel>
                <FormControl>
                  <Input v-bind="componentField" type="text" placeholder="المدينة – الشارع" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>
          </div>

          <DialogFooter class="flex justify-end gap-3 pt-2">
            <Button type="button" variant="outline" :disabled="props.saving" @click="requestClose">
              إلغاء
            </Button>
            <Button type="submit" :disabled="isSaveDisabled">
              <template v-if="props.saving">جارٍ الحفظ…</template>
              <template v-else-if="isEditMode">حفظ التعديلات</template>
              <template v-else>حفظ التاجر</template>
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </div>
  </Dialog>
</template>

