<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import type { AcceptableValue } from 'reka-ui'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { toast } from 'vue-sonner'
import { z } from 'zod'
import { AlertCircle, Copy, Eye, GitBranch, Plus, Workflow } from 'lucide-vue-next'
import type { WorkflowVersion } from '@/types/models'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import WorkflowCanvas from '@/components/workflow/WorkflowCanvas.vue'
import WorkflowStageEditor from '@/components/workflow/WorkflowStageEditor.vue'
import WorkflowTransitionEditor from '@/components/workflow/WorkflowTransitionEditor.vue'
import WorkflowFieldDesigner from '@/components/workflow/WorkflowFieldDesigner.vue'
import WorkflowProcessGraph from '@/components/workflow/WorkflowProcessGraph.vue'
import WorkflowPublishPanel from '@/components/workflow/WorkflowPublishPanel.vue'
import StageRoutingEditor from '@/components/workflow/StageRoutingEditor.vue'
import WorkflowActionsCatalog from '@/components/workflow/WorkflowActionsCatalog.vue'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { useWorkflows } from '@/composables/useWorkflows'

const { definitions, loading, error, fetchDefinitions, createDefinition, cloneVersion } =
  useWorkflows()

// ── Version picker state ────────────────────────────────────────────────
const selectedDefinitionId = ref<number | null>(null)
const selectedVersionId = ref<number | null>(null)

const selectedDefinition = computed(
  () => definitions.value.find((d) => d.id === selectedDefinitionId.value) ?? null,
)
const selectedVersion = computed<WorkflowVersion | null>(() => {
  if (!selectedDefinition.value || !selectedVersionId.value) return null
  return selectedDefinition.value.versions.find((v) => v.id === selectedVersionId.value) ?? null
})

// Auto-select the first definition + its newest version on load.
watch(
  definitions,
  (next) => {
    if (next.length === 0) return
    const first = next[0]
    if (
      selectedDefinitionId.value === null ||
      !next.some((d) => d.id === selectedDefinitionId.value)
    ) {
      if (first) selectedDefinitionId.value = first.id
    }
    const def = next.find((d) => d.id === selectedDefinitionId.value)
    if (def && def.versions.length > 0) {
      const newest = def.versions[0]
      const needPick =
        selectedVersionId.value === null ||
        !def.versions.some((v) => v.id === selectedVersionId.value)
      if (needPick && newest) {
        selectedVersionId.value = newest.id
      }
    } else {
      selectedVersionId.value = null
    }
  },
  { immediate: true },
)

function onDefinitionChange(value: AcceptableValue) {
  selectedDefinitionId.value = Number(value)
  const def = definitions.value.find((d) => d.id === Number(value))
  selectedVersionId.value = def?.versions[0]?.id ?? null
}

function onVersionChange(value: AcceptableValue) {
  selectedVersionId.value = Number(value)
  designerView.value = 'normal'
}

// ── Designer view switch ────────────────────────────────────────────────
const designerView = ref<'normal' | 'canvas'>('normal')
const selectedVersionEditable = computed(
  () => selectedVersion.value?.state === 'DRAFT' && selectedVersion.value.is_editable,
)

async function clone() {
  if (!selectedVersion.value) return
  try {
    const clone = await cloneVersion(selectedVersion.value)
    selectedVersionId.value = clone.id
    toast.success('تم إنشاء نسخة مسودة جديدة')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر استنساخ النسخة'))
  }
}

// ── State badges ────────────────────────────────────────────────────────
function stateBadgeClass(state: WorkflowVersion['state']): string {
  if (state === 'PUBLISHED') {
    return 'border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 text-[var(--severity-green)]'
  }
  if (state === 'ARCHIVED') {
    return 'border border-[var(--locked)]/30 bg-[var(--locked)]/10 text-[var(--locked)]'
  }
  return 'border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]'
}

const stateLabels: Record<WorkflowVersion['state'], string> = {
  DRAFT: 'مسودة',
  PUBLISHED: 'منشورة',
  ARCHIVED: 'مؤرشفة',
}

// ── Create-definition dialog ────────────────────────────────────────────
const createDialogOpen = ref(false)

const definitionSchema = toTypedSchema(
  z.object({
    code: z
      .string()
      .min(1, 'الرمز مطلوب')
      .regex(/^[A-Za-z0-9_-]+$/, 'يسمح بالحروف والأرقام والشرطات فقط'),
    name: z.string().min(1, 'الاسم مطلوب'),
    description: z.string().optional(),
  }),
)

const definitionForm = useForm({ validationSchema: definitionSchema })

function openCreate() {
  definitionForm.resetForm({ values: { code: '', name: '', description: '' } })
  createDialogOpen.value = true
}

const onCreate = definitionForm.handleSubmit(async (values) => {
  try {
    const created = await createDefinition({
      code: values.code,
      name: values.name,
      description: values.description || undefined,
    })
    selectedDefinitionId.value = created.id
    selectedVersionId.value = created.versions[0]?.id ?? null
    toast.success('تم إنشاء مسار العمل')
    createDialogOpen.value = false
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر إنشاء مسار العمل'))
  }
})

async function reload() {
  await fetchDefinitions()
}

onMounted(reload)
</script>

<template>
  <ScreenGuard screen="workflow_designer">
    <div class="mx-auto max-w-[1600px] space-y-6 p-6">
      <PageHeader
        title="مصمم مسارات العمل"
        subtitle="إنشاء وإدارة تعريفات مسارات العمل ونسخها ضمن تبويبات تصميم متخصصة"
        :breadcrumbs="[{ label: 'الرئيسية', to: '/dashboard' }, { label: 'مصمم مسارات العمل' }]"
      >
        <template #actions>
          <ScreenGuard screen="workflow_designer" capability="CREATE">
            <Button @click="openCreate">
              <Plus class="h-4 w-4" />
              إنشاء مسار عمل
            </Button>
          </ScreenGuard>
        </template>
      </PageHeader>

      <Alert v-if="error" variant="destructive" role="alert">
        <AlertCircle class="h-4 w-4" />
        <AlertTitle>تعذّر تحميل مسارات العمل</AlertTitle>
        <AlertDescription>{{ error }}</AlertDescription>
        <AlertAction>
          <Button variant="outline" size="sm" @click="reload">إعادة المحاولة</Button>
        </AlertAction>
      </Alert>

      <div v-if="loading" class="grid gap-4">
        <Skeleton class="h-32 w-full rounded-xl" />
        <Skeleton class="h-10 w-full rounded-lg" />
        <Skeleton class="h-96 w-full rounded-xl" />
      </div>

      <Empty v-else-if="definitions.length === 0 && !error">
        <EmptyMedia variant="icon">
          <Workflow />
        </EmptyMedia>
        <EmptyHeader>
          <EmptyTitle>لا توجد مسارات عمل</EmptyTitle>
          <EmptyDescription>ابدأ بإنشاء أول تعريف لمسار العمل.</EmptyDescription>
        </EmptyHeader>
      </Empty>

      <template v-else>
        <!-- Version picker card -->
        <Card class="border-0 shadow">
          <CardContent class="flex flex-wrap items-end gap-4 p-4">
            <div class="flex min-w-[220px] flex-1 flex-col gap-1.5">
              <label class="text-muted-foreground text-xs font-medium" for="def-select">
                مسار العمل
              </label>
              <Select
                :model-value="String(selectedDefinitionId ?? '')"
                @update:model-value="onDefinitionChange"
              >
                <SelectTrigger id="def-select">
                  <SelectValue placeholder="اختر مسار العمل" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="def in definitions" :key="def.id" :value="String(def.id)">
                    {{ def.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex min-w-[160px] flex-1 flex-col gap-1.5">
              <label class="text-muted-foreground text-xs font-medium" for="ver-select">
                النسخة
              </label>
              <Select
                :model-value="String(selectedVersionId ?? '')"
                :disabled="!selectedDefinition"
                @update:model-value="onVersionChange"
              >
                <SelectTrigger id="ver-select">
                  <SelectValue placeholder="اختر النسخة" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="version in selectedDefinition?.versions ?? []"
                    :key="version.id"
                    :value="String(version.id)"
                  >
                    v{{ version.version_number }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex flex-col gap-1.5">
              <span class="text-muted-foreground text-xs font-medium">الحالة</span>
              <Badge v-if="selectedVersion" :class="stateBadgeClass(selectedVersion.state)">
                {{ stateLabels[selectedVersion.state] }}
              </Badge>
              <Badge v-else variant="secondary">—</Badge>
            </div>

            <div class="ms-auto flex items-center gap-2">
              <ScreenGuard screen="workflow_designer" capability="CREATE">
                <Button
                  variant="outline"
                  size="sm"
                  :disabled="!selectedVersion || selectedVersion.state !== 'PUBLISHED'"
                  @click="clone"
                >
                  <Copy class="h-3.5 w-3.5" />
                  استنساخ
                </Button>
              </ScreenGuard>
            </div>
          </CardContent>
        </Card>

        <div v-if="!selectedVersion" class="py-12">
          <Empty>
            <EmptyTitle>اختر مسار عمل ونسخة للبدء</EmptyTitle>
            <EmptyDescription> تظهر أدوات التصميم بعد اختيار النسخة من الأعلى. </EmptyDescription>
          </Empty>
        </div>

        <!-- View switch: normal (detailed) vs canvas -->
        <template v-if="selectedVersion">
          <!-- Read-only notice for non-editable (published/archived) versions -->
          <Alert v-if="!selectedVersionEditable" class="border-muted bg-muted/30">
            <Eye class="h-4 w-4" />
            <AlertTitle>نسخة للعرض فقط</AlertTitle>
            <AlertDescription>
              هذه النسخة منشورة أو مؤرشفة، لذلك يمكن عرضها فقط. استنسخ نسخة مسودة لإجراء تعديلات.
            </AlertDescription>
          </Alert>
          <div class="flex gap-1">
            <Button
              :variant="designerView === 'normal' ? 'default' : 'outline'"
              size="sm"
              @click="designerView = 'normal'"
            >
              تفصيلي
            </Button>
            <Button
              :variant="designerView === 'canvas' ? 'default' : 'outline'"
              size="sm"
              @click="designerView = 'canvas'"
            >
              <GitBranch class="h-3.5 w-3.5" />
              لوحة
            </Button>
          </div>

          <template v-if="designerView === 'normal'">
            <Tabs default-value="stages" dir="rtl">
              <TabsList class="flex w-full flex-wrap">
                <TabsTrigger value="stages">المراحل</TabsTrigger>
                <TabsTrigger value="routing">سير العملية</TabsTrigger>
                <TabsTrigger value="transitions">الانتقالات</TabsTrigger>
                <TabsTrigger value="fields">الحقول</TabsTrigger>
                <TabsTrigger value="actions">الإجراءات</TabsTrigger>
              </TabsList>

              <TabsContent value="stages" class="mt-4 space-y-6">
                <WorkflowPublishPanel :version="selectedVersion" @published="reload" />
                <WorkflowStageEditor :version="selectedVersion" />
              </TabsContent>

              <TabsContent value="routing" class="mt-4 space-y-6">
                <StageRoutingEditor :version="selectedVersion" />
                <WorkflowProcessGraph :version="selectedVersion" />
              </TabsContent>

              <TabsContent value="transitions" class="mt-4">
                <WorkflowTransitionEditor :version="selectedVersion" />
              </TabsContent>

              <TabsContent value="fields" class="mt-4">
                <WorkflowFieldDesigner :version="selectedVersion" />
              </TabsContent>

              <TabsContent value="actions" class="mt-4">
                <WorkflowActionsCatalog />
              </TabsContent>
            </Tabs>
          </template>

          <template v-else>
            <Card class="border-0 shadow">
              <CardContent class="p-4">
                <WorkflowCanvas :version="selectedVersion" />
              </CardContent>
            </Card>
          </template>
        </template>
      </template>

      <Dialog v-model:open="createDialogOpen">
        <DialogContent class="max-w-lg">
          <DialogHeader>
            <DialogTitle>إنشاء مسار عمل</DialogTitle>
            <DialogDescription>سيتم إنشاء أول نسخة مسودة تلقائياً.</DialogDescription>
          </DialogHeader>

          <form class="flex flex-col gap-4" @submit="onCreate">
            <FormField v-slot="{ componentField }" name="code">
              <FormItem>
                <FormLabel>الرمز</FormLabel>
                <FormControl>
                  <Input v-bind="componentField" placeholder="import_financing" dir="ltr" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <FormField v-slot="{ componentField }" name="name">
              <FormItem>
                <FormLabel>الاسم</FormLabel>
                <FormControl>
                  <Input v-bind="componentField" placeholder="تمويل الاستيراد" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <FormField v-slot="{ componentField }" name="description">
              <FormItem>
                <FormLabel>الوصف</FormLabel>
                <FormControl>
                  <Textarea v-bind="componentField" rows="3" placeholder="وصف اختياري" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <DialogFooter>
              <Button type="button" variant="outline" @click="createDialogOpen = false">
                إلغاء
              </Button>
              <Button type="submit" :disabled="definitionForm.isSubmitting.value">حفظ</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  </ScreenGuard>
</template>
