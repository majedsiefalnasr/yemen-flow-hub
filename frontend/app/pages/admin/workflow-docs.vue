<script setup lang="ts">
import type { ColumnDef, VisibilityState } from '@tanstack/vue-table'
import { h } from 'vue'
import {
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Download,
  Plus,
  Search,
  SearchX,
} from 'lucide-vue-next'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { Input } from '@/components/ui/input'
import PageHeader from '@/components/layout/PageHeader.vue'
import { DataTableViewOptions } from '@/components/ui/data-table'
import DataTable from '@/components/ui/data-table/DataTable.vue'
import { useTableExport } from '@/composables/useTableExport'
import { useTableKeyboard } from '@/composables/useTableKeyboard'
import { ROUTE_ROLE_MAP } from '@/constants/workflow'
import { UserRole } from '@/types/enums'
import type { DocumentType } from '@/types/models'
import { useDocumentTypes, type CreateDocumentTypePayload, type UpdateDocumentTypePayload } from '@/composables/useDocumentTypes'
import { useAuthStore } from '@/stores/auth.store'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/admin/workflow-docs'],
})

const authStore = useAuthStore()
const currentUser = computed(() => authStore.user)
const { fetchDocumentTypes, createDocumentType, updateDocumentType } = useDocumentTypes()
const { exportToCSV } = useTableExport()
const { notify, error: toastError } = useToast()

const docTypes = ref<DocumentType[]>([])
const loadingDocTypes = ref(false)
const saving = ref(false)
const query = ref('')
const searchInputRef = ref<HTMLInputElement | null>(null)
const columnVisibility = ref<VisibilityState>({
  sort_order: false,
})

useTableKeyboard(searchInputRef, {
  onEscape: () => {
    query.value = ''
  },
})

const draft = reactive<{ name_ar: string; name_en: string; slug: string; is_required: boolean }>({
  name_ar: '',
  name_en: '',
  slug: '',
  is_required: true,
})

// Impact preview state
interface ImpactPreview {
  item: DocumentType
  changeType: 'required' | 'deactivate'
  title: string
  description: string
  consequence: string
}
const impactPreview = ref<ImpactPreview | null>(null)
const impactDialogOpen = ref(false)

onMounted(async () => {
  loadingDocTypes.value = true
  try {
    docTypes.value = await fetchDocumentTypes()
  }
  finally {
    loadingDocTypes.value = false
  }
})

const canAdd = computed(() => Boolean(draft.name_ar.trim() && draft.slug.trim()))
const filteredDocTypes = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return docTypes.value
  return docTypes.value.filter(item =>
    item.name_ar.toLowerCase().includes(q)
    || item.name_en.toLowerCase().includes(q)
    || item.slug.toLowerCase().includes(q),
  )
})

async function addRule() {
  if (!canAdd.value) {
    toastError('الاسم والمعرّف مطلوبان')
    return
  }
  saving.value = true
  try {
    const payload: CreateDocumentTypePayload = {
      slug: draft.slug.trim(),
      name_ar: draft.name_ar.trim(),
      name_en: draft.name_en.trim(),
      is_required: draft.is_required,
      is_active: true,
      sort_order: docTypes.value.length + 1,
    }
    const created = await createDocumentType(payload)
    docTypes.value = [...docTypes.value, created]
    draft.name_ar = ''
    draft.name_en = ''
    draft.slug = ''
    draft.is_required = true
    notify('تمت إضافة نوع المستند')
  }
  catch {
    toastError('تعذر حفظ نوع المستند. أعد المحاولة بعد قليل.')
  }
  finally {
    saving.value = false
  }
}

// High-risk change: toggling is_required or deactivating — show impact preview first
function requestToggleRequired(item: DocumentType) {
  const becomingRequired = !item.is_required
  impactPreview.value = {
    item,
    changeType: 'required',
    title: becomingRequired
      ? `جعل "${item.name_ar}" مطلوباً`
      : `جعل "${item.name_ar}" اختيارياً`,
    description: becomingRequired
      ? 'ستُصبح هذه الوثيقة مطلوبة في جميع الطلبات الجارية والمستقبلية.'
      : 'ستُصبح هذه الوثيقة اختيارية. الطلبات التي لم ترفعها ستستمر في التقدم دون انتظارها.',
    consequence: becomingRequired
      ? 'الطلبات النشطة التي لم ترفع هذه الوثيقة بعد قد تتوقف عند مرحلة المراجعة.'
      : 'الطلبات التي طُلبت منها هذه الوثيقة سابقاً لن تتأثر بأثر رجعي.',
  }
  impactDialogOpen.value = true
}

function requestToggleActive(item: DocumentType) {
  const becomingActive = !item.is_active
  if (becomingActive) {
    // Reactivating is low-risk — no confirmation needed
    void applyToggleActive(item)
    return
  }
  impactPreview.value = {
    item,
    changeType: 'deactivate',
    title: `تعطيل "${item.name_ar}"`,
    description: 'سيتوقف ظهور هذا النوع من المستندات في واجهة الرفع للطلبات الجديدة.',
    consequence: 'الطلبات النشطة التي تحتوي على هذه الوثيقة مسبقاً لن تتأثر، لكن لن يُطلب هذا المستند في أي طلب جديد.',
  }
  impactDialogOpen.value = true
}

async function confirmImpactChange() {
  if (!impactPreview.value) return
  impactDialogOpen.value = false
  const { item, changeType } = impactPreview.value
  if (changeType === 'required') {
    await applyToggleRequired(item)
  }
  else {
    await applyToggleActive(item)
  }
  impactPreview.value = null
}

function cancelImpactChange() {
  impactDialogOpen.value = false
  impactPreview.value = null
}

async function applyToggleRequired(item: DocumentType) {
  try {
    const payload: UpdateDocumentTypePayload = {
      slug: item.slug,
      name_ar: item.name_ar,
      name_en: item.name_en,
      is_required: !item.is_required,
      is_active: item.is_active,
      sort_order: item.sort_order,
    }
    const updated = await updateDocumentType(item.id, payload)
    docTypes.value = docTypes.value.map(d => d.id === item.id ? updated : d)
  }
  catch {
    toastError('فشل تحديث الحالة')
  }
}

async function applyToggleActive(item: DocumentType) {
  try {
    const payload: UpdateDocumentTypePayload = {
      slug: item.slug,
      name_ar: item.name_ar,
      name_en: item.name_en,
      is_required: item.is_required,
      is_active: !item.is_active,
      sort_order: item.sort_order,
    }
    const updated = await updateDocumentType(item.id, payload)
    docTypes.value = docTypes.value.map(d => d.id === item.id ? updated : d)
  }
  catch {
    toastError('فشل تحديث الحالة')
  }
}

const columns: ColumnDef<DocumentType>[] = [
  {
    id: 'document_type',
    header: 'نوع المستند',
    cell: ({ row }) =>
      h('div', { class: 'min-w-[220px]' }, [
        h('div', { class: 'text-sm font-medium' }, row.original.name_ar),
        h('div', { class: 'mt-0.5 flex items-center gap-2 text-xs text-muted-foreground' }, [
          h(Badge, {
            variant: 'outline',
            class: 'font-mono text-[10px]',
          }, () => row.original.slug),
          row.original.name_en ? h('span', {}, `· ${row.original.name_en}`) : null,
        ]),
      ]),
  },
  {
    accessorKey: 'is_required',
    header: 'الإلزام',
    cell: ({ row }) =>
      h('div', { class: 'flex items-center gap-2' }, [
        h(Switch, {
          modelValue: row.original.is_required,
          'onUpdate:modelValue': () => requestToggleRequired(row.original),
        }),
        h('span', { class: 'text-xs' }, row.original.is_required ? 'مطلوب' : 'اختياري'),
      ]),
  },
  {
    accessorKey: 'is_active',
    header: 'التفعيل',
    cell: ({ row }) =>
      h('div', { class: 'flex items-center gap-2' }, [
        h(Switch, {
          modelValue: row.original.is_active,
          'onUpdate:modelValue': () => requestToggleActive(row.original),
        }),
        h('span', { class: 'text-xs' }, row.original.is_active ? 'مفعّل' : 'معطّل'),
      ]),
  },
  {
    accessorKey: 'sort_order',
    header: 'الترتيب',
    cell: ({ row }) =>
      h('span', { class: 'text-sm tabular-nums text-muted-foreground' }, String(row.original.sort_order)),
  },
]

const WORKFLOW_DOC_COLUMN_LABELS: Record<string, string> = {
  document_type: 'نوع المستند',
  is_required: 'الإلزام',
  is_active: 'التفعيل',
  sort_order: 'الترتيب',
}

function exportCurrentRules() {
  if (!filteredDocTypes.value.length) return
  const stamp = new Date().toISOString().slice(0, 10)
  exportToCSV(
    filteredDocTypes.value as unknown as Record<string, unknown>[],
    [
      { key: 'name_ar', label: 'الاسم العربي' },
      { key: 'name_en', label: 'الاسم الإنجليزي' },
      { key: 'slug', label: 'المعرّف' },
      {
        key: 'is_required',
        label: 'الإلزام',
        format: (_value: unknown, row: DocumentType) => row.is_required ? 'مطلوب' : 'اختياري',
      },
      {
        key: 'is_active',
        label: 'التفعيل',
        format: (_value: unknown, row: DocumentType) => row.is_active ? 'مفعّل' : 'معطّل',
      },
      { key: 'sort_order', label: 'الترتيب' },
    ] as any,
    `workflow-doc-types-${stamp}`,
  )
}
</script>

<template>
  <div v-if="currentUser?.role === UserRole.CBY_ADMIN">
    <PageHeader
      title="أنواع المستندات"
      subtitle="حدّد أنواع المستندات المطلوبة والاختيارية في دورة حياة الطلب"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'أنواع المستندات' }]"
    />

    <Card class="mb-6 border-0 p-5 shadow">
      <div class="mb-3 font-semibold">
        إضافة نوع مستند جديد
      </div>
      <div class="grid gap-3 md:grid-cols-4">
        <Input
          v-model="draft.name_ar"
          placeholder="الاسم بالعربية *"
        />
        <Input
          v-model="draft.name_en"
          
          placeholder="Name in English"
        />
        <Input
          v-model="draft.slug"
          
          placeholder="المعرّف (slug) *"
        />
        <div class="flex items-center gap-2 rounded-md border px-3">
          <Switch
            :model-value="draft.is_required"
            @update:model-value="value => draft.is_required = Boolean(value)"
          />
          <span class="text-sm">مطلوب</span>
        </div>
      </div>
      <Button
        class="mt-3"
        :disabled="!canAdd || saving"
        @click="addRule"
      >
        <Plus class="ms-1 h-4 w-4" />
        إضافة
      </Button>
    </Card>

    <div class="mb-3 flex items-center gap-2">
      <div class="relative min-w-[240px] flex-1 max-w-md">
        <Search class="absolute inset-e-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          ref="searchInputRef"
          v-model="query"
          class="h-8 rounded-md pe-9 text-sm"
          placeholder="بحث بالاسم أو المعرّف..."
        />
      </div>
      <Button
        variant="outline"
        size="sm"
        class="h-8 gap-1.5"
        :disabled="filteredDocTypes.length === 0"
        @click="exportCurrentRules"
      >
        <Download class="h-4 w-4" />
        تصدير
      </Button>
    </div>

    <Card class="border-0 shadow p-4">
      <DataTable
        :data="filteredDocTypes"
        :columns="columns"
        :loading="loadingDocTypes"
        :column-visibility="columnVisibility"
        @update:column-visibility="(v) => columnVisibility = v"
      >
        <template #toolbar="{ table }">
          <div class="mb-2 flex justify-end">
            <DataTableViewOptions
              :table="table"
              :column-labels="WORKFLOW_DOC_COLUMN_LABELS"
            />
          </div>
        </template>
        <template #empty>
          <Empty class="min-h-[180px] rounded-xl border border-dashed bg-muted/20">
            <EmptyHeader>
              <div class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                <SearchX class="size-5" />
              </div>
              <EmptyTitle>لا توجد أنواع مستندات مطابقة</EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>جرّب تعديل معايير البحث لعرض النتائج.</EmptyDescription>
            </EmptyContent>
          </Empty>
        </template>
        <template #pagination="{ table }">
          <div class="flex items-center justify-between border-t">
            <p class="text-sm text-muted-foreground">{{ filteredDocTypes.length }} نوع</p>
            <div class="flex items-center gap-4">
              <p class="text-sm font-medium whitespace-nowrap">
                صفحة {{ table.getState().pagination.pageIndex + 1 }} من {{ table.getPageCount() }}
              </p>
              <div class="flex items-center gap-1">
                <Button
                  variant="outline"
                  size="icon"
                  class="hidden h-8 w-8 lg:flex"
                  :disabled="!table.getCanPreviousPage()"
                  @click="table.setPageIndex(0)"
                >
                  <span class="sr-only">الصفحة الأولى</span>
                  <ChevronsRight class="h-4 w-4" />
                </Button>
                <Button
                  variant="outline"
                  size="icon"
                  class="h-8 w-8"
                  :disabled="!table.getCanPreviousPage()"
                  @click="table.previousPage()"
                >
                  <span class="sr-only">الصفحة السابقة</span>
                  <ChevronRight class="h-4 w-4" />
                </Button>
                <Button
                  variant="outline"
                  size="icon"
                  class="h-8 w-8"
                  :disabled="!table.getCanNextPage()"
                  @click="table.nextPage()"
                >
                  <span class="sr-only">الصفحة التالية</span>
                  <ChevronLeft class="h-4 w-4" />
                </Button>
                <Button
                  variant="outline"
                  size="icon"
                  class="hidden h-8 w-8 lg:flex"
                  :disabled="!table.getCanNextPage()"
                  @click="table.setPageIndex(table.getPageCount() - 1)"
                >
                  <span class="sr-only">الصفحة الأخيرة</span>
                  <ChevronsLeft class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </div>
        </template>
      </DataTable>
    </Card>

    <!-- Impact preview dialog for high-risk document rule changes -->
    <AlertDialog v-model:open="impactDialogOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <div class="mb-2 flex items-center gap-2 text-[var(--severity-amber)]">
            <AlertTriangle class="h-5 w-5 flex-shrink-0" aria-hidden="true" />
            <span class="text-sm font-semibold">معاينة الأثر قبل الحفظ</span>
          </div>
          <AlertDialogTitle>{{ impactPreview?.title }}</AlertDialogTitle>
          <AlertDialogDescription class="space-y-2">
            <p>{{ impactPreview?.description }}</p>
            <p class="rounded-md border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/5 p-3 text-xs text-foreground">
              <strong>التأثير المتوقع:</strong> {{ impactPreview?.consequence }}
            </p>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="cancelImpactChange">
            إلغاء — لا تغيير
          </AlertDialogCancel>
          <AlertDialogAction
            @click="confirmImpactChange"
          >
            تأكيد التغيير
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
