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
import Label from '@/components/ui/label/Label.vue'
import { Alert, AlertDescription } from '@/components/ui/alert'

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
  errors,
  defineField,
  resetForm,
  meta,
  setFieldError,
} = useForm({
  validationSchema: schema,
  validateOnMount: true,
})

const [name, nameAttrs] = defineField('name')
const [commercial_register, commercialRegisterAttrs] = defineField('commercial_register')
const [tax_number, taxNumberAttrs] = defineField('tax_number')
const [phone, phoneAttrs] = defineField('phone')
const [address, addressAttrs] = defineField('address')
const [business_type, businessTypeAttrs] = defineField('business_type')
const [is_active, isActiveAttrs] = defineField('is_active')
const [bank_id, bankIdAttrs] = defineField('bank_id')

const isEditMode = computed(() => !!props.merchant)
const isBankRequiredForCreate = computed(() => props.requiresBankSelection && !props.merchant)
const showLockedBankField = computed(() => !props.requiresBankSelection && !!props.lockedBankName)
const isSaveDisabled = computed(() => (
  props.saving
  || !meta.value.valid
  || (isBankRequiredForCreate.value && !bank_id.value)
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
        name: name.value ?? '',
        commercial_register: commercial_register.value ?? '',
        tax_number: tax_number.value ?? '',
        phone: phone.value ?? '',
        address: address.value ?? '',
        business_type: business_type.value ?? '',
        is_active: is_active.value ?? 'true',
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
        dir="rtl"
        :aria-label="isEditMode ? 'تعديل بيانات التاجر' : 'تسجيل تاجر جديد'"
      >
        <DialogHeader class="flex items-start justify-between">
          <div>
            <DialogTitle class="text-xl font-semibold text-foreground">
              {{ isEditMode ? 'تعديل بيانات التاجر' : 'تسجيل تاجر جديد' }}
            </DialogTitle>
            <p class="text-xs text-muted-foreground mt-1">الحقول المعلّمة بـ * إلزامية.</p>
          </div>
          <button
            class="text-muted-foreground hover:text-foreground disabled:opacity-50 disabled:cursor-not-allowed text-lg leading-none p-1"
            aria-label="إغلاق"
            :disabled="props.saving"
            @click="requestClose"
          >
            ✕
          </button>
        </DialogHeader>

        <Alert v-if="props.serverError" class="border-l-4 border-l-red-600 bg-destructive/10 border-0" role="alert">
          <AlertCircle class="h-4 w-4 text-destructive" aria-hidden="true" />
          <AlertDescription class="text-destructive text-sm">{{ props.serverError }}</AlertDescription>
        </Alert>

        <form class="flex flex-col gap-5" @submit.prevent="onSubmit">
          <div class="grid grid-cols-2 gap-4">
            <!-- Bank selector for CBY Admin creating a new merchant -->
            <template v-if="isBankRequiredForCreate">
              <div class="col-span-2 flex flex-col gap-2">
                <Label for="bank-id" class="text-xs text-muted-foreground font-medium">
                  البنك التابع له <span class="text-destructive">*</span>
                </Label>
                <select
                  id="bank-id"
                  v-model="bank_id"
                  v-bind="bankIdAttrs"
                  class="h-9 px-3 border border-border rounded-md bg-white text-sm text-foreground outline-none focus-visible:ring-1 focus-visible:ring-blue-600"
                  :class="{ 'border-destructive': errors.bank_id }"
                >
                  <option value="">اختر البنك</option>
                  <option v-for="bank in props.bankOptions" :key="bank.id" :value="String(bank.id)">
                    {{ bank.name }}
                  </option>
                </select>
                <span v-if="errors.bank_id" class="text-xs text-destructive" role="alert">{{ errors.bank_id }}</span>
              </div>
            </template>

            <template v-else-if="showLockedBankField">
              <div class="col-span-2 flex flex-col gap-2">
                <Label for="locked-bank-name" class="text-xs text-muted-foreground font-medium">
                  البنك التابع له <span class="text-destructive">*</span>
                </Label>
                <Input
                  id="locked-bank-name"
                  :value="props.lockedBankName ?? ''"
                  type="text"
                  readonly
                  disabled
                  class="bg-muted text-muted-foreground cursor-not-allowed"
                />
                <span class="text-xs text-muted-foreground">مرتبط بالبنك المسجل على حسابك.</span>
              </div>
            </template>

            <!-- Name -->
            <div class="col-span-2 flex flex-col gap-2">
              <Label for="merchant-name" class="text-xs text-muted-foreground font-medium">
                اسم التاجر / الشركة <span class="text-destructive">*</span>
              </Label>
              <Input
                id="merchant-name"
                v-model="name"
                v-bind="nameAttrs"
                type="text"
                placeholder="مثال: شركة الكميم للأدوية"
                :class="{ 'border-destructive': errors.name }"
              />
              <span v-if="errors.name" class="text-xs text-destructive" role="alert">{{ errors.name }}</span>
            </div>

            <!-- Commercial register -->
            <div class="flex flex-col gap-2">
              <Label for="commercial-register" class="text-xs text-muted-foreground font-medium">
                رقم السجل التجاري <span class="text-destructive">*</span>
              </Label>
              <Input
                id="commercial-register"
                v-model="commercial_register"
                v-bind="commercialRegisterAttrs"
                type="text"
                placeholder="CR-12345"
                dir="ltr"
                :class="{ 'border-destructive': errors.commercial_register }"
              />
              <span v-if="errors.commercial_register" class="text-xs text-destructive" role="alert">{{ errors.commercial_register }}</span>
            </div>

            <!-- Tax number -->
            <div class="flex flex-col gap-2">
              <Label for="tax-number" class="text-xs text-muted-foreground font-medium">
                الرقم الضريبي <span class="text-destructive">*</span>
              </Label>
              <Input
                id="tax-number"
                v-model="tax_number"
                v-bind="taxNumberAttrs"
                type="text"
                placeholder="4123456"
                dir="ltr"
                :class="{ 'border-destructive': errors.tax_number }"
              />
              <span v-if="errors.tax_number" class="text-xs text-destructive" role="alert">{{ errors.tax_number }}</span>
            </div>

            <!-- Phone -->
            <div class="flex flex-col gap-2">
              <Label for="merchant-phone" class="text-xs text-muted-foreground font-medium">
                هاتف التواصل
              </Label>
              <Input
                id="merchant-phone"
                v-model="phone"
                v-bind="phoneAttrs"
                type="text"
                placeholder="+9677…"
                dir="ltr"
              />
            </div>

            <!-- Business type -->
            <div class="flex flex-col gap-2">
              <Label for="business-type" class="text-xs text-muted-foreground font-medium">
                القطاع / النشاط
              </Label>
              <select
                id="business-type"
                v-model="business_type"
                v-bind="businessTypeAttrs"
                class="h-9 px-3 border border-border rounded-md bg-white text-sm text-foreground outline-none focus-visible:ring-1 focus-visible:ring-blue-600"
              >
                <option value="">اختر القطاع</option>
                <option v-for="opt in BUSINESS_TYPE_OPTIONS" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <!-- Status — edit mode only -->
            <div v-if="isEditMode" class="flex flex-col gap-2">
              <Label for="merchant-status" class="text-xs text-muted-foreground font-medium">
                الحالة
              </Label>
              <select
                id="merchant-status"
                v-model="is_active"
                v-bind="isActiveAttrs"
                class="h-9 px-3 border border-border rounded-md bg-white text-sm text-foreground outline-none focus-visible:ring-1 focus-visible:ring-blue-600"
              >
                <option value="true">نشط</option>
                <option value="false">موقوف</option>
              </select>
            </div>

            <!-- Address -->
            <div class="col-span-2 flex flex-col gap-2">
              <Label for="address" class="text-xs text-muted-foreground font-medium">
                العنوان
              </Label>
              <Input
                id="address"
                v-model="address"
                v-bind="addressAttrs"
                type="text"
                placeholder="المدينة – الشارع"
              />
            </div>
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

