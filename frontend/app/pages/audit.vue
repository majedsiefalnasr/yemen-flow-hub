<script setup lang="ts">
import { Activity, AlertTriangle, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, FileWarning, Search, SearchX, ShieldCheck } from 'lucide-vue-next'
import { FlexRender, getCoreRowModel, getPaginationRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table'
import type { ColumnDef } from '@tanstack/vue-table'
import { h } from 'vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { useAudit } from '@/composables/useAudit'
import type { AuditLog } from '@/types/models'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'

const { fetchAuditLogs, fetchAuditStats, fetchDuplicates, fetchRiskIndicators } = useAudit()

const query = ref('')
const loadingAudit = ref(true)
const auditLogs = ref<AuditLog[]>([])
const todayCount = ref(0)
const duplicates = ref<{ invoice_number: string; banks: string[]; requests: { id: number; reference_number: string }[] }[]>([])
const risks = ref<{ title: string; body: string; level: 'عالية' | 'متوسطة' | 'منخفضة' }[]>([])

onMounted(async () => {
  const [logsResult, statsResult, dupsResult, risksResult] = await Promise.allSettled([
    fetchAuditLogs(),
    fetchAuditStats(),
    fetchDuplicates(),
    fetchRiskIndicators(),
  ])
  if (logsResult.status === 'fulfilled') auditLogs.value = logsResult.value.data
  if (statsResult.status === 'fulfilled') todayCount.value = statsResult.value.today_count
  if (dupsResult.status === 'fulfilled') duplicates.value = dupsResult.value
  if (risksResult.status === 'fulfilled') risks.value = risksResult.value
  loadingAudit.value = false
})

const filteredAudits = computed(() => {
  const q = query.value.trim()
  if (!q) return auditLogs.value
  const lower = q.toLowerCase()
  return auditLogs.value.filter(entry =>
    (entry.user?.name ?? '').toLowerCase().includes(lower)
    || entry.action.toLowerCase().includes(lower),
  )
})

const kpis = computed(() => [
  { label: 'نشاطات اليوم', value: todayCount.value.toString(), icon: Activity, tone: 'text-info bg-info/10' },
  { label: 'تنبيهات مفتوحة', value: risks.value.length.toString(), icon: AlertTriangle, tone: 'text-amber-600 bg-amber-50/10' },
  { label: 'فواتير مكررة', value: duplicates.value.length.toString(), icon: FileWarning, tone: 'text-red-700 bg-red-700/10' },
  { label: 'حالات مخاطر', value: risks.value.filter(r => r.level === 'عالية').length.toString(), icon: ShieldCheck, tone: 'text-red-700 bg-red-700/10' },
])

function formatDate(ts: string) {
  return new Date(ts).toLocaleString('ar-EG')
}

const ACTION_LABELS: Record<string, string> = {
  LOGIN: 'تسجيل دخول',
  LOGIN_FAILED: 'فشل تسجيل الدخول',
  LOGOUT: 'تسجيل خروج',
  SETTINGS_UPDATED: 'تحديث الإعدادات',
  USER_CREATED: 'إنشاء مستخدم',
  USER_UPDATED: 'تحديث مستخدم',
  USER_DEACTIVATED: 'تعطيل مستخدم',
  PASSWORD_CHANGED: 'تغيير كلمة المرور',
  MFA_ENABLED: 'تفعيل المصادقة الثنائية',
  MFA_DISABLED: 'تعطيل المصادقة الثنائية',
  CLAIM_RELEASED: 'إفراج عن المطالبة',
  AUTHORIZATION_FAILURE: 'فشل التفويض',
  WORKFLOW_TRANSITION: 'انتقال سير عمل',
  DOCUMENT_UPLOADED: 'رفع وثيقة',
  DOCUMENT_DOWNLOADED: 'تحميل وثيقة',
  VOTE_SUBMITTED: 'تسجيل تصويت',
  VOTING_SESSION_OPENED: 'فتح جلسة تصويت',
  VOTING_SESSION_CLOSED: 'إغلاق جلسة تصويت',
  CUSTOMS_DECLARATION_ISSUED: 'إصدار بيان جمركي',
}

function formatAction(action: string): string {
  return ACTION_LABELS[action] ?? action.replace(/_/g, ' ')
}

const columns: ColumnDef<AuditLog>[] = [
  {
    accessorKey: 'user',
    header: 'المستخدم',
    cell: ({ row }) => h('span', { class: 'font-medium' }, row.original.user?.name ?? 'غير معروف'),
  },
  {
    accessorKey: 'action',
    header: 'الإجراء',
    cell: ({ row }) => h('span', { class: 'inline-flex items-center rounded-md border border-border bg-muted px-2 py-0.5 text-xs font-medium text-foreground' }, formatAction(row.original.action)),
  },
  {
    accessorKey: 'from_status',
    header: 'من',
    cell: ({ row }) => h('span', { class: 'text-xs text-muted-foreground' }, row.original.from_status ?? '—'),
  },
  {
    accessorKey: 'to_status',
    header: 'إلى',
    cell: ({ row }) => h('span', { class: 'text-xs text-muted-foreground' }, row.original.to_status ?? '—'),
  },
  {
    accessorKey: 'created_at',
    header: 'التوقيت',
    cell: ({ row }) => h('span', { class: 'text-xs text-muted-foreground' }, formatDate(row.original.created_at)),
  },
]

const table = useVueTable({
  get data() { return filteredAudits.value },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  initialState: {
    pagination: { pageSize: 20 },
  },
})
</script>

<template>
  <div>
    <PageHeader
      title="التدقيق والامتثال"
      subtitle="سجل النشاط، كشف الفواتير المكررة، وتنبيهات المخاطر الأمنية"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'التدقيق والامتثال' }]"
    />

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
      <Card
        v-for="kpi in kpis"
        :key="kpi.label"
        class="flex items-center gap-3 border-0 p-4 shadow"
      >
        <div :class="['grid h-11 w-11 place-items-center rounded-xl', kpi.tone]">
          <component
            :is="kpi.icon"
            class="h-5 w-5"
          />
        </div>
        <div>
          <div class="text-xs text-muted-foreground">
            {{ kpi.label }}
          </div>
          <div class="text-xl font-bold">
            {{ kpi.value }}
          </div>
        </div>
      </Card>
    </div>

    <Tabs default-value="logs">
      <TabsList>
        <TabsTrigger value="logs">
          سجل النشاط
        </TabsTrigger>
        <TabsTrigger value="duplicates">
          الفواتير المكررة
        </TabsTrigger>
        <TabsTrigger value="risk">
          مؤشرات المخاطر
        </TabsTrigger>
      </TabsList>

      <TabsContent
        value="logs"
        class="mt-4"
      >
        <div class="mb-3 flex items-center gap-2">
          <div class="relative max-w-xs">
            <Search class="absolute end-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input
              v-model="query"
              class="h-8 rounded-md pe-9 text-sm"
              placeholder="بحث: مستخدم، إجراء..."
            />
          </div>
        </div>

        <Card class="border-0 shadow">
          <div class="overflow-x-auto">
            <Table class="w-full min-w-[760px] text-sm">
              <TableHeader>
                <TableRow
                  v-for="headerGroup in table.getHeaderGroups()"
                  :key="headerGroup.id"
                >
                  <TableHead
                    v-for="header in headerGroup.headers"
                    :key="header.id"
                    class="px-4 py-3 text-xs text-muted-foreground"
                  >
                    <FlexRender
                      v-if="!header.isPlaceholder"
                      :render="header.column.columnDef.header"
                      :props="header.getContext()"
                    />
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="loadingAudit">
                  <TableRow
                    v-for="i in 8"
                    :key="i"
                  >
                    <TableCell
                      v-for="j in 5"
                      :key="j"
                      class="px-4 py-3"
                    >
                      <Skeleton class="h-4 w-full rounded" />
                    </TableCell>
                  </TableRow>
                </template>
                <template v-else-if="table.getRowModel().rows.length">
                  <TableRow
                    v-for="row in table.getRowModel().rows"
                    :key="row.id"
                    class="border-t hover:bg-muted/30"
                  >
                    <TableCell
                      v-for="cell in row.getVisibleCells()"
                      :key="cell.id"
                      class="px-4 py-3"
                    >
                      <FlexRender
                        :render="cell.column.columnDef.cell"
                        :props="cell.getContext()"
                      />
                    </TableCell>
                  </TableRow>
                </template>
                <template v-else>
                  <TableRow>
                    <TableCell colspan="5" class="p-8">
                      <Empty class="min-h-[200px] rounded-xl border border-dashed bg-muted/20">
                        <EmptyHeader>
                          <div class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                            <SearchX class="size-5" />
                          </div>
                          <EmptyTitle>لا توجد سجلات مطابقة</EmptyTitle>
                        </EmptyHeader>
                        <EmptyContent>
                          <EmptyDescription>جرّب تغيير نص البحث.</EmptyDescription>
                        </EmptyContent>
                      </Empty>
                    </TableCell>
                  </TableRow>
                </template>
              </TableBody>
            </Table>
          </div>

          <div class="flex items-center justify-between border-t px-4 py-3">
            <p class="text-sm text-muted-foreground">
              {{ table.getFilteredRowModel().rows.length }} سجل
            </p>
            <div class="flex items-center gap-1">
              <Button
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :disabled="!table.getCanPreviousPage()"
                @click="table.setPageIndex(0)"
              >
                <ChevronsRight class="h-4 w-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :disabled="!table.getCanPreviousPage()"
                @click="table.previousPage()"
              >
                <ChevronRight class="h-4 w-4" />
              </Button>
              <span class="px-2 text-sm text-muted-foreground">
                {{ table.getState().pagination.pageIndex + 1 }} / {{ table.getPageCount() }}
              </span>
              <Button
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :disabled="!table.getCanNextPage()"
                @click="table.nextPage()"
              >
                <ChevronLeft class="h-4 w-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :disabled="!table.getCanNextPage()"
                @click="table.setPageIndex(table.getPageCount() - 1)"
              >
                <ChevronsLeft class="h-4 w-4" />
              </Button>
            </div>
          </div>
        </Card>
      </TabsContent>

      <TabsContent
        value="duplicates"
        class="mt-4"
      >
        <Card class="border-0 p-5 shadow">
          <div
            v-if="duplicates.length > 0"
            class="mb-4 flex items-center gap-2 rounded-lg border border-destructive/30 bg-red-700/10 p-3"
          >
            <AlertTriangle class="h-5 w-5 text-red-700" />
            <div class="text-sm">
              <span class="font-semibold">تم اكتشاف {{ duplicates.length }} حالات</span>
              لفواتير مكررة بحاجة لمراجعة عاجلة.
            </div>
          </div>

          <Empty
            v-if="duplicates.length === 0"
            class="min-h-[160px] rounded-xl border border-dashed bg-muted/20"
          >
            <EmptyHeader>
              <EmptyTitle>لا توجد فواتير مكررة</EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>لم يُكتشف أي تكرار في أرقام الفواتير.</EmptyDescription>
            </EmptyContent>
          </Empty>

          <div class="space-y-3">
            <div
              v-for="dup in duplicates"
              :key="dup.invoice_number"
              class="rounded-lg border p-4 hover:border-destructive/40"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div class="flex items-center gap-2">
                    <Badge variant="destructive">
                      مكرر
                    </Badge>
                    <span class="font-mono font-semibold">{{ dup.invoice_number }}</span>
                  </div>
                  <div class="mt-1 text-xs text-muted-foreground">
                    البنوك: {{ dup.banks.join('، ') }}
                  </div>
                </div>
                <div class="text-start text-xs text-muted-foreground">
                  {{ dup.requests.length }} طلبات مرتبطة
                </div>
              </div>
            </div>
          </div>
        </Card>
      </TabsContent>

      <TabsContent
        value="risk"
        class="mt-4"
      >
        <Card class="border-0 p-5 shadow">
          <h3 class="mb-4 font-semibold">
            مؤشرات المخاطر النشطة
          </h3>

          <Empty
            v-if="risks.length === 0"
            class="min-h-[160px] rounded-xl border border-dashed bg-muted/20"
          >
            <EmptyHeader>
              <EmptyTitle>لا توجد مؤشرات مخاطر</EmptyTitle>
            </EmptyHeader>
            <EmptyContent>
              <EmptyDescription>النظام في وضع سليم، لا تنبيهات نشطة.</EmptyDescription>
            </EmptyContent>
          </Empty>

          <div class="space-y-3">
            <div
              v-for="risk in risks"
              :key="risk.title"
              class="flex items-start gap-3 rounded-lg border p-3"
            >
              <ShieldCheck
                :class="[
                  'mt-0.5 h-5 w-5',
                  risk.level === 'عالية' ? 'text-red-700' : risk.level === 'متوسطة' ? 'text-amber-600' : 'text-info',
                ]"
              />
              <div class="flex-1">
                <div class="text-sm font-medium">
                  {{ risk.title }}
                </div>
                <div class="text-xs text-muted-foreground">
                  {{ risk.body }}
                </div>
              </div>
              <Badge :variant="risk.level === 'عالية' ? 'destructive' : 'secondary'">
                {{ risk.level }}
              </Badge>
            </div>
          </div>
        </Card>
      </TabsContent>
    </Tabs>
  </div>
</template>
