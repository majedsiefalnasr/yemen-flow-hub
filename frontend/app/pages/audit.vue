<script setup lang="ts">
import { Activity, AlertTriangle, FileWarning, Search, ShieldCheck } from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { useAudit } from '@/composables/useAudit'
import type { AuditLog } from '@/types/models'

const { fetchAuditLogs, fetchAuditStats, fetchDuplicates, fetchRiskIndicators } = useAudit()

const query = ref('')
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
          <div class="text-xs text-gray-600">
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
        <Card class="border-0 shadow">
          <div class="border-b p-4">
            <div class="relative max-w-md">
              <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-600" />
              <Input
                v-model="query"
                class="pe-10"
                placeholder="بحث في السجل: مستخدم، إجراء..."
              />
            </div>
          </div>

          <div class="overflow-x-auto">
            <Table class="w-full min-w-[760px] text-sm">
              <TableHeader class="bg-gray-50/40 text-end text-xs text-gray-600">
                <TableRow>
                  <TableHead class="px-4 py-3">
                    المستخدم
                  </TableHead>
                  <TableHead class="px-4 py-3">
                    الإجراء
                  </TableHead>
                  <TableHead class="px-4 py-3">
                    من
                  </TableHead>
                  <TableHead class="px-4 py-3">
                    إلى
                  </TableHead>
                  <TableHead class="px-4 py-3">
                    التوقيت
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow
                  v-for="entry in filteredAudits"
                  :key="entry.id"
                  class="border-t hover:bg-gray-50/30"
                >
                  <TableCell class="px-4 py-3 font-medium">
                    {{ entry.user?.name ?? 'غير معروف' }}
                  </TableCell>
                  <TableCell class="px-4 py-3">
                    <Badge variant="secondary">
                      {{ entry.action }}
                    </Badge>
                  </TableCell>
                  <TableCell class="px-4 py-3 text-xs text-gray-600">
                    {{ entry.from_status ?? '—' }}
                  </TableCell>
                  <TableCell class="px-4 py-3 text-xs text-gray-600">
                    {{ entry.to_status ?? '—' }}
                  </TableCell>
                  <TableCell class="px-4 py-3 text-xs text-gray-600">
                    {{ formatDate(entry.created_at) }}
                  </TableCell>
                </TableRow>
                <TableRow v-if="filteredAudits.length === 0">
                  <TableCell
                    colspan="5"
                    class="px-4 py-8 text-center text-gray-600"
                  >
                    لا توجد سجلات.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
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

          <div
            v-if="duplicates.length === 0"
            class="py-8 text-center text-sm text-gray-600"
          >
            لا توجد فواتير مكررة.
          </div>

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
                  <div class="mt-1 text-xs text-gray-600">
                    البنوك: {{ dup.banks.join('، ') }}
                  </div>
                </div>
                <div class="text-start text-xs text-gray-600">
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

          <div
            v-if="risks.length === 0"
            class="py-8 text-center text-sm text-gray-600"
          >
            لا توجد مؤشرات مخاطر.
          </div>

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
                <div class="text-xs text-gray-600">
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
