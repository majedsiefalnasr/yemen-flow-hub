<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { UserRole } from '@/types/enums'
import type { DocumentType } from '@/types/models'
import { useDocumentTypes, type CreateDocumentTypePayload, type UpdateDocumentTypePayload } from '@/composables/useDocumentTypes'
import { useAuthStore } from '@/stores/auth.store'

const authStore = useAuthStore()
const currentUser = computed(() => authStore.user)
const { fetchDocumentTypes, createDocumentType, updateDocumentType } = useDocumentTypes()
const { toast } = useToast()

const docTypes = ref<DocumentType[]>([])
const saving = ref(false)

const draft = reactive<{ name_ar: string; name_en: string; slug: string; is_required: boolean }>({
  name_ar: '',
  name_en: '',
  slug: '',
  is_required: true,
})

onMounted(async () => {
  docTypes.value = await fetchDocumentTypes()
})

const canAdd = computed(() => Boolean(draft.name_ar.trim() && draft.slug.trim()))

async function addRule() {
  if (!canAdd.value) {
    toast({ title: 'الاسم والمعرّف مطلوبان', variant: 'destructive' })
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
    toast({ title: 'تمت إضافة نوع المستند' })
  }
  catch {
    toast({ title: 'حدث خطأ', variant: 'destructive' })
  }
  finally {
    saving.value = false
  }
}

async function toggleRequired(item: DocumentType) {
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
    toast({ title: 'فشل تحديث الحالة', variant: 'destructive' })
  }
}

async function toggleActive(item: DocumentType) {
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
    toast({ title: 'فشل تحديث الحالة', variant: 'destructive' })
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

    <Card class="mb-6 border-0 p-5 shadow-card">
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

    <Card class="divide-y border-0 shadow-card">
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
            @update:model-value="() => toggleRequired(item)"
          />
          <span class="text-xs">{{ item.is_required ? 'مطلوب' : 'اختياري' }}</span>
        </div>
        <div class="flex items-center gap-2 px-2">
          <Switch
            :model-value="item.is_active"
            @update:model-value="() => toggleActive(item)"
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
  </div>
</template>
