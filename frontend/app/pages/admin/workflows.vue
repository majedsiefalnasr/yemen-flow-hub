<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { toTypedSchema } from '@vee-validate/zod'
import { useForm } from 'vee-validate'
import { toast } from 'vue-sonner'
import { z } from 'zod'
import { AlertCircle, Copy, Plus } from 'lucide-vue-next'
import type { WorkflowVersion } from '@/types/models'
import ScreenGuard from '@/components/security/ScreenGuard.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import WorkflowStageEditor from '@/components/workflow/WorkflowStageEditor.vue'
import WorkflowTransitionEditor from '@/components/workflow/WorkflowTransitionEditor.vue'
import WorkflowFieldDesigner from '@/components/workflow/WorkflowFieldDesigner.vue'
import WorkflowProcessGraph from '@/components/workflow/WorkflowProcessGraph.vue'
import WorkflowPublishPanel from '@/components/workflow/WorkflowPublishPanel.vue'
import WorkflowActionsCatalog from '@/components/workflow/WorkflowActionsCatalog.vue'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { useWorkflows } from '@/composables/useWorkflows'

const { definitions, loading, error, fetchDefinitions, createDefinition, cloneVersion } =
  useWorkflows()

const createDialogOpen = ref(false)
const expandedVersionId = ref<number | null>(null)

function toggleVersion(versionId: number) {
  expandedVersionId.value = expandedVersionId.value === versionId ? null : versionId
}

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
    await createDefinition({
      code: values.code,
      name: values.name,
      description: values.description || undefined,
    })
    toast.success('تم إنشاء مسار العمل')
    createDialogOpen.value = false
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر إنشاء مسار العمل'))
  }
})

async function clone(version: WorkflowVersion) {
  try {
    await cloneVersion(version)
    toast.success('تم إنشاء نسخة مسودة جديدة')
  } catch (cause) {
    toast.error(extractApiErrorMessage(cause, 'تعذّر استنساخ النسخة'))
  }
}

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

onMounted(() => fetchDefinitions())
</script>

<template>
  <ScreenGuard screen="workflow_designer">
    <div class="space-y-6">
      <PageHeader title="مصمم مسارات العمل" description="إنشاء وإدارة تعريفات مسارات العمل ونسخها">
        <template #actions>
          <ScreenGuard screen="workflow_designer" capability="CREATE">
            <Button @click="openCreate"><Plus class="h-4 w-4" />إنشاء مسار عمل</Button>
          </ScreenGuard>
        </template>
      </PageHeader>

      <Alert v-if="error" variant="destructive" role="alert">
        <AlertCircle class="h-4 w-4" />
        <AlertTitle>تعذّر تحميل مسارات العمل</AlertTitle>
        <AlertDescription>{{ error }}</AlertDescription>
        <AlertAction>
          <Button variant="outline" size="sm" @click="fetchDefinitions()">إعادة المحاولة</Button>
        </AlertAction>
      </Alert>

      <div v-if="loading" class="grid gap-4">
        <Skeleton v-for="n in 3" :key="n" class="h-32 w-full rounded-xl" />
      </div>

      <Empty v-else-if="definitions.length === 0 && !error">
        <EmptyMedia variant="icon">
          <Plus />
        </EmptyMedia>
        <EmptyHeader>
          <EmptyTitle>لا توجد مسارات عمل</EmptyTitle>
          <EmptyDescription>ابدأ بإنشاء أول تعريف لمسار العمل.</EmptyDescription>
        </EmptyHeader>
      </Empty>

      <div v-else class="grid gap-4">
        <Card v-for="definition in definitions" :key="definition.id" class="border-0 shadow">
          <CardHeader class="pb-2">
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <CardTitle class="text-sm font-semibold">{{ definition.name }}</CardTitle>
                <span class="text-muted-foreground font-mono text-xs">{{ definition.code }}</span>
              </div>
              <Badge v-if="!definition.is_active" variant="secondary">غير نشط</Badge>
            </div>
          </CardHeader>
          <CardContent class="p-4 pt-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead class="text-right">النسخة</TableHead>
                  <TableHead class="text-right">الحالة</TableHead>
                  <TableHead class="text-right">إجراء</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-for="version in definition.versions" :key="version.id">
                  <TableCell class="font-mono">v{{ version.version_number }}</TableCell>
                  <TableCell>
                    <Badge :class="stateBadgeClass(version.state)">
                      {{ stateLabels[version.state] }}
                    </Badge>
                  </TableCell>
                  <TableCell @click.stop>
                    <Button size="sm" variant="ghost" @click="toggleVersion(version.id)">
                      {{ expandedVersionId === version.id ? 'إخفاء المراحل' : 'المراحل' }}
                    </Button>
                    <ScreenGuard screen="workflow_designer" capability="CREATE">
                      <Button
                        v-if="version.state === 'PUBLISHED'"
                        size="sm"
                        variant="outline"
                        @click="clone(version)"
                      >
                        <Copy class="h-3.5 w-3.5" />استنساخ
                      </Button>
                      <span v-else-if="!version.is_editable" class="text-muted-foreground text-xs">
                        مقفلة
                      </span>
                    </ScreenGuard>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>

            <template v-for="version in definition.versions" :key="`stages-${version.id}`">
              <div
                v-if="expandedVersionId === version.id"
                class="border-border mt-4 space-y-6 border-t pt-4"
              >
                <WorkflowPublishPanel :version="version" @published="fetchDefinitions()" />
                <WorkflowStageEditor :version="version" />
                <WorkflowTransitionEditor :version="version" />
                <WorkflowFieldDesigner :version="version" />
                <WorkflowProcessGraph :version="version" />
              </div>
            </template>
          </CardContent>
        </Card>
      </div>

      <WorkflowActionsCatalog />

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
