// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/cby-admin page
level
<script setup lang="ts">
import type { ColumnDef } from '@tanstack/vue-table'
import { computed, onMounted, ref, h } from 'vue'
import { useRouter } from 'vue-router'
import {
  Activity,
  AlertTriangle,
  Vote,
  DollarSign,
  ShieldAlert,
  Server,
  TrendingUp,
  TrendingDown,
  Minus,
  Download,
  CalendarDays,
  Building2,
  AlertCircle,
  CheckCircle2,
} from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import type {
  CbyAdminDashboardStats,
  CbyAdminKpi,
  CbyAdminWorkflowPressureRow,
  CbyAdminBankRiskRow,
  CbyAdminComplianceSignal,
} from '../../composables/useDashboard'
import {
  SPARK_W,
  SPARK_H,
  resolvedKpi as resolveKpi,
  severityColor,
  buildSparkLine,
  slaRiskColor,
  slaRiskLabel,
  trendColor,
  riskScoreColor,
  formatDuration,
} from '../../utils/cby-admin-helpers'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Alert, AlertDescription } from '@/components/ui/alert'
import DataTable from '@/components/ui/data-table/DataTable.vue'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'
import RankedListCard from '@/components/shared/dashboard/RankedListCard.vue'
import RecentActivityCard from '@/components/shared/dashboard/RecentActivityCard.vue'
import DashboardToolbar from '@/components/shared/dashboard/DashboardToolbar.vue'

const router = useRouter()
const store = useDashboardStore()
const stats = computed(() => store.stats as CbyAdminDashboardStats | null)
const lastRefreshed = ref(new Date())

// Only mark "last refreshed" once the fetch resolves so the timestamp tracks
// the data the user actually sees, not the moment they clicked.
async function refresh() {
  await store.loadStats()
  if (!store.error) lastRefreshed.value = new Date()
}

// Guard backend-provided URLs so a poisoned API payload cannot redirect the
// user off-route. Anything not starting with a single `/` falls back to the
// static drilldown defined in KPI_CONFIGS.
function safeInternalPath(route: string | undefined, fallback: string): string {
  if (typeof route !== 'string') return fallback
  if (!route.startsWith('/') || route.startsWith('//')) return fallback
  return route
}

function formatTime(d: Date): string {
  return new Intl.DateTimeFormat('ar-YE', { hour: '2-digit', minute: '2-digit' }).format(d)
}

function formatDateShort(iso: string): string {
  return new Intl.DateTimeFormat('ar-YE', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(iso))
}

// ── KPI config ─────────────────────────────────────────────────────────────
interface KpiConfig {
  key: keyof CbyAdminDashboardStats
  label: string
  icon: typeof Activity
  drilldown: string
}

const KPI_CONFIGS: KpiConfig[] = [
  {
    key: 'active_workflow_requests',
    label: 'طلبات نشطة في الدورة',
    icon: Activity,
    drilldown: '/requests?tab=active',
  },
  {
    key: 'sla_violations',
    label: 'انتهاكات SLA',
    icon: AlertTriangle,
    drilldown: '/requests?tab=needs_attention',
  },
  {
    key: 'open_voting_sessions',
    label: 'جلسات تصويت مفتوحة',
    icon: Vote,
    drilldown: '/requests?tab=executive_voting',
  },
  {
    key: 'fx_confirmation_pending',
    label: 'تأكيد مصارفة معلّق',
    icon: DollarSign,
    drilldown: '/requests?tab=fx_pending',
  },
  {
    key: 'bank_risk_alerts',
    label: 'تنبيهات مخاطر البنوك',
    icon: ShieldAlert,
    drilldown: '/reports',
  },
  { key: 'system_availability', label: 'توفّر النظام %', icon: Server, drilldown: '/audit' },
]

// Bind resolvedKpi to the local stats ref so call sites stay terse.
function resolvedKpi(key: keyof CbyAdminDashboardStats): CbyAdminKpi {
  return resolveKpi(stats.value, key)
}

function trendIcon(trend: CbyAdminWorkflowPressureRow['trend']) {
  return { up: TrendingUp, stable: Minus, down: TrendingDown }[trend]
}

function kpiTone(severity: CbyAdminKpi['severity']): 'default' | 'info' | 'warning' | 'danger' {
  if (severity === 'red') return 'danger'
  if (severity === 'amber') return 'warning'
  if (severity === 'blue') return 'info'
  return 'default'
}

// ── Compliance signals ──────────────────────────────────────────────────────
function signalSeverityIcon(severity: CbyAdminComplianceSignal['severity']) {
  return { red: AlertTriangle, amber: AlertCircle, blue: Activity }[severity]
}

const sortedBankRisk = computed((): CbyAdminBankRiskRow[] => {
  const rows = stats.value?.bank_risk_intelligence ?? []
  return [...rows].sort((a, b) => b.risk_score - a.risk_score)
})

const workflowPressureColumns: ColumnDef<CbyAdminWorkflowPressureRow>[] = [
  {
    accessorKey: 'stage_label',
    header: 'المرحلة',
    cell: ({ row }) =>
      h('span', { class: 'text-right text-xs font-medium py-2.5' }, row.original.stage_label),
  },
  {
    id: 'active_count',
    header: 'طلبات نشطة',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'font-semibold', style: { color: slaRiskColor(row.original.sla_risk) } },
        row.original.active_count,
      ),
  },
  {
    id: 'avg_age_hours',
    header: 'متوسط العمر',
    cell: ({ row }) =>
      h('span', { class: 'text-right text-xs py-2.5' }, formatDuration(row.original.avg_age_hours)),
  },
  {
    id: 'sla_risk',
    header: 'مخاطر SLA',
    cell: ({ row }) =>
      h(
        Badge,
        {
          class: 'text-[10px] px-1.5 py-0 rounded-full border-0 font-medium',
          style: {
            backgroundColor: `${slaRiskColor(row.original.sla_risk)}20`,
            color: slaRiskColor(row.original.sla_risk),
          },
        },
        () => slaRiskLabel(row.original.sla_risk),
      ),
  },
  {
    id: 'trend',
    header: 'الاتجاه',
    cell: ({ row }) =>
      h(trendIcon(row.original.trend), {
        class: 'size-4',
        style: { color: trendColor(row.original.trend) },
        'aria-hidden': 'true',
      }),
  },
]

const bankRiskColumns: ColumnDef<CbyAdminBankRiskRow>[] = [
  {
    accessorKey: 'bank_name',
    header: 'البنك',
    cell: ({ row }) =>
      h('span', { class: 'text-right text-xs font-medium py-2' }, row.original.bank_name),
  },
  {
    accessorKey: 'request_volume',
    header: 'الحجم',
    cell: ({ row }) => h('span', { class: 'text-right text-xs py-2' }, row.original.request_volume),
  },
  {
    accessorKey: 'approval_rate',
    header: 'معدل القبول',
    cell: ({ row }) =>
      h('span', { class: 'text-right text-xs py-2' }, `${row.original.approval_rate}%`),
  },
  {
    accessorKey: 'risk_score',
    header: 'المخاطر',
    cell: ({ row }) =>
      h(
        'span',
        {
          class: 'text-xs font-semibold leading-5 tabular-nums',
          style: { color: riskScoreColor(row.original.risk_score) },
        },
        row.original.risk_score,
      ),
  },
  {
    id: 'alerts',
    header: 'تنبيهات',
    cell: ({ row }) =>
      row.original.alerts > 0
        ? h(
            Badge,
            {
              class: 'text-[10px] px-1.5 rounded-full border-0',
              style: 'background:var(--severity-red)20;color:var(--severity-red)',
            },
            () => row.original.alerts,
          )
        : h(CheckCircle2, {
            class: 'size-3.5 text-[var(--severity-green)]',
            'aria-hidden': 'true',
          }),
  },
]

// ── Critical events ─────────────────────────────────────────────────────────
const GOVERNANCE_EVENT_ICONS: Record<string, typeof Activity> = {
  voting_finalized: CheckCircle2,
  fx_completed: DollarSign,
  role_changed: ShieldAlert,
  security_login: AlertTriangle,
  entity_activated: Building2,
  document_rule_modified: AlertCircle,
}

function eventIcon(event_type: string) {
  return GOVERNANCE_EVENT_ICONS[event_type] ?? Activity
}

onMounted(() => {
  store.loadStats()
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Global toolbar -->
    <DashboardToolbar
      badge-label="إشراف فقط"
      :badge-icon="ShieldAlert"
      :last-updated="formatTime(lastRefreshed)"
      @refresh="refresh"
    >
      <Button variant="ghost" size="sm" class="h-8 gap-1.5 text-xs">
        <CalendarDays class="size-3.5" aria-hidden="true" />
        النطاق الزمني
      </Button>
      <Button variant="ghost" size="sm" class="h-8 gap-1.5 text-xs">
        <Building2 class="size-3.5" aria-hidden="true" />
        فلترة البنوك
      </Button>
      <Button variant="outline" size="sm" class="h-8 gap-1.5 text-xs">
        <Download class="size-3.5" aria-hidden="true" />
        تصدير PDF تنفيذي
      </Button>
    </DashboardToolbar>

    <!-- Loading skeleton -->
    <div
      v-if="store.loading"
      aria-busy="true"
      aria-label="جارٍ تحميل الإحصائيات"
      class="flex flex-col gap-6"
    >
      <div class="grid grid-cols-6 gap-4 max-xl:grid-cols-3 max-md:grid-cols-2">
        <Card v-for="n in 6" :key="n" class="border-0 shadow" aria-hidden="true">
          <CardContent class="p-4">
            <Skeleton class="mb-3 h-3 w-3/5" />
            <Skeleton class="mb-2 h-8 w-1/3" />
            <Skeleton class="h-5 w-full" />
          </CardContent>
        </Card>
      </div>
      <Skeleton class="h-40 w-full rounded-xl" />
      <Skeleton class="h-32 w-full rounded-xl" />
    </div>

    <!-- Error -->
    <Alert v-else-if="store.error" variant="destructive" role="alert">
      <AlertCircle class="size-4" />
      <AlertDescription class="flex items-center justify-between">
        <span>{{ store.error }}</span>
        <Button variant="outline" size="sm" @click="store.loadStats()">إعادة المحاولة</Button>
      </AlertDescription>
    </Alert>

    <template v-else-if="stats">
      <!-- 6-KPI Strategic Governance Strip -->
      <section aria-label="مؤشرات أداء النظام">
        <MetricGrid :columns="6">
          <MetricCard
            v-for="config in KPI_CONFIGS"
            :key="config.key"
            :label="config.label"
            :value="resolvedKpi(config.key).value.toLocaleString('ar-YE')"
            :tone="kpiTone(resolvedKpi(config.key).severity)"
            :highlighted="
              resolvedKpi(config.key).severity === 'red' ||
              resolvedKpi(config.key).severity === 'amber'
            "
            :previous-label="
              resolvedKpi(config.key).delta !== 0
                ? `${resolvedKpi(config.key).delta > 0 ? '+' : ''}${resolvedKpi(config.key).delta}`
                : undefined
            "
            @click="
              router.push(
                safeInternalPath(resolvedKpi(config.key).drilldown_route, config.drilldown),
              )
            "
          >
            <template #icon>
              <div class="bg-muted/50 flex h-9 w-9 items-center justify-center rounded">
                <component
                  :is="config.icon"
                  class="size-4"
                  :style="{ color: severityColor(resolvedKpi(config.key).severity) }"
                  aria-hidden="true"
                />
              </div>
            </template>
            <template #footer>
              <svg
                v-if="resolvedKpi(config.key).sparkline.length >= 2"
                :viewBox="`0 0 ${SPARK_W} ${SPARK_H}`"
                :width="SPARK_W"
                :height="SPARK_H"
                class="w-full"
                preserveAspectRatio="none"
                aria-hidden="true"
              >
                <polyline
                  :points="buildSparkLine(resolvedKpi(config.key).sparkline)"
                  fill="none"
                  :stroke="severityColor(resolvedKpi(config.key).severity)"
                  stroke-width="1.5"
                  stroke-linejoin="round"
                  stroke-linecap="round"
                  opacity="0.7"
                />
              </svg>
              <div v-else class="h-7" aria-hidden="true" />
            </template>
          </MetricCard>
        </MetricGrid>
      </section>

      <!-- Workflow Pressure Map -->
      <Card
        v-if="stats.workflow_pressure_map?.length"
        class="border-0 shadow"
        aria-labelledby="pressure-heading"
      >
        <CardHeader class="pb-3">
          <CardTitle id="pressure-heading" class="text-sm font-semibold"
            >خريطة ضغط سير العمل</CardTitle
          >
          <CardDescription class="text-xs">
            المراحل النشطة وحالة SLA. انقر لتصفية الطلبات حسب المرحلة
          </CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <DataTable
            :data="stats.workflow_pressure_map"
            :columns="workflowPressureColumns"
            @row-click="(row) => router.push(`/requests?stage=${row.stage}`)"
          />
        </CardContent>
      </Card>

      <!-- Two-column: Executive Voting Oversight + Bank Risk Intelligence -->
      <div class="grid grid-cols-2 gap-6 max-lg:grid-cols-1">
        <!-- Executive Voting Oversight -->
        <Card class="border-0 shadow" aria-labelledby="voting-oversight-heading">
          <CardHeader class="pb-3">
            <CardTitle id="voting-oversight-heading" class="text-sm font-semibold"
              >الإشراف على التصويت التنفيذي</CardTitle
            >
            <CardDescription class="text-xs">جلسات التصويت المفتوحة (قراءة فقط)</CardDescription>
          </CardHeader>
          <CardContent>
            <div
              v-if="!stats.executive_voting_sessions?.length"
              class="text-muted-foreground py-8 text-center text-sm"
              role="status"
            >
              <CheckCircle2
                class="mx-auto mb-2 size-6 text-[var(--severity-green)]"
                aria-hidden="true"
              />
              لا توجد جلسات تصويت مفتوحة
            </div>
            <ul v-else class="flex flex-col gap-3">
              <li
                v-for="session in stats.executive_voting_sessions"
                :key="session.id"
                class="border-border hover:border-primary/40 flex cursor-pointer flex-col gap-1.5 rounded-lg border p-3 transition-colors"
                @click="router.push(`/requests/${session.id}`)"
              >
                <div class="flex items-center justify-between gap-2">
                  <span class="text-primary font-mono text-xs">{{ session.reference_number }}</span>
                  <span class="text-muted-foreground text-xs">{{
                    formatDateShort(session.opened_at)
                  }}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                  <span class="text-foreground text-xs">{{ session.bank_name }}</span>
                  <span class="text-xs font-medium">{{
                    new Intl.NumberFormat('en-US', {
                      style: 'currency',
                      currency: session.currency,
                      maximumFractionDigits: 0,
                    }).format(session.amount)
                  }}</span>
                </div>
                <div v-if="session.waiting_for.length" class="text-xs text-[var(--severity-amber)]">
                  <span class="font-medium">بانتظار: </span>{{ session.waiting_for.join('، ') }}
                </div>
              </li>
            </ul>
          </CardContent>
        </Card>

        <!-- Bank Risk Intelligence -->
        <RankedListCard
          title="استخبارات مخاطر البنوك"
          description="انقر على العمود للفرز"
          content-class="p-0"
          aria-labelledby="bank-risk-heading"
        >
          <div
            v-if="!sortedBankRisk.length"
            class="text-muted-foreground py-8 text-center text-sm"
            role="status"
          >
            لا توجد بيانات مخاطر
          </div>
          <DataTable
            v-else
            :data="sortedBankRisk"
            :columns="bankRiskColumns"
            @row-click="(row) => router.push(`/requests?bank=${row.bank_id}`)"
          />
        </RankedListCard>
      </div>

      <!-- Compliance & Audit Signals -->
      <section v-if="stats.compliance_signals?.length" aria-labelledby="signals-heading">
        <h2 id="signals-heading" class="mb-3 text-sm font-semibold">إشارات الامتثال والتدقيق</h2>
        <div class="grid grid-cols-3 gap-4 max-lg:grid-cols-2 max-md:grid-cols-1">
          <Button
            v-for="signal in stats.compliance_signals"
            :key="signal.id"
            variant="outline"
            class="h-auto flex-col items-start gap-2 rounded-xl p-4 text-start transition-all hover:shadow-md"
            @click="router.push(safeInternalPath(signal.link_route, '/audit'))"
          >
            <div class="flex items-center gap-2">
              <component
                :is="signalSeverityIcon(signal.severity)"
                class="size-4"
                aria-hidden="true"
              />
              <span class="text-xs font-semibold">{{ signal.title }}</span>
            </div>
            <p class="text-foreground/80 text-xs leading-relaxed">{{ signal.description }}</p>
            <span class="text-muted-foreground text-[10px]">{{
              formatDateShort(signal.created_at)
            }}</span>
          </Button>
        </div>
      </section>

      <!-- Critical Events Feed -->
      <RecentActivityCard
        v-if="stats.critical_events?.length"
        title="الأحداث الحرجة"
        description="أحداث الحوكمة والأمان عالية الأولوية فقط"
        aria-labelledby="events-heading"
      >
        <ul class="divide-border flex flex-col divide-y" role="list">
          <li
            v-for="event in stats.critical_events"
            :key="event.id"
            class="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0"
          >
            <component
              :is="eventIcon(event.event_type)"
              class="text-muted-foreground mt-0.5 size-4 flex-shrink-0"
              aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
              <p class="text-foreground text-xs leading-snug">{{ event.summary }}</p>
              <span class="text-muted-foreground text-[10px]"
                >{{ event.actor_name }} · {{ formatDateShort(event.created_at) }}</span
              >
            </div>
            <Button
              v-if="event.link_route"
              variant="link"
              size="sm"
              class="h-auto flex-shrink-0 p-0 text-[10px]"
              @click="router.push(safeInternalPath(event.link_route, '/audit'))"
            >
              عرض
            </Button>
          </li>
        </ul>
      </RecentActivityCard>

      <!-- Empty state when no governance data yet -->
      <div
        v-if="
          !stats.workflow_pressure_map?.length &&
          !stats.executive_voting_sessions?.length &&
          !stats.bank_risk_intelligence?.length
        "
        class="border-border rounded-xl border border-dashed p-8 text-center"
        role="status"
      >
        <Activity class="text-muted-foreground mx-auto mb-3 size-8" aria-hidden="true" />
        <p class="text-muted-foreground text-sm">لا توجد بيانات حوكمة للفترة المختارة</p>
      </div>
    </template>
  </div>
</template>
