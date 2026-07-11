<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import type { AcceptableValue } from 'reka-ui'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { toast } from 'vue-sonner'
import { z } from 'zod'
import {
  AlertCircle,
  Copy,
  Eye,
  GitBranch,
  Layers,
  ListTree,
  MoreHorizontal,
  Plus,
  Tag,
  TextCursorInput,
  Users,
  Workflow,
} from 'lucide-vue-next'
import type { WorkflowVersion } from '@/types/models'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import WorkflowCanvas from '@/components/workflow/WorkflowCanvas.vue'
import WorkflowStageEditor from '@/components/workflow/WorkflowStageEditor.vue'
import WorkflowTransitionEditor from '@/components/workflow/WorkflowTransitionEditor.vue'
import WorkflowFieldDesigner from '@/components/workflow/WorkflowFieldDesigner.vue'
import WorkflowPublishPanel from '@/components/workflow/WorkflowPublishPanel.vue'
import StageRoutingEditor from '@/components/workflow/StageRoutingEditor.vue'
import WorkflowActionsCatalog from '@/components/workflow/WorkflowActionsCatalog.vue'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { ButtonGroup } from '@/components/ui/button-group'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
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

const {
  definitions,
  loading,
  error,
  fetchDefinitions,
  createDefinition,
  cloneVersion,
  deleteVersion,
  deleteDefinition,
  archiveVersion,
} = useWorkflows()

// UI-RBAC-001: gate the route itself, not just the in-template ScreenGuard
// wrappers. Without this, a user lacking workflow_designer access still mounts
// the full designer shell and sees an empty page (every panel is withheld by
// ScreenGuard). The screen middleware redirects them to /forbidden before the
// page renders; the ScreenGuard wrappers remain as per-capability defense.
definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'workflow_designer' })

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

function formatDate(iso: string | null): string {
  if (!iso) return '—'
  return new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' }).format(new Date(iso))
}

// ── Delete dialogs ───────────────────────────────────────────────────────
const deleteVersionDialogOpen = ref(false)
const deleteDefinitionDialogOpen = ref(false)
const archiveVersionDialogOpen = ref(false)
const archivingVersion = ref(false)
const deletingVersion = ref(false)
const deletingDefinition = ref(false)

const isLastPublishedVersion = computed(() => {
  if (!selectedDefinition.value || !selectedVersion.value) return false
  if (selectedVersion.value.state !== 'PUBLISHED') return false
  return selectedDefinition.value.versions.filter((v) => v.state === 'PUBLISHED').length === 1
})

function openArchiveVersionDialog() {
  archiveVersionDialogOpen.value = true
}

async function confirmArchiveVersion() {
  if (!selectedVersion.value) return
  archivingVersion.value = true
  try {
    const result = await archiveVersion(selectedVersion.value)
    archiveVersionDialogOpen.value = false
    if (result.warnings.some((warning) => warning.code === 'LAST_PUBLISHED_ARCHIVED')) {
      toast.warning(
        'تمت أرشفة آخر نسخة منشورة. لن يُنشأ طلب جديد على هذا المسار حتى نشر نسخة أخرى.',
      )
    } else {
      toast.success('تمت أرشفة النسخة بنجاح')
    }
    await fetchDefinitions()
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذر أرشفة النسخة'))
  } finally {
    archivingVersion.value = false
  }
}

function openDeleteVersionDialog() {
  deleteVersionDialogOpen.value = true
}

function openDeleteDefinitionDialog() {
  deleteDefinitionDialogOpen.value = true
}

async function confirmDeleteVersion() {
  if (!selectedVersion.value) return
  deletingVersion.value = true
  try {
    await deleteVersion(selectedVersion.value)
    deleteVersionDialogOpen.value = false
    toast.success('تم حذف النسخة بنجاح')
    await fetchDefinitions()
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذر حذف النسخة'))
  } finally {
    deletingVersion.value = false
  }
}

async function confirmDeleteDefinition() {
  if (!selectedDefinition.value) return
  deletingDefinition.value = true
  try {
    await deleteDefinition(selectedDefinition.value)
    deleteDefinitionDialogOpen.value = false
    toast.success('تم حذف مسار العمل بنجاح')
    await fetchDefinitions()
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذر حذف مسار العمل'))
  } finally {
    deletingDefinition.value = false
  }
}

// Designer tab definitions (label + icon), matching the settings-page nav style.
const designerTabs = [
  { value: 'stages', label: 'المراحل', icon: Layers },
  { value: 'routing', label: 'سير العملية', icon: Users },
  { value: 'transitions', label: 'الانتقالات', icon: GitBranch },
  { value: 'fields', label: 'الحقول', icon: TextCursorInput },
  { value: 'actions', label: 'الإجراءات', icon: Tag },
] as const

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
    <div class="mx-auto max-w-[1600px] space-y-6 py-2">
      <PageHeader
        title="مصمم مسارات العمل"
        subtitle="إنشاء وإدارة تعريفات مسارات العمل ونسخها ضمن تبويبات تصميم متخصصة"
        :breadcrumbs="[{ label: 'الرئيسية', to: '/dashboard' }, { label: 'مصمم مسارات العمل' }]"
      >
        <template #actions>
          <ScreenGuard screen="workflow_designer" capability="MANAGE">
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
        <!-- Designer summary card: definition/version context, counts, actions -->
        <Card class="border-0 shadow" aria-labelledby="designer-summary-heading">
          <CardHeader class="pb-2">
            <div class="flex flex-wrap items-center justify-between gap-4">
              <div class="flex flex-1 flex-wrap items-center gap-3">
                <div class="min-w-[220px]">
                  <CardTitle id="designer-summary-heading" class="text-base font-semibold">
                    {{ selectedDefinition?.name ?? 'اختر مسار العمل' }}
                    <span
                      v-if="selectedDefinition"
                      class="text-muted-foreground text-sm font-normal"
                    >
                      ({{ selectedDefinition.code }})
                    </span>
                  </CardTitle>
                  <CardDescription class="text-xs">
                    {{ formatDate(selectedVersion?.created_at ?? null) }}
                    ·
                    {{
                      selectedVersion?.published_at
                        ? formatDate(selectedVersion.published_at)
                        : 'غير منشورة'
                    }}
                  </CardDescription>
                </div>

                <Select
                  :model-value="String(selectedDefinitionId ?? '')"
                  @update:model-value="onDefinitionChange"
                >
                  <SelectTrigger aria-label="مسار العمل" size="sm" class="w-48">
                    <SelectValue placeholder="اختر مسار العمل" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="def in definitions" :key="def.id" :value="String(def.id)">
                      {{ def.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>

                <Select
                  :model-value="String(selectedVersionId ?? '')"
                  :disabled="!selectedDefinition"
                  @update:model-value="onVersionChange"
                >
                  <SelectTrigger aria-label="النسخة" size="sm" class="w-24">
                    <SelectValue placeholder="—" />
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

                <Badge
                  v-if="selectedVersion"
                  :class="stateBadgeClass(selectedVersion.state)"
                  class="shrink-0"
                >
                  {{ stateLabels[selectedVersion.state] }}
                </Badge>
              </div>

              <div v-if="selectedVersion" class="flex flex-wrap items-center gap-2">
                <ButtonGroup>
                  <Button
                    :variant="designerView === 'normal' ? 'default' : 'outline'"
                    size="sm"
                    @click="designerView = 'normal'"
                  >
                    <ListTree class="h-3.5 w-3.5" />
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
                </ButtonGroup>
                <ScreenGuard screen="workflow_designer" capability="MANAGE">
                  <Button
                    variant="outline"
                    size="sm"
                    :disabled="!['PUBLISHED', 'ARCHIVED'].includes(selectedVersion.state)"
                    @click="clone"
                  >
                    <Copy class="h-3.5 w-3.5" />
                    استنساخ
                  </Button>
                </ScreenGuard>
                <ScreenGuard screen="workflow_designer" capability="MANAGE">
                  <Button
                    v-if="selectedVersion.state === 'PUBLISHED'"
                    variant="outline"
                    size="sm"
                    @click="openArchiveVersionDialog"
                  >
                    أرشفة النسخة
                  </Button>
                </ScreenGuard>
                <ScreenGuard screen="workflow_designer" capability="MANAGE">
                  <Button variant="outline" size="sm" @click="openDeleteVersionDialog">
                    حذف النسخة
                  </Button>
                </ScreenGuard>
                <ScreenGuard screen="workflow_designer" capability="MANAGE">
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="ghost" size="icon" aria-label="إجراءات إضافية">
                        <MoreHorizontal class="h-4 w-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem
                        class="text-destructive"
                        @click="openDeleteDefinitionDialog"
                      >
                        حذف مسار العمل
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </ScreenGuard>
              </div>
            </div>

            <div v-if="selectedVersion" class="text-muted-foreground mt-2 text-xs">
              {{ selectedVersion.stages_count ?? 0 }} مراحل ·
              {{ selectedVersion.transitions_count ?? 0 }} انتقالات ·
              {{ selectedVersion.fields_count ?? 0 }} حقول
            </div>
          </CardHeader>
        </Card>

        <!-- Validate + publish panel (full width, own row) -->
        <WorkflowPublishPanel
          v-if="selectedVersion"
          :version="selectedVersion"
          @published="reload"
        />

        <div v-if="!selectedVersion" class="py-12">
          <Empty>
            <EmptyTitle>اختر مسار عمل ونسخة للبدء</EmptyTitle>
            <EmptyDescription> تظهر أدوات التصميم بعد اختيار النسخة من الأعلى. </EmptyDescription>
          </Empty>
        </div>

        <!-- View switch: normal (detailed) vs canvas -->
        <template v-if="selectedVersion">
          <!-- Read-only notice for non-editable (published/archived) versions -->
          <Alert
            v-if="!selectedVersionEditable"
            class="border-0 border-s-4 border-s-[var(--locked)] bg-[var(--locked)]/5"
          >
            <Eye class="h-4 w-4 text-[var(--locked)]" />
            <AlertTitle>نسخة للعرض فقط</AlertTitle>
            <AlertDescription>
              هذه النسخة منشورة أو مؤرشفة، لذلك يمكن عرضها فقط. استنسخ نسخة مسودة لإجراء تعديلات.
            </AlertDescription>
          </Alert>

          <template v-if="designerView === 'normal'">
            <!-- Two-column layout: right sidebar nav + left content (settings style) -->
            <Tabs
              default-value="stages"
              dir="rtl"
              orientation="vertical"
              class="flex flex-col gap-6 py-2 lg:flex-row"
            >
              <TabsList
                class="h-auto w-full shrink-0 flex-row flex-wrap justify-start gap-1 bg-transparent p-0 lg:w-52 lg:flex-col lg:justify-start"
              >
                <TabsTrigger
                  v-for="tab in designerTabs"
                  :key="tab.value"
                  :value="tab.value"
                  class="text-muted-foreground hover:bg-muted/50 hover:text-foreground data-[state=active]:bg-muted data-[state=active]:text-foreground flex w-auto items-center justify-start gap-2.5 rounded-lg border-0 px-3 py-2.5 text-sm font-medium shadow-none transition-colors data-[state=active]:shadow-none lg:w-full"
                >
                  <component :is="tab.icon" class="size-4 shrink-0" />
                  {{ tab.label }}
                </TabsTrigger>
              </TabsList>

              <div class="min-w-0 flex-1">
                <TabsContent value="stages" class="mt-0 space-y-6">
                  <WorkflowStageEditor :version="selectedVersion" />
                </TabsContent>

                <TabsContent value="routing" class="mt-0 space-y-6">
                  <StageRoutingEditor :version="selectedVersion" />
                </TabsContent>

                <TabsContent value="transitions" class="mt-0">
                  <WorkflowTransitionEditor :version="selectedVersion" />
                </TabsContent>

                <TabsContent value="fields" class="mt-0">
                  <WorkflowFieldDesigner :version="selectedVersion" />
                </TabsContent>

                <TabsContent value="actions" class="mt-0">
                  <WorkflowActionsCatalog />
                </TabsContent>
              </div>
            </Tabs>
          </template>

          <template v-else>
            <Card class="border-0 shadow">
              <CardContent>
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

      <AlertDialog v-model:open="archiveVersionDialogOpen">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>أرشفة النسخة المنشورة</AlertDialogTitle>
            <AlertDialogDescription>
              <template v-if="isLastPublishedVersion">
                هذه هي آخر نسخة منشورة لمسار العمل «{{ selectedDefinition?.name }}». بعد الأرشفة لن
                يُنشأ طلب جديد على هذا المسار حتى نشر نسخة أخرى. الطلبات الجارية على النسخة المثبتة
                تستمر دون تأثر.
              </template>
              <template v-else>
                سيتم أرشفة النسخة «v{{ selectedVersion?.version_number }}» مع الإبقاء على الطلبات
                المرتبطة بها.
              </template>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>إلغاء</AlertDialogCancel>
            <AlertDialogAction :disabled="archivingVersion" @click="confirmArchiveVersion">
              تأكيد الأرشفة
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog v-model:open="deleteVersionDialogOpen">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف النسخة</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم حذف النسخة «v{{ selectedVersion?.version_number }}» نهائياً.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>إلغاء</AlertDialogCancel>
            <AlertDialogAction :disabled="deletingVersion" @click="confirmDeleteVersion">
              تأكيد الحذف
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog v-model:open="deleteDefinitionDialogOpen">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف مسار العمل</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم حذف مسار العمل «{{ selectedDefinition?.name }}» وكل نسخه نهائياً.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>إلغاء</AlertDialogCancel>
            <AlertDialogAction :disabled="deletingDefinition" @click="confirmDeleteDefinition">
              تأكيد الحذف
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  </ScreenGuard>
</template>
