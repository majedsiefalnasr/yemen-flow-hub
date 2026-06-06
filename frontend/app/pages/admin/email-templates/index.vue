<script setup lang="ts">
import { Edit3, MailCheck, RefreshCw, ShieldCheck } from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useEmailTemplates } from '@/composables/useEmailTemplates'
import { UserRole } from '@/types/enums'
import {
  formatNotificationTemplateDate,
  NOTIFICATION_TEMPLATE_DESCRIPTIONS,
  notificationTemplateLabel,
  type NotificationTemplate,
} from '@/types/notifications'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: [UserRole.CBY_ADMIN],
})

useHead({ title: 'قوالب البريد الإلكتروني' })

const router = useRouter()
const { fetchTemplates, extractMessage } = useEmailTemplates()

const templates = ref<NotificationTemplate[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

onMounted(() => {
  void loadTemplates()
})

async function loadTemplates() {
  loading.value = true
  error.value = null
  try {
    templates.value = await fetchTemplates()
  } catch (err) {
    error.value = extractMessage(err, 'تعذّر تحميل قوالب البريد الإلكتروني.')
  } finally {
    loading.value = false
  }
}

function editTemplate(type: string) {
  router.push(`/admin/email-templates/${type}`)
}
</script>

<template>
  <div dir="rtl">
    <PageHeader
      title="قوالب البريد الإلكتروني"
      subtitle="إدارة الصياغة الرسمية للقوالب القابلة للتعديل ومعاينة مخرجات البريد قبل الإرسال"
      :breadcrumbs="[
        { label: 'الرئيسية', to: '/dashboard' },
        { label: 'إعدادات المؤسسة', to: '/organization?section=email' },
        { label: 'قوالب البريد الإلكتروني' },
      ]"
    />

    <Card class="mb-6 border-0 shadow">
      <CardHeader>
        <div class="flex items-start justify-between gap-4">
          <div>
            <CardTitle class="font-heading text-base">القوالب الخاضعة للحوكمة</CardTitle>
            <CardDescription>
              تظهر هنا الأنواع المسموح بتعديلها فقط. القوالب النظامية مثل MFA واستعادة كلمة المرور
              لا تعرض كمحررات.
            </CardDescription>
          </div>
          <Badge
            class="border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 text-[var(--severity-green)]"
          >
            <ShieldCheck class="me-1 h-3.5 w-3.5" />
            CBY Admin
          </Badge>
        </div>
      </CardHeader>
    </Card>

    <Alert v-if="error" variant="destructive" class="mb-6" role="alert">
      <AlertTitle>تعذّر التحميل</AlertTitle>
      <AlertDescription>{{ error }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="loadTemplates">
          <RefreshCw class="me-1 h-4 w-4" />
          إعادة المحاولة
        </Button>
      </AlertAction>
    </Alert>

    <Card class="border-0 shadow">
      <CardContent class="p-0">
        <div v-if="loading" class="space-y-3 p-4" aria-busy="true">
          <Skeleton v-for="row in 3" :key="row" class="h-14 w-full rounded-lg" />
        </div>

        <Table v-else>
          <TableHeader>
            <TableRow>
              <TableHead class="text-right">القالب</TableHead>
              <TableHead class="text-right">الحالة</TableHead>
              <TableHead class="text-right">المتغيرات</TableHead>
              <TableHead class="text-right">آخر تعديل</TableHead>
              <TableHead class="text-right">إجراء</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="template in templates" :key="template.type">
              <TableCell>
                <div class="flex items-center gap-3">
                  <div
                    class="bg-primary/10 text-primary flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                  >
                    <MailCheck class="h-5 w-5" aria-hidden="true" />
                  </div>
                  <div>
                    <p class="font-section text-sm font-semibold">
                      {{ notificationTemplateLabel(template.type) }}
                    </p>
                    <p class="text-muted-foreground text-xs">
                      {{ NOTIFICATION_TEMPLATE_DESCRIPTIONS[template.type] ?? template.type }}
                    </p>
                  </div>
                </div>
              </TableCell>
              <TableCell>
                <Badge
                  class="border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 text-[var(--severity-green)]"
                >
                  قابل للتعديل
                </Badge>
              </TableCell>
              <TableCell class="font-mono text-xs">
                {{ template.allowed_variables.length }} متغير
              </TableCell>
              <TableCell class="text-sm">
                {{ formatNotificationTemplateDate(template.active?.changed_at ?? null) }}
              </TableCell>
              <TableCell>
                <Button size="sm" variant="outline" @click="editTemplate(template.type)">
                  <Edit3 class="me-1 h-4 w-4" />
                  تحرير
                </Button>
              </TableCell>
            </TableRow>
            <TableEmpty v-if="templates.length === 0" :colspan="5">
              <Empty>
                <EmptyMedia variant="icon">
                  <MailCheck />
                </EmptyMedia>
                <EmptyHeader>
                  <EmptyTitle>لا توجد قوالب قابلة للتعديل</EmptyTitle>
                  <EmptyDescription>
                    القوالب النظامية محفوظة للمنصة ولا تعرض كمحررات إدارية.
                  </EmptyDescription>
                </EmptyHeader>
              </Empty>
            </TableEmpty>
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  </div>
</template>
