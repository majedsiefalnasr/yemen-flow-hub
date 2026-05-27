<script setup lang="ts">
import { AlertTriangle, Plus, Trash2 } from 'lucide-vue-next'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import PageHeader from '@/components/layout/PageHeader.vue'
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
const { notify, error: toastError } = useToast()

const docTypes = ref<DocumentType[]>([])
const saving = ref(false)

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
  docTypes.value = await fetchDocumentTypes()
})

const canAdd = computed(() => Boolean(draft.name_ar.trim() && draft.slug.trim()))

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
    toastError('حدث خطأ')
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
          dir="ltr"
          placeholder="Name in English"
        />
        <Input
          v-model="draft.slug"
          dir="ltr"
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

    <Card class="divide-y border-0 shadow">
      <div
        v-if="docTypes.length === 0"
        class="p-8 text-center text-sm text-muted-foreground"
      >
        لا توجد أنواع مستندات.
      </div>
      <div
        v-for="item in docTypes"
        :key="item.id"
        class="flex flex-wrap items-center gap-3 p-4"
      >
        <div class="min-w-[200px] flex-1">
          <div class="text-sm font-medium">
            {{ item.name_ar }}
          </div>
          <div class="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
            <Badge
              variant="outline"
              class="font-mono text-[10px]"
            >
              {{ item.slug }}
            </Badge>
            <span v-if="item.name_en">· {{ item.name_en }}</span>
          </div>
        </div>
        <div class="flex items-center gap-2 px-2">
          <Switch
            :model-value="item.is_required"
            @update:model-value="() => requestToggleRequired(item)"
          />
          <span class="text-xs">{{ item.is_required ? 'مطلوب' : 'اختياري' }}</span>
        </div>
        <div class="flex items-center gap-2 px-2">
          <Switch
            :model-value="item.is_active"
            @update:model-value="() => requestToggleActive(item)"
          />
          <span class="text-xs">{{ item.is_active ? 'مفعّل' : 'معطّل' }}</span>
        </div>
        <Button
          variant="ghost"
          size="icon"
          class="text-muted-foreground"
          disabled
          title="الحذف غير متاح عبر الواجهة"
        >
          <Trash2 class="h-4 w-4" />
        </Button>
      </div>
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
            class="bg-[var(--severity-amber)] text-white hover:bg-[var(--severity-amber)]/90"
            @click="confirmImpactChange"
          >
            تأكيد التغيير
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
