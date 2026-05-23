<script setup lang="ts">
import type { Bank } from '@/types/models'

const CATEGORIES = ['مواد غذائية', 'أدوية ومستلزمات طبية', 'مشتقات نفطية', 'قطع غيار', 'مواد بناء', 'إلكترونيات']

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

const form = reactive<MerchantFormData>({
  name: props.initial?.name ?? '',
  commercial_register: props.initial?.commercial_register ?? '',
  tax_number: props.initial?.tax_number ?? '',
  address: props.initial?.address ?? '',
  phone: props.initial?.phone ?? '',
  business_type: props.initial?.business_type ?? CATEGORIES[0] ?? '',
  is_active: props.initial?.is_active ?? true,
  bank_id: props.initial?.bank_id ?? props.defaultBankId ?? null,
})

const valid = computed(() =>
  Boolean(form.name.trim() && form.commercial_register.trim() && form.tax_number.trim() && form.bank_id),
)

function submit() {
  if (!valid.value) return
  emit('save', { ...form, name: form.name.trim(), commercial_register: form.commercial_register.trim(), tax_number: form.tax_number.trim() })
}
</script>

<template>
  <DialogContent class="sm:max-w-lg">
    <DialogHeader>
      <DialogTitle>{{ title }}</DialogTitle>
      <DialogDescription>الحقول المعلّمة إلزامية.</DialogDescription>
    </DialogHeader>

    <div class="grid gap-3 py-2 sm:grid-cols-2">
      <div class="space-y-1.5">
        <Label class="text-xs text-muted-foreground">اسم التاجر / الشركة</Label>
        <Input
          v-model="form.name"
          placeholder="مثال: شركة الكميم للأدوية"
        />
      </div>

      <div class="space-y-1.5">
        <Label class="text-xs text-muted-foreground">رقم السجل التجاري</Label>
        <Input
          v-model="form.commercial_register"
          placeholder="CR-12345"
        />
      </div>

      <div class="space-y-1.5">
        <Label class="text-xs text-muted-foreground">الرقم الضريبي</Label>
        <Input
          v-model="form.tax_number"
          placeholder="4123456"
        />
      </div>

      <div class="space-y-1.5">
        <Label class="text-xs text-muted-foreground">هاتف التواصل</Label>
        <Input
          v-model="form.phone"
          placeholder="+9677..."
        />
      </div>

      <div class="space-y-1.5">
        <Label class="text-xs text-muted-foreground">القطاع / النشاط</Label>
        <Select v-model="form.business_type">
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="category in CATEGORIES"
              :key="category"
              :value="category"
            >
              {{ category }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="space-y-1.5">
        <Label class="text-xs text-muted-foreground">الحالة</Label>
        <Select :model-value="form.is_active ? 'active' : 'suspended'" @update:model-value="v => form.is_active = v === 'active'">
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="active">
              نشط
            </SelectItem>
            <SelectItem value="suspended">
              موقوف
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="space-y-1.5 sm:col-span-2">
        <Label class="text-xs text-muted-foreground">البنك التابع له</Label>
        <Select
          :model-value="form.bank_id?.toString() ?? ''"
          :disabled="lockBank"
          @update:model-value="v => form.bank_id = v ? Number(v) : null"
        >
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="bank in banks"
              :key="bank.id"
              :value="bank.id.toString()"
            >
              {{ bank.name_ar }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="space-y-1.5 sm:col-span-2">
        <Label class="text-xs text-muted-foreground">العنوان</Label>
        <Input
          v-model="form.address"
          placeholder="المدينة – الشارع"
        />
      </div>
    </div>

    <DialogFooter>
      <Button
        :disabled="!valid"
        @click="submit"
      >
        {{ initial ? 'حفظ التعديلات' : 'حفظ التاجر' }}
      </Button>
    </DialogFooter>
  </DialogContent>
</template>
