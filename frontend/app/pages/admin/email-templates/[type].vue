<script setup lang="ts">
import { ArrowRight, Eye, RefreshCw, Save, ShieldCheck } from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Separator } from '@/components/ui/separator'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { useEmailTemplates } from '@/composables/useEmailTemplates'
import { useToast } from '@/composables/use-toast'
import { UserRole } from '@/types/enums'
import {
  formatNotificationTemplateDate,
  notificationTemplateLabel,
  type NotificationTemplate,
  type NotificationTemplatePayload,
  type NotificationTemplatePreview,
} from '@/types/notifications'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: [UserRole.CBY_ADMIN],
})

const route = useRoute()
const router = useRouter()
const { success: toastSuccess, error: toastError } = useToast()
const { fetchTemplate, updateTemplate, previewTemplate, extractFieldErrors, extractMessage } =
  useEmailTemplates()

const type = computed(() => String(route.params.type ?? ''))
const template = ref<NotificationTemplate | null>(null)
const form = reactive<NotificationTemplatePayload>({ subject: '', body: '' })
const cleanSnapshot = ref<NotificationTemplatePayload>({ subject: '', body: '' })
const fieldErrors = ref<Record<string, string[]>>({})
const preview = ref<NotificationTemplatePreview | null>(null)
const loading = ref(false)
const saving = ref(false)
const previewing = ref(false)
const error = ref<string | null>(null)
const subjectInputRef = ref<HTMLInputElement | null>(null)
const bodyTextareaRef = ref<HTMLTextAreaElement | null>(null)
const lastFocusedField = ref<'subject' | 'body'>('body')

const templateTitle = computed(() => notificationTemplateLabel(type.value))
const isDirty = computed(
  () => form.subject !== cleanSnapshot.value.subject || form.body !== cleanSnapshot.value.body,
)

function syncFormFromTemplate(source: NotificationTemplate) {
  form.subject = source.active?.subject ?? ''
  form.body = source.active?.body ?? ''
  cleanSnapshot.value = { subject: form.subject, body: form.body }
}

onMounted(() => {
  void loadTemplate()
})

async function loadTemplate() {
  loading.value = true
  error.value = null
  try {
    template.value = await fetchTemplate(type.value)
    syncFormFromTemplate(template.value)
    preview.value = null
    fieldErrors.value = {}
  } catch (err) {
    error.value = extractMessage(err, 'تعذّر تحميل قالب البريد الإلكتروني.')
  } finally {
    loading.value = false
  }
}

async function saveTemplate() {
  if (!isDirty.value || saving.value) return
  saving.value = true
  error.value = null
  fieldErrors.value = {}
  try {
    template.value = await updateTemplate(type.value, { ...form })
    syncFormFromTemplate(template.value)
    toastSuccess('تم حفظ القالب كإصدار جديد.')
  } catch (err) {
    fieldErrors.value = extractFieldErrors(err)
    toastError(extractMessage(err, 'تعذّر حفظ القالب. راجع البيانات ثم أعد المحاولة.'))
  } finally {
    saving.value = false
  }
}

async function renderPreview() {
  previewing.value = true
  error.value = null
  fieldErrors.value = {}
  try {
    preview.value = await previewTemplate(type.value, { ...form })
  } catch (err) {
    fieldErrors.value = extractFieldErrors(err)
    toastError(extractMessage(err, 'تعذّر إنشاء المعاينة.'))
  } finally {
    previewing.value = false
  }
}

function insertVariable(variable: string) {
  const token = `{{${variable}}}`
  const field = lastFocusedField.value
  const target = field === 'subject' ? subjectInputRef.value : bodyTextareaRef.value
  const current = form[field]

  if (!target) {
    form[field] = current + token
    return
  }

  const start = target.selectionStart ?? current.length
  const end = target.selectionEnd ?? start
  form[field] = current.slice(0, start) + token + current.slice(end)

  nextTick(() => {
    target.focus()
    const cursor = start + token.length
    target.setSelectionRange(cursor, cursor)
  })
}

function formatVariableToken(variable: string): string {
  return `{{${variable}}}`
}

function bindSubjectRef(el: unknown) {
  const element = resolveInputElement(el)
  subjectInputRef.value = element instanceof HTMLInputElement ? element : null
}

function bindBodyRef(el: unknown) {
  const element = resolveInputElement(el)
  bodyTextareaRef.value = element instanceof HTMLTextAreaElement ? element : null
}

function resolveInputElement(el: unknown): HTMLInputElement | HTMLTextAreaElement | null {
  if (!el) return null
  if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) return el
  const candidate = (el as { $el?: unknown }).$el
  if (candidate instanceof HTMLInputElement || candidate instanceof HTMLTextAreaElement) {
    return candidate
  }
  return null
}

function firstFieldError(field: 'subject' | 'body'): string | null {
  return fieldErrors.value[field]?.[0] ?? null
}
</script>

<template>
  <div dir="rtl">
    <PageHeader
      :title="templateTitle"
      subtitle="تحرير موضوع ونص Markdown مع معاينة المصدر المخزن والبريد النهائي"
      :breadcrumbs="[
        { label: 'الرئيسية', to: '/dashboard' },
        { label: 'قوالب البريد الإلكتروني', to: '/admin/email-templates' },
        { label: templateTitle },
      ]"
    />

    <div class="mb-4 flex items-center justify-between gap-3">
      <Button variant="outline" size="sm" @click="router.push('/admin/email-templates')">
        <ArrowRight class="me-1 h-4 w-4" />
        رجوع للقائمة
      </Button>
      <Badge
        class="border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 text-[var(--severity-green)]"
      >
        <ShieldCheck class="me-1 h-3.5 w-3.5" />
        محرر خاضع للتدقيق
      </Badge>
    </div>

    <Alert v-if="error" variant="destructive" class="mb-6" role="alert">
      <AlertTitle>تعذّر التحميل</AlertTitle>
      <AlertDescription>{{ error }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="loadTemplate">
          <RefreshCw class="me-1 h-4 w-4" />
          إعادة المحاولة
        </Button>
      </AlertAction>
    </Alert>

    <div v-if="loading" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
      <Skeleton class="h-[520px] rounded-xl" />
      <Skeleton class="h-[520px] rounded-xl" />
    </div>

    <div v-else class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
      <div class="space-y-6">
        <Card class="border-0 shadow">
          <CardHeader>
            <CardTitle class="font-heading text-base">محتوى القالب</CardTitle>
            <CardDescription>
              استخدم المتغيرات المعتمدة فقط. يحفظ النظام نسخة جديدة بعد نجاح التحقق.
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-5">
            <Field :data-invalid="!!firstFieldError('subject')">
              <FieldLabel>موضوع البريد</FieldLabel>
              <Input
                :ref="bindSubjectRef"
                v-model="form.subject"
                dir="rtl"
                placeholder="أدخل موضوع البريد"
                @focus="lastFocusedField = 'subject'"
              />
              <FieldError v-if="firstFieldError('subject')">
                {{ firstFieldError('subject') }}
              </FieldError>
            </Field>

            <Field :data-invalid="!!firstFieldError('body')">
              <FieldLabel>نص Markdown</FieldLabel>
              <Textarea
                :ref="bindBodyRef"
                v-model="form.body"
                dir="rtl"
                rows="14"
                class="font-mono text-sm"
                placeholder="أدخل نص القالب بصيغة Markdown"
                @focus="lastFocusedField = 'body'"
              />
              <FieldDescription>
                لا يسمح HTML الخام. تعرض المعاينة المصدر بعد التنظيف قبل إنشاء الإصدار.
              </FieldDescription>
              <FieldError v-if="firstFieldError('body')">
                {{ firstFieldError('body') }}
              </FieldError>
            </Field>

            <div class="space-y-2">
              <p class="text-muted-foreground text-xs">اضغط لإدراج متغير في الحقل المحدد حالياً:</p>
              <div class="flex flex-wrap gap-2">
                <Button
                  v-for="variable in template?.allowed_variables ?? []"
                  :key="variable"
                  type="button"
                  variant="outline"
                  size="xs"
                  class="font-mono"
                  @click="insertVariable(variable)"
                >
                  {{ formatVariableToken(variable) }}
                </Button>
              </div>
            </div>

            <Separator />

            <div class="flex flex-wrap justify-end gap-2">
              <Button variant="outline" :disabled="previewing" @click="renderPreview">
                <Eye class="me-1 h-4 w-4" />
                {{ previewing ? 'جارٍ إنشاء المعاينة' : 'معاينة' }}
              </Button>
              <Button :disabled="!isDirty || saving" @click="saveTemplate">
                <Save class="me-1 h-4 w-4" />
                {{ saving ? 'جارٍ الحفظ' : 'حفظ إصدار جديد' }}
              </Button>
            </div>
          </CardContent>
        </Card>

        <Card class="border-0 shadow">
          <CardHeader>
            <CardTitle class="font-heading text-base">المعاينة المزدوجة</CardTitle>
            <CardDescription>
              المصدر المنظف هو ما سيخزن. البريد النهائي يعرض HTML النص البديل من محرك القوالب.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div
              v-if="!preview"
              class="text-muted-foreground rounded-xl border border-dashed p-6 text-sm"
            >
              أنشئ معاينة للتحقق من الصياغة والبيانات النموذجية قبل الحفظ.
            </div>
            <Tabs v-else default-value="source" dir="rtl">
              <TabsList>
                <TabsTrigger value="source">المصدر المنظف</TabsTrigger>
                <TabsTrigger value="html">البريد المرئي</TabsTrigger>
                <TabsTrigger value="text">النص البديل</TabsTrigger>
              </TabsList>
              <TabsContent value="source" class="mt-4 space-y-3">
                <div class="rounded-xl border p-4">
                  <p class="mb-2 text-sm font-semibold">{{ preview.source.subject }}</p>
                  <Separator class="my-2" />
                  <pre class="text-foreground text-sm leading-7 whitespace-pre-wrap">{{
                    preview.source.body
                  }}</pre>
                </div>
              </TabsContent>
              <TabsContent value="html" class="mt-4">
                <div class="bg-background overflow-hidden rounded-xl border">
                  <div class="border-b p-3 text-sm font-semibold">
                    {{ preview.rendered.subject }}
                  </div>
                  <iframe
                    class="bg-background h-[520px] w-full"
                    title="معاينة البريد الإلكتروني"
                    sandbox=""
                    :srcdoc="preview.rendered.html"
                  />
                </div>
              </TabsContent>
              <TabsContent value="text" class="mt-4">
                <pre
                  class="bg-muted/30 rounded-xl border p-4 text-sm leading-7 whitespace-pre-wrap"
                  >{{ preview.rendered.text }}</pre
                >
              </TabsContent>
            </Tabs>
          </CardContent>
        </Card>
      </div>

      <Card class="h-fit border-0 shadow">
        <CardHeader>
          <CardTitle class="font-heading text-base">سجل الإصدارات</CardTitle>
          <CardDescription
            >يعرض من عدّل القالب ومتى، دون عرض محتوى الإصدارات القديمة.</CardDescription
          >
        </CardHeader>
        <CardContent>
          <ScrollArea class="h-[420px] pe-3">
            <div class="space-y-3">
              <div
                v-for="version in template?.versions ?? []"
                :key="version.id"
                class="rounded-xl border p-3"
              >
                <div class="mb-2 flex items-center justify-between gap-2">
                  <Badge v-if="version.is_active_version">نشط</Badge>
                  <Badge v-else variant="secondary">سابق</Badge>
                  <span class="font-mono text-xs">#{{ version.id }}</span>
                </div>
                <p class="text-sm font-medium">{{ version.changed_by_name ?? 'النظام' }}</p>
                <p class="text-muted-foreground text-xs">
                  {{ formatNotificationTemplateDate(version.changed_at) }}
                </p>
              </div>
              <p
                v-if="(template?.versions ?? []).length === 0"
                class="text-muted-foreground text-sm"
              >
                لا توجد إصدارات مسجلة.
              </p>
            </div>
          </ScrollArea>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
