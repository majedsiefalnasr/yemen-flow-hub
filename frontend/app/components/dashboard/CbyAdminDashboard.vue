// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/cby-admin page level
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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
  RefreshCw,
  Download,
  CalendarDays,
  Building2,
  ChevronUp,
  ChevronDown,
  AlertCircle,
  CheckCircle2,
  Clock,
} from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import type {
  CbyAdminDashboardStats,
  CbyAdminKpi,
  CbyAdminWorkflowPressureRow,
  CbyAdminVotingSession,
  CbyAdminBankRiskRow,
  CbyAdminComplianceSignal,
  CbyAdminCriticalEvent,
} from '../../composables/useDashboard'
import {
  SPARK_W,
  SPARK_H,
  resolvedKpi as resolveKpi,
  severityColor,
  severityBg,
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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

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
  return new Intl.DateTimeFormat('ar-YE', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(iso))
}

// ── KPI config ─────────────────────────────────────────────────────────────
interface KpiConfig {
  key: keyof CbyAdminDashboardStats
  label: string
  icon: typeof Activity
  drilldown: string
}

const KPI_CONFIGS: KpiConfig[] = [
  { key: 'active_workflow_requests', label: 'طلبات نشطة في الدورة', icon: Activity, drilldown: '/requests?tab=active' },
  { key: 'sla_violations', label: 'انتهاكات SLA', icon: AlertTriangle, drilldown: '/requests?tab=needs_attention' },
  { key: 'open_voting_sessions', label: 'جلسات تصويت مفتوحة', icon: Vote, drilldown: '/requests?tab=executive_voting' },
  { key: 'fx_confirmation_pending', label: 'تأكيد مصارفة معلّق', icon: DollarSign, drilldown: '/requests?tab=fx_pending' },
  { key: 'bank_risk_alerts', label: 'تنبيهات مخاطر البنوك', icon: ShieldAlert, drilldown: '/reports' },
  { key: 'system_availability', label: 'توفّر النظام %', icon: Server, drilldown: '/audit' },
]

// Bind resolvedKpi to the local stats ref so call sites stay terse.
function resolvedKpi(key: keyof CbyAdminDashboardStats): CbyAdminKpi {
  return resolveKpi(stats.value, key)
}

function trendIcon(trend: CbyAdminWorkflowPressureRow['trend']) {
  return { up: TrendingUp, stable: Minus, down: TrendingDown }[trend]
}

// ── Compliance signals ──────────────────────────────────────────────────────
function signalSeverityIcon(severity: CbyAdminComplianceSignal['severity']) {
  return { red: AlertTriangle, amber: AlertCircle, blue: Activity }[severity]
}

// ── Bank risk sort ──────────────────────────────────────────────────────────
const riskSortKey = ref<keyof CbyAdminBankRiskRow>('risk_score')
const riskSortDesc = ref(true)

const sortedBankRisk = computed((): CbyAdminBankRiskRow[] => {
  const rows = stats.value?.bank_risk_intelligence ?? []
  return [...rows].sort((a, b) => {
    const av = a[riskSortKey.value] as number
    const bv = b[riskSortKey.value] as number
    return riskSortDesc.value ? bv - av : av - bv
  })
})

function toggleRiskSort(key: keyof CbyAdminBankRiskRow) {
  if (riskSortKey.value === key) riskSortDesc.value = !riskSortDesc.value
  else { riskSortKey.value = key; riskSortDesc.value = true }
}

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

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" >

    <!-- Global toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-muted/30 px-4 py-3">
      <div class="flex items-center gap-2">
        <Badge variant="outline" class="gap-1 rounded-full px-3 py-1 text-xs font-medium text-muted-foreground border-border">
          <ShieldAlert class="size-3" aria-hidden="true" />
          إشراف فقط
        </Badge>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <Button variant="ghost" size="sm" class="h-8 gap-1.5 text-xs" @click="refresh">
          <RefreshCw class="size-3.5" aria-hidden="true" />
          تحديث
        </Button>
        <span class="text-xs text-muted-foreground">
          <Clock class="inline size-3 me-1" aria-hidden="true" />
          آخر تحديث: {{ formatTime(lastRefreshed) }}
        </span>
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
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="store.loading" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات" class="flex flex-col gap-6">
      <div class="grid grid-cols-6 max-xl:grid-cols-3 max-md:grid-cols-2 gap-4">
        <Card v-for="n in 6" :key="n" class="border-0 shadow" aria-hidden="true">
          <CardContent class="p-4">
            <Skeleton class="h-3 w-3/5 mb-3" />
            <Skeleton class="h-8 w-1/3 mb-2" />
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
        <div class="grid grid-cols-6 max-xl:grid-cols-3 max-md:grid-cols-2 gap-4">
          <Card
            v-for="config in KPI_CONFIGS"
            :key="config.key"
            class="border-0 shadow cursor-pointer transition-shadow hover:shadow-md"
            :class="severityBg(resolvedKpi(config.key).severity)"
            @click="router.push(safeInternalPath(resolvedKpi(config.key).drilldown_route, config.drilldown))"
          >
            <CardContent class="p-4 flex flex-col gap-2">
              <!-- Icon + label -->
              <div class="flex items-center justify-between gap-1">
                <span class="text-xs font-medium text-foreground/80 leading-tight">{{ config.label }}</span>
                <component
                  :is="config.icon"
                  class="size-4 flex-shrink-0"
                  :style="{ color: severityColor(resolvedKpi(config.key).severity) }"
                  aria-hidden="true"
                />
              </div>

              <!-- Value + delta -->
              <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold" :style="{ color: severityColor(resolvedKpi(config.key).severity) }">
                  {{ resolvedKpi(config.key).value.toLocaleString('ar-YE') }}
                </span>
                <span
                  v-if="resolvedKpi(config.key).delta !== 0"
                  class="text-xs"
                  :class="resolvedKpi(config.key).delta > 0 ? 'text-[var(--severity-red)]' : 'text-[var(--severity-green)]'"
                >
                  {{ resolvedKpi(config.key).delta > 0 ? '+' : '' }}{{ resolvedKpi(config.key).delta }}
                </span>
              </div>

              <!-- Mini sparkline -->
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
            </CardContent>
          </Card>
        </div>
      </section>

      <!-- Workflow Pressure Map -->
      <Card v-if="stats.workflow_pressure_map?.length" class="border-0 shadow" aria-labelledby="pressure-heading">
        <CardHeader class="pb-3">
          <CardTitle id="pressure-heading" class="text-sm font-semibold">خريطة ضغط سير العمل</CardTitle>
          <CardDescription class="text-xs">المراحل النشطة وحالة SLA — انقر لتصفية الطلبات حسب المرحلة</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="text-right text-xs">المرحلة</TableHead>
                <TableHead class="text-right text-xs">طلبات نشطة</TableHead>
                <TableHead class="text-right text-xs">متوسط العمر</TableHead>
                <TableHead class="text-right text-xs">مخاطر SLA</TableHead>
                <TableHead class="text-right text-xs">الاتجاه</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow
                v-for="row in stats.workflow_pressure_map"
                :key="row.stage"
                class="cursor-pointer transition-colors"
                :class="row.sla_risk === 'high' ? 'bg-[var(--severity-red)]/5 hover:bg-[var(--severity-red)]/10' : row.sla_risk === 'medium' ? 'bg-[var(--severity-amber)]/5 hover:bg-[var(--severity-amber)]/10' : 'hover:bg-muted/40'"
                @click="router.push(`/requests?stage=${row.stage}`)"
              >
                <TableCell class="text-right text-xs font-medium py-2.5">{{ row.stage_label }}</TableCell>
                <TableCell class="text-right text-xs py-2.5">
                  <span class="font-semibold" :style="{ color: slaRiskColor(row.sla_risk) }">{{ row.active_count }}</span>
                </TableCell>
                <TableCell class="text-right text-xs py-2.5">{{ formatDuration(row.avg_age_hours) }}</TableCell>
                <TableCell class="text-right py-2.5">
                  <Badge class="text-[10px] px-1.5 py-0 rounded-full border-0 font-medium" :style="{ backgroundColor: `${slaRiskColor(row.sla_risk)}20`, color: slaRiskColor(row.sla_risk) }">
                    {{ slaRiskLabel(row.sla_risk) }}
                  </Badge>
                </TableCell>
                <TableCell class="text-right py-2.5">
                  <component :is="trendIcon(row.trend)" class="size-4" :style="{ color: trendColor(row.trend) }" aria-hidden="true" />
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <!-- Two-column: Executive Voting Oversight + Bank Risk Intelligence -->
      <div class="grid grid-cols-2 max-lg:grid-cols-1 gap-6">

        <!-- Executive Voting Oversight -->
        <Card class="border-0 shadow" aria-labelledby="voting-oversight-heading">
          <CardHeader class="pb-3">
            <CardTitle id="voting-oversight-heading" class="text-sm font-semibold">الإشراف على التصويت التنفيذي</CardTitle>
            <CardDescription class="text-xs">جلسات التصويت المفتوحة — قراءة فقط</CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="!stats.executive_voting_sessions?.length" class="py-8 text-center text-sm text-muted-foreground" role="status">
              <CheckCircle2 class="size-6 mx-auto mb-2 text-[var(--severity-green)]" aria-hidden="true" />
              لا توجد جلسات تصويت مفتوحة
            </div>
            <ul v-else class="flex flex-col gap-3">
              <li
                v-for="session in stats.executive_voting_sessions"
                :key="session.id"
                class="flex flex-col gap-1.5 rounded-lg border border-border p-3 cursor-pointer hover:border-primary/40 transition-colors"
                @click="router.push(`/requests/${session.id}`)"
              >
                <div class="flex items-center justify-between gap-2">
                  <span class="font-mono text-xs text-primary">{{ session.reference_number }}</span>
                  <span class="text-xs text-muted-foreground">{{ formatDateShort(session.opened_at) }}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                  <span class="text-xs text-foreground">{{ session.bank_name }}</span>
                  <span class="text-xs font-medium">{{ new Intl.NumberFormat('en-US', { style: 'currency', currency: session.currency, maximumFractionDigits: 0 }).format(session.amount) }}</span>
                </div>
                <div v-if="session.waiting_for.length" class="text-xs text-[var(--severity-amber)]">
                  <span class="font-medium">بانتظار: </span>{{ session.waiting_for.join('، ') }}
                </div>
              </li>
            </ul>
          </CardContent>
        </Card>

        <!-- Bank Risk Intelligence -->
        <Card class="border-0 shadow" aria-labelledby="bank-risk-heading">
          <CardHeader class="pb-3">
            <CardTitle id="bank-risk-heading" class="text-sm font-semibold">استخبارات مخاطر البنوك</CardTitle>
            <CardDescription class="text-xs">انقر على العمود للفرز</CardDescription>
          </CardHeader>
          <CardContent class="p-0">
            <div v-if="!sortedBankRisk.length" class="py-8 text-center text-sm text-muted-foreground" role="status">
              لا توجد بيانات مخاطر
            </div>
            <Table v-else>
              <TableHeader>
                <TableRow>
                  <TableHead class="text-right text-xs cursor-pointer" @click="toggleRiskSort('bank_name')">البنك</TableHead>
                  <TableHead class="text-right text-xs cursor-pointer" @click="toggleRiskSort('request_volume')">
                    الحجم
                    <component :is="riskSortKey === 'request_volume' ? (riskSortDesc ? ChevronDown : ChevronUp) : Minus" class="inline size-3 ms-1" aria-hidden="true" />
                  </TableHead>
                  <TableHead class="text-right text-xs cursor-pointer" @click="toggleRiskSort('approval_rate')">
                    معدل القبول
                    <component :is="riskSortKey === 'approval_rate' ? (riskSortDesc ? ChevronDown : ChevronUp) : Minus" class="inline size-3 ms-1" aria-hidden="true" />
                  </TableHead>
                  <TableHead class="text-right text-xs cursor-pointer" @click="toggleRiskSort('risk_score')">
                    المخاطر
                    <component :is="riskSortKey === 'risk_score' ? (riskSortDesc ? ChevronDown : ChevronUp) : Minus" class="inline size-3 ms-1" aria-hidden="true" />
                  </TableHead>
                  <TableHead class="text-right text-xs">تنبيهات</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow
                  v-for="row in sortedBankRisk"
                  :key="row.bank_id"
                  class="cursor-pointer hover:bg-muted/40 transition-colors"
                  @click="router.push(`/requests?bank=${row.bank_id}`)"
                >
                  <TableCell class="text-right text-xs font-medium py-2">{{ row.bank_name }}</TableCell>
                  <TableCell class="text-right text-xs py-2">{{ row.request_volume }}</TableCell>
                  <TableCell class="text-right text-xs py-2">{{ row.approval_rate }}%</TableCell>
                  <TableCell class="text-right py-2">
                    <span class="text-xs font-bold" :style="{ color: riskScoreColor(row.risk_score) }">{{ row.risk_score }}</span>
                  </TableCell>
                  <TableCell class="text-right py-2">
                    <Badge v-if="row.alerts > 0" class="text-[10px] px-1.5 rounded-full border-0" style="background:var(--severity-red)20;color:var(--severity-red)">{{ row.alerts }}</Badge>
                    <CheckCircle2 v-else class="size-3.5 text-[var(--severity-green)]" aria-hidden="true" />
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>

      <!-- Compliance & Audit Signals -->
      <section v-if="stats.compliance_signals?.length" aria-labelledby="signals-heading">
        <h2 id="signals-heading" class="text-sm font-semibold mb-3">إشارات الامتثال والتدقيق</h2>
        <div class="grid grid-cols-3 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4">
          <button
            v-for="signal in stats.compliance_signals"
            :key="signal.id"
            class="flex flex-col items-start gap-2 p-4 rounded-xl border text-start cursor-pointer transition-all hover:shadow-md"
            :class="signal.severity === 'red' ? 'border-[var(--severity-red)]/30 bg-[var(--severity-red)]/5' : signal.severity === 'amber' ? 'border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/5' : 'border-primary/20 bg-primary/5'"
            @click="router.push(safeInternalPath(signal.link_route, '/audit'))"
          >
            <div class="flex items-center gap-2">
              <component :is="signalSeverityIcon(signal.severity)" class="size-4" :style="{ color: severityColor(signal.severity) }" aria-hidden="true" />
              <span class="text-xs font-semibold" :style="{ color: severityColor(signal.severity) }">{{ signal.title }}</span>
            </div>
            <p class="text-xs text-foreground/80 leading-relaxed">{{ signal.description }}</p>
            <span class="text-[10px] text-muted-foreground">{{ formatDateShort(signal.created_at) }}</span>
          </button>
        </div>
      </section>

      <!-- Critical Events Feed -->
      <Card v-if="stats.critical_events?.length" class="border-0 shadow" aria-labelledby="events-heading">
        <CardHeader class="pb-2">
          <CardTitle id="events-heading" class="text-sm font-semibold">الأحداث الحرجة</CardTitle>
          <CardDescription class="text-xs">أحداث الحوكمة والأمان عالية الأولوية فقط</CardDescription>
        </CardHeader>
        <CardContent>
          <ul class="flex flex-col divide-y divide-border" role="list">
            <li
              v-for="event in stats.critical_events"
              :key="event.id"
              class="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0"
            >
              <component :is="eventIcon(event.event_type)" class="size-4 flex-shrink-0 mt-0.5 text-muted-foreground" aria-hidden="true" />
              <div class="flex-1 min-w-0">
                <p class="text-xs text-foreground leading-snug">{{ event.summary }}</p>
                <span class="text-[10px] text-muted-foreground">{{ event.actor_name }} · {{ formatDateShort(event.created_at) }}</span>
              </div>
              <button
                v-if="event.link_route"
                class="text-[10px] text-primary hover:underline flex-shrink-0 cursor-pointer"
                @click="router.push(safeInternalPath(event.link_route, '/audit'))"
              >
                عرض
              </button>
            </li>
          </ul>
        </CardContent>
      </Card>

      <!-- Empty state when no governance data yet -->
      <div v-if="!stats.workflow_pressure_map?.length && !stats.executive_voting_sessions?.length && !stats.bank_risk_intelligence?.length" class="rounded-xl border border-dashed border-border p-8 text-center" role="status">
        <Activity class="size-8 mx-auto mb-3 text-muted-foreground" aria-hidden="true" />
        <p class="text-sm text-muted-foreground">لا توجد بيانات حوكمة للفترة المختارة</p>
        <p class="text-xs text-muted-foreground mt-1">لا توجد بيانات للفترة المختارة</p>
      </div>

    </template>
  </div>
</template>
