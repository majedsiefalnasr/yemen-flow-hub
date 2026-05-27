// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/support-committee page level
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2, Users, Clock, Mail, Zap, AlertCircle, AlarmClock } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole } from '../../types/enums'
import type { SupportCommitteeDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { getRequestProgress } from '../../utils/requestProgress'
import { Card, CardContent } from '../ui/card'
import { Button } from '../ui/button'
import { Skeleton } from '../ui/skeleton'
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '../ui/table'

const router = useRouter()
const store = useDashboardStore()
const auth = useAuthStore()

const stats = computed<SupportCommitteeDashboardStats | null>(() => {
  const raw = store.stats as Partial<SupportCommitteeDashboardStats> | null
  if (!raw) return null
  return {
    waiting_for_claim: raw.waiting_for_claim ?? 0,
    active_by_me: raw.active_by_me ?? 0,
    claimed_by_others: raw.claimed_by_others ?? 0,
    recently_approved: raw.recently_approved ?? 0,
    support_queue: Array.isArray(raw.support_queue) ? raw.support_queue : [],
  }
})
const queue = computed(() => stats.value?.support_queue ?? [])
const currentUserId = computed(() => auth.user?.id ?? null)

// Active claims = rows in queue currently claimed by me
const myActiveClaims = computed(() =>
  queue.value.filter(req => req.is_claimed_by_me || (currentUserId.value != null && req.claimed_by?.id === currentUserId.value)),
)
const hasActiveClaim = computed(() => myActiveClaims.value.length > 0)
// Oldest active claim — frontend defends the "oldest first" ordering so we
// don't silently break if backend ordering changes (claim age is the source
// of truth, not list position).
const oldestActiveClaim = computed(() => {
  const claims = [...myActiveClaims.value]
  claims.sort((a, b) => {
    const at = a.claimed_at ? new Date(a.claimed_at).getTime() : Number.POSITIVE_INFINITY
    const bt = b.claimed_at ? new Date(b.claimed_at).getTime() : Number.POSITIVE_INFINITY
    return at - bt
  })
  return claims[0] ?? null
})

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function claimOwnerLabel(req: SupportCommitteeDashboardStats['support_queue'][number]): string {
  if (!req.claimed_by) return 'غير مطالب به'
  if (req.is_claimed_by_me || (currentUserId.value != null && req.claimed_by.id === currentUserId.value)) {
    return `${req.claimed_by.name} (أنت)`
  }
  return req.claimed_by.name
}

function getKpiIconColor(variant: string): string {
  const colors: Record<string, string> = {
    green: 'text-success bg-success/10',
    indigo: 'text-voting bg-voting/10',
    amber: 'text-warning bg-warning/10',
    gray: 'text-muted-foreground bg-muted',
  }
  return colors[variant] ?? colors.gray!
}

// Spec order: Waiting for Claim (amber) / Active by Me (indigo) / Claimed by Others (gray) / Recently Approved (green)
const kpiConfig = computed(() => [
  {
    icon: Mail,
    value: stats.value?.waiting_for_claim ?? 0,
    label: 'بانتظار المطالبة',
    variant: (stats.value?.waiting_for_claim ?? 0) > 0 ? 'amber' : 'gray',
    tab: 'waiting',
  },
  {
    icon: Clock,
    value: stats.value?.active_by_me ?? 0,
    label: 'أعمل عليها الآن',
    variant: (stats.value?.active_by_me ?? 0) > 0 ? 'indigo' : 'gray',
    tab: 'my_claims',
  },
  {
    icon: Users,
    value: stats.value?.claimed_by_others ?? 0,
    label: 'محجوزة لأعضاء آخرين',
    variant: 'gray',
    tab: 'in_progress',
  },
  {
    icon: CheckCircle2,
    value: stats.value?.recently_approved ?? 0,
    label: 'اعتُمِدت مؤخراً',
    variant: 'green',
    tab: 'approved',
  },
])

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 4" :key="n" class="border-0 p-4 shadow" aria-hidden="true">
        <Skeleton class="h-3.5 w-[60px] mb-3" />
        <Skeleton class="h-8 w-[40px]" />
      </div>
    </div>

    <!-- Error -->
    <Card v-else-if="store.error" class="border-0 border-s-4 border-s-[var(--severity-red)] bg-background" role="alert">
      <CardContent class="pt-6 flex items-center gap-3">
        <AlertCircle class="w-4.5 h-4.5 flex-shrink-0 text-[var(--severity-red)]" aria-hidden="true" />
        <span class="text-destructive flex-1">{{ store.error }}</span>
        <Button variant="outline" size="sm" class="text-destructive border-destructive" @click="store.loadStats()">
          إعادة المحاولة
        </Button>
      </CardContent>
    </Card>

    <template v-else-if="stats">

      <!-- Active-claim strip (highest priority, indigo) — hidden when no active claims -->
      <Card
        v-if="hasActiveClaim"
        class="border-0 border-s-4 border-s-[var(--voting)] bg-[var(--voting)]/5 shadow-sm"
        role="status"
        aria-label="طلبات نشطة محجوزة باسمك"
      >
        <CardContent class="pt-4 pb-4 flex items-center gap-3">
          <AlarmClock class="h-5 w-5 flex-shrink-0 text-[var(--voting)]" aria-hidden="true" />
          <div class="flex-1 min-w-0">
            <span class="font-semibold text-foreground text-sm">لديك {{ myActiveClaims.length }} طلب نشط محجوز باسمك</span>
            <p v-if="oldestActiveClaim" class="text-xs text-muted-foreground mt-0.5 truncate">
              {{ oldestActiveClaim.reference_number }}
            </p>
          </div>
          <Button
            v-if="oldestActiveClaim"
            size="sm"
            class="flex-shrink-0 bg-[var(--voting)] text-white hover:opacity-90"
            @click="router.push(`/requests/${oldestActiveClaim.id}`)"
          >
            متابعة المراجعة
          </Button>
        </CardContent>
      </Card>

      <!-- KPI grid: 4 clickable cards — spec order: waiting / active / others / approved -->
      <div class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4">
        <template v-for="kpi in kpiConfig" :key="kpi.label">
          <Card
            class="border-0 p-4 shadow flex flex-col gap-1.5 cursor-pointer hover:shadow-md transition-shadow"
            :class="{
              'border-s-4 border-s-[var(--severity-amber)]': kpi.variant === 'amber',
              'border-s-4 border-s-[var(--voting)]': kpi.variant === 'indigo',
            }"
            role="button"
            tabindex="0"
            :aria-label="`${kpi.label}: ${kpi.value}`"
            @click="router.push(`/requests?tab=${kpi.tab}`)"
            @keydown.enter="router.push(`/requests?tab=${kpi.tab}`)"
            @keydown.space.prevent="router.push(`/requests?tab=${kpi.tab}`)"
          >
            <div class="h-9 w-9 rounded flex items-center justify-center flex-shrink-0" :class="getKpiIconColor(kpi.variant)">
              <component :is="kpi.icon" class="h-5 w-5" aria-hidden="true" />
            </div>
            <span
              class="text-2xl font-semibold leading-none"
              :class="{
                'text-[var(--severity-amber)]': kpi.variant === 'amber' && kpi.value > 0,
                'text-[var(--voting)]': kpi.variant === 'indigo' && kpi.value > 0,
                'text-[var(--severity-green)]': kpi.variant === 'green',
                'text-foreground': kpi.variant === 'gray' || kpi.value === 0,
              }"
            >
              {{ kpi.value }}
            </span>
            <span class="text-xs text-muted-foreground">{{ kpi.label }}</span>
          </Card>
        </template>
      </div>

      <!-- Quick actions -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="flex items-center gap-2 text-sm font-semibold text-foreground mb-3">
          <Zap class="h-4 w-4" aria-hidden="true" />
          إجراءات سريعة
        </h2>
        <div class="grid grid-cols-2 max-md:grid-cols-1 gap-3">
          <Card
            class="flex flex-col items-start gap-1 p-4 bg-[var(--voting)] text-white border-0 rounded-2xl cursor-pointer hover:opacity-90 transition-opacity focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="طابور المراجعة"
            @click="router.push('/requests')"
            @keydown.enter="router.push('/requests')"
            @keydown.space.prevent="router.push('/requests')"
          >
            <Users class="h-5 w-5 flex-shrink-0 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">طابور المراجعة</span>
            <span class="text-xs opacity-75">{{ stats.waiting_for_claim }} طلب بانتظار المطالبة</span>
          </Card>

          <Card
            class="flex flex-col items-start gap-1 p-4 bg-background border border-border text-foreground rounded-2xl cursor-pointer hover:border-primary hover:shadow-md transition-all focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="الإشعارات"
            @click="router.push('/notifications')"
            @keydown.enter="router.push('/notifications')"
            @keydown.space.prevent="router.push('/notifications')"
          >
            <Mail class="h-5 w-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">الإشعارات</span>
            <span class="text-xs text-muted-foreground">آخر تحديثات الطابور والقرارات</span>
          </Card>
        </div>
      </section>

      <!-- Support queue table (max 8 rows) -->
      <Card class="border-0 shadow" aria-labelledby="queue-heading">
        <CardContent class="p-4">
          <div class="flex items-center justify-between mb-4">
            <h2 id="queue-heading" class="text-sm font-semibold text-foreground">طابور عملي</h2>
            <Button variant="link" size="sm" class="text-xs h-auto p-0" @click="router.push('/requests')">عرض الكل</Button>
          </div>

          <Table aria-label="طابور عملي">
            <TableHeader>
              <TableRow>
                <TableHead>المرجع</TableHead>
                <TableHead>المورد</TableHead>
                <TableHead>المبلغ</TableHead>
                <TableHead>الحالة</TableHead>
                <TableHead>الحجز</TableHead>
                <TableHead>إجراء</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableEmpty v-if="queue.length === 0" :colspan="6">
                لا توجد طلبات بانتظار المراجعة حالياً ✓
              </TableEmpty>
              <TableRow
                v-for="req in queue.slice(0, 8)"
                :key="req.id"
                class="cursor-pointer"
                :class="{
                  'bg-[var(--voting)]/8 hover:bg-[var(--voting)]/12': req.is_claimed_by_me || (currentUserId != null && req.claimed_by?.id === currentUserId),
                  'bg-muted/40 hover:bg-muted/60': !!req.claimed_by && !req.is_claimed_by_me && req.claimed_by?.id !== currentUserId,
                  'hover:bg-muted/30': !req.claimed_by,
                }"
                @click="router.push(`/requests/${req.id}`)"
              >
                <TableCell>
                  <a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a>
                </TableCell>
                <TableCell>{{ req.supplier_name }}</TableCell>
                <TableCell class="direction-ltr font-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</TableCell>
                <TableCell><StatusBadge :status="req.status" :role="UserRole.SUPPORT_COMMITTEE" /></TableCell>
                <TableCell>
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                    :class="{
                      'bg-[var(--voting)]/10 text-[var(--voting)]': req.is_claimed_by_me || (currentUserId != null && req.claimed_by?.id === currentUserId),
                      'bg-muted text-muted-foreground': !!req.claimed_by && !req.is_claimed_by_me && req.claimed_by?.id !== currentUserId,
                      'bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]': !req.claimed_by,
                    }"
                  >
                    {{ claimOwnerLabel(req) }}
                  </span>
                </TableCell>
                <!-- Claim-state-dependent action button -->
                <TableCell @click.stop>
                  <!-- Unclaimed: primary مطالبة -->
                  <Button
                    v-if="!req.claimed_by"
                    size="sm"
                    class="bg-[var(--voting)] text-white hover:opacity-90"
                    @click="router.push(`/requests/${req.id}`)"
                  >
                    مطالبة
                  </Button>
                  <!-- Claimed by me: outline متابعة -->
                  <Button
                    v-else-if="req.is_claimed_by_me || (currentUserId != null && req.claimed_by?.id === currentUserId)"
                    size="sm"
                    variant="outline"
                    class="border-[var(--voting)] text-[var(--voting)] hover:bg-[var(--voting)]/10"
                    @click="router.push(`/requests/${req.id}`)"
                  >
                    متابعة
                  </Button>
                  <!-- Claimed by others: ghost عرض -->
                  <Button
                    v-else
                    size="sm"
                    variant="outline"
                    @click="router.push(`/requests/${req.id}`)"
                  >
                    عرض
                  </Button>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>

    </template>
  </div>
</template>
