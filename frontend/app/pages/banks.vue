<script setup lang="ts">
import type { ColumnDef, PaginationState } from '@tanstack/vue-table'
import { computed, ref, reactive, watch, onMounted, h } from 'vue'
import {
  AlertTriangle,
  Download, MoreHorizontal, Plus, Printer, Search, SearchX, X,
} from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { UserRole } from '../types/enums'
import type { Bank } from '../types/models'
import { useBanks } from '../composables/useBanks'
import { useAuthStore } from '../stores/auth.store'
import type { CreateBankPayload, UpdateBankPayload } from '../composables/useBanks'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import DataTable from '@/components/ui/data-table/DataTable.vue'
import { DataTablePagination } from '@/components/ui/data-table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field'
import { Alert, AlertDescription } from '@/components/ui/alert'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'
import { Checkbox } from '@/components/ui/checkbox'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: [UserRole.CBY_ADMIN, UserRole.BANK_ADMIN],
})

const { fetchBanksPaginated, createBank, updateBank } = useBanks()
const auth = useAuthStore()
const isBankAdmin = computed(() => auth.user?.role === UserRole.BANK_ADMIN)

const banks = ref<Bank[]>([])
const banksMeta = ref<{ last_page: number; total: number } | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const query = ref('')

const showModal = ref(false)
const editingBank = ref<Bank | null>(null)
const viewingBank = ref<Bank | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)

interface BankForm {
  name_ar: string
  name_en: string
  code: string
  is_active: boolean
}

const form = reactive<BankForm>({
  name_ar: '',
  name_en: '',
  code: '',
  is_active: true,
})

const formErrors = reactive<Partial<Record<keyof BankForm, string>>>({})

// Server-side paginated load (same pattern as the requests page).
async function loadBanks() {
  loading.value = true
  error.value = null
  try {
    const q = query.value.trim()
    const result = await fetchBanksPaginated({
      page: urlBankPage.value,
      per_page: urlBankPageSize.value,
      ...(q ? { search: q } : {}),
    })
    banks.value = result.data
    banksMeta.value = { last_page: result.meta.last_page, total: result.meta.total }
  }
  catch {
    error.value = 'تعذّر تحميل قائمة البنوك.'
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  editingBank.value = null
  form.name_ar = ''
  form.name_en = ''
  form.code = ''
  form.is_active = true
  clearErrors()
  showModal.value = true
}

function openEdit(bank: Bank) {
  editingBank.value = bank
  form.name_ar = bank.name_ar
  form.name_en = bank.name_en
  form.code = bank.code
  form.is_active = bank.is_active
  clearErrors()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  formError.value = null
}

function clearErrors() {
  formErrors.name_ar = undefined
  formErrors.name_en = undefined
  formErrors.code = undefined
  formError.value = null
}

function validateForm(): boolean {
  clearErrors()
  let valid = true
  if (!form.name_ar.trim()) { formErrors.name_ar = 'الاسم بالعربية مطلوب'; valid = false }
  if (!isBankAdmin.value && !form.name_en.trim()) { formErrors.name_en = 'الاسم بالإنجليزية مطلوب'; valid = false }
  if (!isBankAdmin.value && !form.code.trim()) { formErrors.code = 'الرمز مطلوب'; valid = false }
  return valid
}

async function saveBank() {
  if (!validateForm()) return
  saving.value = true
  formError.value = null
  try {
    if (editingBank.value) {
      const payload: UpdateBankPayload = isBankAdmin.value
        ? { name: form.name_ar.trim(), name_ar: form.name_ar.trim() }
        : {
            name: form.name_ar.trim(),
            name_ar: form.name_ar.trim(),
            name_en: form.name_en.trim(),
            code: form.code.trim().toUpperCase(),
            is_active: form.is_active,
          }
      await updateBank(editingBank.value.id, payload)
    }
    else {
      const payload: CreateBankPayload = {
        name: form.name_ar.trim(),
        name_ar: form.name_ar.trim(),
        name_en: form.name_en.trim(),
        code: form.code.trim().toUpperCase(),
        is_active: form.is_active,
      }
      await createBank(payload)
    }
    closeModal()
    await loadBanks()
  }
  catch (err: unknown) {
    const e = err as { data?: { errors?: Record<string, string[]>, message?: string } }
    if (e.data?.errors) {
      const errs = e.data.errors
      if (errs.name_ar?.[0]) formErrors.name_ar = errs.name_ar[0]
      if (errs.name_en?.[0]) formErrors.name_en = errs.name_en[0]
      if (errs.code?.[0]) formErrors.code = errs.code[0]
    }
    else {
      formError.value = e.data?.message ?? 'حدث خطأ أثناء الحفظ.'
    }
  }
  finally {
    saving.value = false
  }
}

const rowSelection = ref<Record<string, boolean>>({})
const selectedCount = computed(() => Object.values(rowSelection.value).filter(Boolean).length)

function clearSelection() {
  rowSelection.value = {}
}

// URL-driven client-side pagination (same UX as the requests page).
const DEFAULT_BANK_PAGE_SIZE = 20
const route = useRoute()
const router = useRouter()
const urlBankPage = computed(() => Number(route.query.page ?? 1))
const urlBankPageSize = computed(() => Number(route.query.perPage ?? DEFAULT_BANK_PAGE_SIZE))

const bankPagination = computed<PaginationState>(() => ({
  pageIndex: urlBankPage.value - 1,
  pageSize: urlBankPageSize.value,
}))

function onBankPaginationChange(updater: PaginationState | ((old: PaginationState) => PaginationState)) {
  const next = typeof updater === 'function' ? updater(bankPagination.value) : updater
  router.push({
    query: {
      ...route.query,
      page: next.pageIndex === 0 ? undefined : String(next.pageIndex + 1),
      perPage: next.pageSize === DEFAULT_BANK_PAGE_SIZE ? undefined : String(next.pageSize),
    },
  })
}

// Re-fetch from the server whenever the page or page size changes.
watch([urlBankPage, urlBankPageSize], () => loadBanks())

// Debounced server-side search — resets to page 1 via the URL.
let bankSearchTimeout: ReturnType<typeof setTimeout> | null = null
watch(query, () => {
  if (bankSearchTimeout) clearTimeout(bankSearchTimeout)
  bankSearchTimeout = setTimeout(() => {
    if (urlBankPage.value !== 1) router.push({ query: { ...route.query, page: undefined } })
    else loadBanks()
  }, 350)
})

function activeStatusCell(isActive: boolean, activeLabel = 'نشط', inactiveLabel = 'موقوف') {
  const color = isActive ? 'var(--color-success)' : 'var(--color-locked)'
  const label = isActive ? activeLabel : inactiveLabel
  const paths = isActive
    ? [
        h('path', { d: 'M22 11.08V12a10 10 0 1 1-5.93-9.14' }),
        h('polyline', { points: '22 4 12 14.01 9 11.01' }),
      ]
    : [
        h('circle', { cx: '12', cy: '12', r: '10' }),
        h('line', { x1: '15', y1: '9', x2: '9', y2: '15' }),
        h('line', { x1: '9', y1: '9', x2: '15', y2: '15' }),
      ]
  return h('span', { class: 'inline-flex items-center gap-1.5 whitespace-nowrap' }, [
    h('svg', {
      class: 'shrink-0', style: { color }, width: 15, height: 15,
      viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor',
      'stroke-width': '2.5', 'stroke-linecap': 'round', 'stroke-linejoin': 'round',
    }, paths),
    h('span', { class: 'text-sm font-medium text-foreground' }, label),
  ])
}

const columns: ColumnDef<Bank>[] = [
  {
    id: 'select',
    header: ({ table }) =>
      h(Checkbox, {
        'modelValue': table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() ? 'indeterminate' : false),
        'onUpdate:modelValue': (v: boolean | 'indeterminate') => table.toggleAllPageRowsSelected(!!v),
        'aria-label': 'تحديد الكل',
      }),
    cell: ({ row }) =>
      h('div', { onClick: (e: Event) => e.stopPropagation() }, [
        h(Checkbox, {
          'modelValue': row.getIsSelected(),
          'onUpdate:modelValue': (v: boolean | 'indeterminate') => row.toggleSelected(!!v),
          'aria-label': 'تحديد البنك',
        }),
      ]),
    enableSorting: false,
    enableHiding: false,
  },
  {
    id: 'name',
    header: 'البنك',
    cell: ({ row }) => h('div', { class: 'flex flex-col gap-0.5' }, [
      h('button', {
        type: 'button',
        class: 'text-sm font-medium text-start hover:underline underline-offset-2 cursor-pointer focus-visible:outline-none focus-visible:underline',
        title: 'معاينة سريعة',
        onClick: (e: Event) => { e.stopPropagation(); viewingBank.value = row.original },
      }, row.original.name_ar),
      h('span', { class: 'text-xs text-muted-foreground', dir: 'ltr' }, row.original.name_en),
    ]),
  },
  {
    accessorKey: 'code',
    header: 'الرمز',
    cell: ({ row }) => h('code', { class: 'rounded bg-muted px-2 py-0.5 text-xs font-mono' }, row.original.code),
  },
  {
    accessorKey: 'is_active',
    header: 'الحالة',
    cell: ({ row }) => activeStatusCell(row.original.is_active),
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const bank = row.original
      return h(DropdownMenu, {}, {
        default: () => [
          h(DropdownMenuTrigger, { asChild: true }, {
            default: () => h(Button, {
              variant: 'ghost', size: 'icon', class: 'h-8 w-8',
            }, {
              default: () => [
                h('span', { class: 'sr-only' }, 'فتح القائمة'),
                h(MoreHorizontal, { class: 'h-4 w-4' }),
              ],
            }),
          }),
          h(DropdownMenuContent, { align: 'end' }, {
            default: () => [
              h(DropdownMenuItem, { onClick: () => (viewingBank.value = bank) }, () => 'عرض'),
              h(DropdownMenuItem, { onClick: () => openEdit(bank) }, () => 'تعديل'),
            ],
          }),
        ],
      })
    },
  },
]

onMounted(loadBanks)
</script>

<template>
  <div class="flex flex-col gap-6">
    <PageHeader
      :title="isBankAdmin ? 'بيانات البنك' : 'إدارة البنوك'"
      :subtitle="isBankAdmin ? 'عرض معلومات بنكك' : 'إدارة قائمة البنوك التجارية المتاحة على المنصة'"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: isBankAdmin ? 'بيانات البنك' : 'إدارة البنوك' }]"
    >
      <template v-if="!isBankAdmin" #actions>
        <Button size="sm" class="h-8" @click="openCreate">
          <Plus class="h-4 w-4" />
          <span class="hidden lg:inline">إضافة بنك</span>
        </Button>
      </template>
    </PageHeader>

    <!-- Error State -->
    <Alert v-if="error" variant="destructive">
      <AlertTriangle class="h-4 w-4" />
      <AlertDescription>{{ error }}</AlertDescription>
    </Alert>

    <!-- Toolbar: bulk (when selected) OR search (default) -->
    <div v-if="selectedCount > 0" class="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2">
      <span class="text-sm font-medium text-primary">{{ selectedCount }} محدد</span>
      <div class="mx-2 h-4 w-px bg-border" />
      <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs">
        <Download class="h-3.5 w-3.5" />
        تصدير
      </Button>
      <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs">
        <Printer class="h-3.5 w-3.5" />
        طباعة
      </Button>
      <Button
        variant="ghost"
        size="sm"
        class="ms-auto h-7 gap-1 text-xs text-muted-foreground"
        @click="clearSelection"
      >
        <X class="h-3.5 w-3.5" />
        إلغاء التحديد
      </Button>
    </div>

    <div v-else class="flex items-center gap-2">
      <div class="relative min-w-[220px] flex-1">
        <Search class="absolute inset-e-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="query"
          placeholder="بحث بالاسم أو الرمز..."
          class="h-8 rounded-md pe-9 text-sm"
        />
      </div>
    </div>

    <!-- Table -->
    <div class="relative flex flex-col gap-4">
      <DataTable
        :data="banks"
        :columns="columns"
        :loading="loading"
        :page-count="banksMeta?.last_page ?? 1"
        :pagination="bankPagination"
        :row-selection="rowSelection"
        @update:pagination="onBankPaginationChange"
        @update:row-selection="(v) => rowSelection = v"
        :row-class="'group/row'"
      >
        <template #empty>
          <Empty class="min-h-[280px] rounded-xl border border-dashed bg-muted/20">
            <EmptyHeader>
              <div class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                <SearchX class="size-5" />
              </div>
              <EmptyTitle>لا توجد نتائج</EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>جرّب تغيير البحث لعرض البنوك.</EmptyDescription>
            </EmptyContent>
          </Empty>
        </template>
        <template #pagination="{ table }">
          <DataTablePagination :table="table" :total-rows="banksMeta?.total" />
        </template>
      </DataTable>
    </div>

    <!-- Dialog Modal -->
    <Dialog v-model:open="showModal" @update:open="(open) => !open && closeModal()">
      <DialogContent class="sm:max-w-[420px]" >
        <DialogHeader>
          <DialogTitle>{{ editingBank ? 'تعديل بيانات البنك' : 'إضافة بنك جديد' }}</DialogTitle>
        </DialogHeader>

        <Alert v-if="formError" variant="destructive">
          <AlertTriangle class="h-4 w-4" />
          <AlertDescription>{{ formError }}</AlertDescription>
        </Alert>

        <div class="flex flex-col gap-4">
          <Field :data-invalid="!!formErrors.name_ar">
            <FieldLabel>الاسم بالعربية <span class="text-destructive">*</span></FieldLabel>
            <Input v-model="form.name_ar" :aria-invalid="!!formErrors.name_ar" placeholder="مثال: البنك التجاري اليمني" type="text" />
            <FieldDescription v-if="formErrors.name_ar" class="text-destructive">{{ formErrors.name_ar }}</FieldDescription>
          </Field>

          <Field v-if="!isBankAdmin" :data-invalid="!!formErrors.name_en">
            <FieldLabel>الاسم بالإنجليزية <span class="text-destructive">*</span></FieldLabel>
            <Input v-model="form.name_en" :aria-invalid="!!formErrors.name_en" placeholder="e.g. Yemen Commercial Bank" type="text" />
            <FieldDescription v-if="formErrors.name_en" class="text-destructive">{{ formErrors.name_en }}</FieldDescription>
          </Field>

          <Field v-if="!isBankAdmin" :data-invalid="!!formErrors.code">
            <FieldLabel>الرمز <span class="text-destructive">*</span></FieldLabel>
            <Input v-model="form.code" :aria-invalid="!!formErrors.code" placeholder="مثال: YCB" type="text" maxlength="20" />
            <FieldDescription v-if="formErrors.code" class="text-destructive">{{ formErrors.code }}</FieldDescription>
          </Field>

          <Field v-if="!isBankAdmin" class="flex items-center gap-3">
            <FieldLabel class="flex-1">نشط</FieldLabel>
            <Switch v-model:checked="form.is_active" />
          </Field>
        </div>

        <DialogFooter class="gap-2">
          <Button variant="outline" :disabled="saving" @click="closeModal">إلغاء</Button>
          <Button :disabled="saving" @click="saveBank">{{ saving ? 'جارٍ الحفظ…' : 'حفظ' }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Quick-view Dialog -->
    <Dialog :open="Boolean(viewingBank)" @update:open="v => !v && (viewingBank = null)">
      <DialogContent v-if="viewingBank"  class="sm:max-w-md">
        <DialogHeader class="pb-2">
          <DialogTitle class="flex items-center gap-2 text-base">
            {{ viewingBank.name_ar }}
          </DialogTitle>
          <DialogDescription  class="text-xs">{{ viewingBank.name_en }}</DialogDescription>
        </DialogHeader>

        <div class="grid grid-cols-2 gap-x-4 gap-y-3 py-1 text-sm">
          <div class="space-y-0.5">
            <p class="text-xs text-muted-foreground">الرمز</p>
            <code class="rounded bg-muted px-2 py-0.5 text-xs font-mono">{{ viewingBank.code }}</code>
          </div>
          <div class="space-y-0.5">
            <p class="text-xs text-muted-foreground">الحالة</p>
            <Badge :class="viewingBank.is_active ? 'bg-[var(--severity-green)]/10 text-[var(--severity-green)] border-[var(--severity-green)]/30 border' : 'bg-[var(--severity-red)]/10 text-[var(--severity-red)] border-[var(--severity-red)]/30 border'">
              {{ viewingBank.is_active ? 'نشط' : 'موقوف' }}
            </Badge>
          </div>
          <div v-if="viewingBank.license_number" class="space-y-0.5">
            <p class="text-xs text-muted-foreground">رقم الترخيص</p>
            <p class="font-medium">{{ viewingBank.license_number }}</p>
          </div>
          <div v-if="viewingBank.entity_type" class="space-y-0.5">
            <p class="text-xs text-muted-foreground">نوع الجهة</p>
            <p class="font-medium">{{ viewingBank.entity_type }}</p>
          </div>
        </div>

        <Separator />

        <DialogFooter class="gap-2">
          <Button variant="outline" size="sm" @click="() => { viewingBank = null }">إغلاق</Button>
          <Button size="sm" @click="() => { openEdit(viewingBank!); viewingBank = null }">تعديل البيانات</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
