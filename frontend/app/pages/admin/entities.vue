<script setup lang="ts">
import { Building2, Edit, Eye, Plus, Power, Search } from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { UserRole } from '@/types/enums'
import type { Bank } from '@/types/models'
import { useBanks, type CreateBankPayload, type UpdateBankPayload } from '@/composables/useBanks'
import { useAuthStore } from '@/stores/auth.store'

type EntityForm = {
  name_ar: string
  name_en: string
  license_number: string
  code: string
  is_active: boolean
  adminName: string
  adminEmail: string
}

const authStore = useAuthStore()
const currentUser = computed(() => authStore.user)
const { fetchBanks, createBank, updateBank } = useBanks()
const { notify, error: toastError } = useToast()

const query = ref('')
const createOpen = ref(false)
const editing = ref<Bank | null>(null)
const viewing = ref<Bank | null>(null)
const saving = ref(false)
const banks = ref<Bank[]>([])

const form = reactive<EntityForm>({
  name_ar: '',
  name_en: '',
  license_number: '',
  code: '',
  is_active: true,
  adminName: '',
  adminEmail: '',
})

onMounted(async () => {
  banks.value = await fetchBanks()
})

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return banks.value
  return banks.value.filter(b =>
    b.name_ar.toLowerCase().includes(q)
    || b.name_en.toLowerCase().includes(q)
    || (b.license_number ?? '').toLowerCase().includes(q)
    || b.code.toLowerCase().includes(q),
  )
})

const emailValid = computed(() => !form.adminEmail.trim() || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.adminEmail.trim()))
const formValid = computed(() =>
  form.name_ar.trim().length > 0
  && form.code.trim().length > 0
  && emailValid.value
  && (Boolean(editing.value) || (form.adminName.trim().length > 0 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.adminEmail.trim()))),
)

function resetForm(initial?: Bank) {
  form.name_ar = initial?.name_ar ?? ''
  form.name_en = initial?.name_en ?? ''
  form.license_number = initial?.license_number ?? ''
  form.code = initial?.code ?? ''
  form.is_active = initial?.is_active ?? true
  form.adminName = ''
  form.adminEmail = ''
}

function openCreate() {
  editing.value = null
  resetForm()
  createOpen.value = true
}

function openEdit(bank: Bank) {
  editing.value = bank
  resetForm(bank)
}

function closeForm() {
  createOpen.value = false
  editing.value = null
  resetForm()
}

async function saveEntity() {
  if (!formValid.value) return
  saving.value = true
  try {
    if (editing.value) {
      const payload: UpdateBankPayload = {
        name_ar: form.name_ar.trim(),
        name_en: form.name_en.trim(),
        code: form.code.trim(),
        license_number: form.license_number.trim() || undefined,
        is_active: form.is_active,
      }
      const updated = await updateBank(editing.value.id, payload)
      banks.value = banks.value.map(b => b.id === editing.value!.id ? updated : b)
      notify('تم حفظ التعديلات')
    }
    else {
      const payload: CreateBankPayload = {
        name_ar: form.name_ar.trim(),
        name_en: form.name_en.trim(),
        code: form.code.trim(),
        license_number: form.license_number.trim() || undefined,
        is_active: form.is_active,
      }
      const created = await createBank(payload)
      banks.value = [...banks.value, created]
      notify(`تم إضافة "${created.name_ar}"`)
    }
    closeForm()
  }
  catch {
    toastError('حدث خطأ، يرجى المحاولة مجدداً')
  }
  finally {
    saving.value = false
  }
}

async function toggleStatus(bank: Bank) {
  try {
    const payload: UpdateBankPayload = {
      name_ar: bank.name_ar,
      name_en: bank.name_en,
      code: bank.code,
      license_number: bank.license_number ?? undefined,
      is_active: !bank.is_active,
    }
    const updated = await updateBank(bank.id, payload)
    banks.value = banks.value.map(b => b.id === bank.id ? updated : b)
    notify(updated.is_active ? `تم تفعيل ${bank.name_ar}` : `تم إيقاف ${bank.name_ar}`)
  }
  catch {
    toastError('فشل تغيير الحالة')
  }
}
</script>

<template>
  <div v-if="currentUser?.role === UserRole.CBY_ADMIN">
    <PageHeader
      title="إدارة البنوك التجارية"
      subtitle="إنشاء بنوك جديدة، عرض البيانات، تعديلها وتغيير حالة التفعيل"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'إدارة البنوك' }]"
    >
      <template #actions>
        <Button @click="openCreate">
          <Plus class="ms-1 h-4 w-4" />
          بنك جديد
        </Button>
      </template>
    </PageHeader>

    <Card class="mb-4 border-0 p-4 shadow">
      <div class="relative max-w-md">
        <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-600" />
        <Input
          v-model="query"
          class="pe-10"
          placeholder="بحث بالاسم أو رقم الترخيص أو الكود..."
        />
      </div>
    </Card>

    <Card class="overflow-hidden border-0 shadow">
      <div class="overflow-x-auto">
        <Table class="w-full min-w-[720px] text-sm">
          <TableHeader class="bg-gray-50/40 text-xs text-gray-600">
            <TableRow class="text-end">
              <TableHead class="px-4 py-3">
                الجهة
              </TableHead>
              <TableHead class="px-4 py-3">
                رقم الترخيص
              </TableHead>
              <TableHead class="px-4 py-3">
                الكود
              </TableHead>
              <TableHead class="px-4 py-3">
                الحالة
              </TableHead>
              <TableHead class="sticky start-0 z-10 bg-gray-50/40 px-4 py-3 text-start shadow-[6px_0_8px_-6px_rgba(0,0,0,0.12)]">
                إجراءات
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="bank in filtered"
              :key="bank.id"
              class="border-t hover:bg-gray-50/30"
            >
              <TableCell class="px-4 py-3 font-medium">
                <div class="flex items-center gap-2">
                  <div class="grid h-8 w-8 place-items-center rounded-lg bg-blue-600/10 text-blue-600">
                    <Building2 class="h-4 w-4" />
                  </div>
                  <div>
                    <div>{{ bank.name_ar }}</div>
                    <div class="text-[11px] text-gray-600">
                      {{ bank.name_en }}
                    </div>
                  </div>
                </div>
              </TableCell>
              <TableCell class="px-4 py-3 font-mono text-xs">
                {{ bank.license_number ?? '—' }}
              </TableCell>
              <TableCell class="px-4 py-3 font-mono text-xs">
                {{ bank.code }}
              </TableCell>
              <TableCell class="px-4 py-3">
                <Badge :class="bank.is_active ? 'border-0 bg-green-50/15 text-green-700' : 'border-0 bg-red-700/15 text-red-700'">
                  {{ bank.is_active ? 'نشط' : 'موقوف' }}
                </Badge>
              </TableCell>
              <TableCell class="sticky start-0 z-10 bg-white px-4 py-3 shadow-[6px_0_8px_-6px_rgba(0,0,0,0.12)]">
                <div class="flex justify-end gap-1">
                  <Button
                    size="sm"
                    variant="ghost"
                    @click="viewing = bank"
                  >
                    <Eye class="ms-1 h-3.5 w-3.5" />
                    عرض
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    @click="openEdit(bank)"
                  >
                    <Edit class="ms-1 h-3.5 w-3.5" />
                    تعديل
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    :class="bank.is_active ? 'text-red-700' : 'text-green-700'"
                    @click="toggleStatus(bank)"
                  >
                    <Power class="ms-1 h-3.5 w-3.5" />
                    {{ bank.is_active ? 'إيقاف' : 'تفعيل' }}
                  </Button>
                </div>
              </TableCell>
            </TableRow>
            <TableRow v-if="filtered.length === 0">
              <TableCell
                colspan="5"
                class="px-4 py-8 text-center text-sm text-gray-600"
              >
                لا توجد نتائج.
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </Card>

    <Dialog
      :open="createOpen || Boolean(editing)"
      @update:open="value => !value && closeForm()"
    >
      <DialogContent
        dir="rtl"
        class="sm:max-w-md"
      >
        <DialogHeader>
          <DialogTitle>
            {{ editing ? 'تعديل بيانات البنك' : 'إضافة بنك جديد' }}
          </DialogTitle>
        </DialogHeader>

        <div class="space-y-3 py-2">
          <div class="space-y-1.5">
            <Label>اسم البنك (عربي) *</Label>
            <Input v-model="form.name_ar" />
          </div>
          <div class="space-y-1.5">
            <Label>اسم البنك (إنجليزي)</Label>
            <Input
              v-model="form.name_en"
              dir="ltr"
            />
          </div>
          <div class="space-y-1.5">
            <Label>كود البنك *</Label>
            <Input
              v-model="form.code"
              dir="ltr"
              placeholder="YBRD"
            />
          </div>
          <div class="space-y-1.5">
            <Label>رقم الترخيص</Label>
            <Input
              v-model="form.license_number"
              dir="ltr"
              placeholder="BNK-004"
            />
          </div>
          <div class="space-y-1.5">
            <Label>الحالة</Label>
            <div class="flex gap-2">
              <Button
                type="button"
                :variant="form.is_active ? 'default' : 'outline'"
                size="sm"
                @click="form.is_active = true"
              >
                نشط
              </Button>
              <Button
                type="button"
                :variant="!form.is_active ? 'default' : 'outline'"
                size="sm"
                @click="form.is_active = false"
              >
                موقوف
              </Button>
            </div>
          </div>

          <div
            v-if="!editing"
            class="mt-2 border-t pt-3"
          >
            <div class="mb-1 text-sm font-semibold">
              حساب مدير البنك <span class="text-red-700">*</span>
            </div>
            <p class="mb-3 text-xs text-gray-600">
              يُنشأ حساب المدير الأول للبنك تلقائياً ويُستخدم لتسجيل الدخول وإضافة باقي المستخدمين.
            </p>
            <div class="space-y-3">
              <div class="space-y-1.5">
                <Label>اسم المدير *</Label>
                <Input
                  v-model="form.adminName"
                  placeholder="مثال: محمد علي"
                />
              </div>
              <div class="space-y-1.5">
                <Label>البريد الإلكتروني للمدير *</Label>
                <Input
                  v-model="form.adminEmail"
                  type="email"
                  dir="ltr"
                  placeholder="admin@bank.ye"
                />
                <p
                  v-if="!emailValid"
                  class="text-xs text-red-700"
                >
                  صيغة البريد غير صحيحة
                </p>
              </div>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button
            :disabled="!formValid || saving"
            @click="saveEntity"
          >
            {{ editing ? 'حفظ التعديلات' : 'إضافة' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog
      :open="Boolean(viewing)"
      @update:open="value => !value && (viewing = null)"
    >
      <DialogContent
        v-if="viewing"
        dir="rtl"
        class="sm:max-w-md"
      >
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Building2 class="h-5 w-5 text-blue-600" />
            {{ viewing.name_ar }}
          </DialogTitle>
          <DialogDescription>
            تفاصيل البنك
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-3 py-2 text-sm">
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-gray-600">الاسم الإنجليزي</span>
            <span class="font-medium">{{ viewing.name_en }}</span>
          </div>
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-gray-600">الكود</span>
            <span class="font-mono font-medium">{{ viewing.code }}</span>
          </div>
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-gray-600">رقم الترخيص</span>
            <span class="font-mono font-medium">{{ viewing.license_number ?? '—' }}</span>
          </div>
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-gray-600">الحالة</span>
            <span class="font-medium">{{ viewing.is_active ? 'نشط' : 'موقوف' }}</span>
          </div>
          <div
            v-if="viewing.user_count != null"
            class="flex items-center justify-between border-b pb-2"
          >
            <span class="text-gray-600">عدد المستخدمين</span>
            <span class="font-medium">{{ viewing.user_count }}</span>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>
