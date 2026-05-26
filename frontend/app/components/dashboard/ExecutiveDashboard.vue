// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/executive page level
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2, XCircle, Vote, Zap, AlertCircle, ClipboardList } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole, RequestStatus } from '../../types/enums'
import type { ExecutiveDashboardStats, VotingQueueItem } from '../../composables/useDashboard'
import type { ImportRequest } from '../../types/models'
import StatusBadge from '../shared/StatusBadge.vue'
import { Card, CardContent } from '../ui/card'

const router = useRouter()
const store = useDashboardStore()
const auth = useAuthStore()

const stats = computed(() => store.stats as ExecutiveDashboardStats | null)
const isDirector = computed(() => auth.user?.role === UserRole.COMMITTEE_DIRECTOR)
const customsDeclarationPending = computed(() => stats.value?.customs_declaration_pending ?? [])

// Voting queue sorted per spec:
// 1. EXECUTIVE_VOTING_OPEN + not voted (oldest first)
// 2. EXECUTIVE_VOTING_OPEN + voted (most recent first)
// 3. EXECUTIVE_VOTING_CLOSED
// 4. SUPPORT_APPROVED / WAITING_FOR_VOTING_OPEN
const sortedVotingQueue = computed((): VotingQueueItem[] => {
  const q = stats.value?.voting_queue ?? []
  const priority = (req: VotingQueueItem): number => {
    if (req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && !req.my_vote) return 0
    if (req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && req.my_vote) return 1
    if (req.status === RequestStatus.EXECUTIVE_VOTING_CLOSED) return 2
    return 3
  }
  return [...q].sort((a, b) => priority(a) - priority(b))
})

// Rows where voting is open and I haven't voted — the most actionable
const pendingMyVoteCount = computed(() =>
  stats.value?.pending_my_vote
  ?? sortedVotingQueue.value.filter(
    r => r.status === RequestStatus.EXECUTIVE_VOTING_OPEN && !r.my_vote,
  ).length,
)

// Oldest pending-my-vote session for the action strip
const oldestPendingVote = computed(() =>
  sortedVotingQueue.value.find(
    r => r.status === RequestStatus.EXECUTIVE_VOTING_OPEN && !r.my_vote,
  ) ?? null,
)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

// KPI cards — spec: My Voting Queue (indigo) / Approval (green) / Rejection (rose)
// 3 cards for EXECUTIVE_MEMBER, extended set for COMMITTEE_DIRECTOR
const kpiConfig = computed(() => {
  const base = [
    {
      icon: Vote,
      value: pendingMyVoteCount.value,
      label: 'طابور التصويت',
      variant: pendingMyVoteCount.value > 0 ? 'indigo' : 'gray',
      tab: 'pending_my_vote',
    },
    {
      icon: CheckCircle2,
      value: stats.value?.decisions_approved ?? 0,
      label: 'قرارات اعتماد',
      variant: 'green',
      tab: 'approved',
    },
    {
      icon: XCircle,
      value: stats.value?.decisions_rejected ?? 0,
      label: 'قرارات رفض',
      variant: 'rose',
      tab: 'rejected',
    },
  ]
  return base
})

function getKpiIconColor(variant: string): string {
  const colors: Record<string, string> = {
    indigo: 'text-[#5856d6] bg-[#5856d6]/10',
    green: 'text-green-700 bg-green-50/10',
    rose: 'text-rose-600 bg-rose-50/10',
    gray: 'text-muted-foreground bg-muted',
  }
  return colors[variant] ?? colors.gray
}

function myVoteLabel(req: VotingQueueItem): { text: string; cls: string } {
  if (req.status !== RequestStatus.EXECUTIVE_VOTING_OPEN && req.status !== RequestStatus.EXECUTIVE_VOTING_CLOSED) {
    return { text: '—', cls: 'text-muted-foreground' }
  }
  if (!req.my_vote) {
    return { text: 'لم تصوّت بعد', cls: 'bg-[#5856d6]/10 text-[#5856d6] px-2 py-0.5 rounded-full font-semibold' }
  }
  if (req.my_vote === 'approve') {
    return { text: 'اعتمدت', cls: 'bg-green-50/50 text-green-700 px-2 py-0.5 rounded-full font-semibold' }
  }
  return { text: 'رفضت', cls: 'bg-rose-50/50 text-rose-600 px-2 py-0.5 rounded-full font-semibold' }
}

function votingProgressText(req: VotingQueueItem): string {
  if (req.votes_cast == null || req.total_voters == null) return '—'
  return `${req.votes_cast}/${req.total_voters} صوتوا`
}

function votingProgressPct(req: VotingQueueItem): number {
  if (!req.votes_cast || !req.total_voters) return 0
  return Math.round((req.votes_cast / req.total_voters) * 100)
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-3 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 3" :key="n" class="border-0 p-4 shadow animate-pulse" aria-hidden="true">
        <div class="h-3.5 w-15 bg-muted rounded mb-3" />
        <div class="h-8 w-10 bg-muted rounded" />
      </div>
    </div>

    <!-- Error -->
    <Card v-else-if="store.error" class="border-l-4 border-destructive border-b border-border border-r bg-background" role="alert">
      <CardContent class="pt-6 flex items-center gap-3">
        <AlertCircle class="w-4.5 h-4.5 flex-shrink-0 text-destructive" aria-hidden="true" />
        <span class="text-destructive flex-1">{{ store.error }}</span>
        <button class="px-4 py-1.5 bg-background border border-destructive rounded-lg text-destructive text-sm cursor-pointer hover:bg-destructive/10 transition-colors" @click="store.loadStats()">إعادة المحاولة</button>
      </CardContent>
    </Card>

    <template v-else-if="stats">

      <!-- Pending-vote action strip (highest priority, indigo) — hidden when count = 0 -->
      <Card
        v-if="!isDirector && pendingMyVoteCount > 0"
        class="border-0 border-s-4 border-s-[#5856d6] bg-[#5856d6]/5 shadow-sm"
        role="status"
        aria-label="جلسات تصويت تنتظر صوتك"
      >
        <CardContent class="pt-4 pb-4 flex items-center gap-3">
          <Vote class="h-5 w-5 flex-shrink-0 text-[#5856d6]" aria-hidden="true" />
          <div class="flex-1 min-w-0">
            <span class="font-semibold text-foreground text-sm">{{ pendingMyVoteCount }} جلسات تصويت تنتظر صوتك</span>
            <p v-if="oldestPendingVote" class="text-xs text-muted-foreground mt-0.5 truncate">
              {{ oldestPendingVote.reference_number }}
            </p>
          </div>
          <button
            v-if="oldestPendingVote"
            class="flex-shrink-0 px-3 py-1.5 bg-[#5856d6] text-white text-xs font-semibold rounded-xl hover:opacity-90 transition-opacity"
            @click="router.push(`/requests/${oldestPendingVote.id}`)"
          >
            ابدأ التصويت
          </button>
        </CardContent>
      </Card>

      <!-- KPI grid: 3 cards for EXECUTIVE_MEMBER -->
      <div class="grid grid-cols-3 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4">
        <template v-for="kpi in kpiConfig" :key="kpi.label">
          <Card
            class="border-0 p-4 shadow flex flex-col gap-1.5 cursor-pointer hover:shadow-md transition-shadow"
            :class="{ 'border-s-4 border-s-[#5856d6]': kpi.variant === 'indigo' }"
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
                'text-[#5856d6]': kpi.variant === 'indigo' && kpi.value > 0,
                'text-green-700': kpi.variant === 'green',
                'text-rose-600': kpi.variant === 'rose' && kpi.value > 0,
                'text-foreground': kpi.value === 0 || kpi.variant === 'gray',
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
          <button class="flex flex-col items-start gap-1 p-4 bg-[#5856d6] text-white border-0 rounded-2xl cursor-pointer hover:opacity-90 transition-opacity" @click="router.push('/requests')">
            <Vote class="h-5 w-5 flex-shrink-0 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">طابور التصويت</span>
            <span class="text-xs opacity-75">{{ stats.active_voting_sessions }} طلب بانتظار التصويت</span>
          </button>

          <button class="flex flex-col items-start gap-1 p-4 bg-background border border-border text-foreground rounded-2xl cursor-pointer hover:border-primary hover:shadow-md transition-all" @click="router.push('/reports')">
            <ClipboardList class="h-5 w-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">التقارير</span>
            <span class="text-xs text-muted-foreground">تقارير التصويت والقرارات</span>
          </button>
        </div>
      </section>

      <!-- Voting queue table -->
      <section aria-labelledby="voting-queue-heading">
        <Card class="border-0 shadow" aria-labelledby="voting-queue-heading">
          <CardContent class="p-4">
            <div class="flex items-center justify-between mb-4">
              <h2 id="voting-queue-heading" class="text-sm font-semibold text-foreground">طلبات بانتظار تصويتك</h2>
              <a class="text-xs text-primary hover:underline transition-colors cursor-pointer" @click="router.push('/requests?tab=pending_my_vote')">عرض الكل</a>
            </div>

            <!-- Empty — reassuring -->
            <div
              v-if="sortedVotingQueue.length === 0"
              class="py-10 flex flex-col items-center gap-3 text-center text-sm text-muted-foreground"
              role="status"
            >
              <CheckCircle2 class="h-8 w-8 text-muted-foreground" aria-hidden="true" />
              <p>لا توجد جلسات تصويت نشطة حالياً ✓</p>
            </div>

            <table v-else class="w-full border-collapse text-xs" role="table" aria-label="طلبات بانتظار تصويتك">
              <thead>
                <tr class="border-b border-border">
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المرجع</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المورد</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المبلغ</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">الحالة</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">صوتي</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">تقدم التصويت</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="req in sortedVotingQueue"
                  :key="req.id"
                  class="border-t border-muted transition-colors cursor-pointer"
                  :class="{
                    'bg-[#5856d6]/8 hover:bg-[#5856d6]/12 border-s-2 border-s-[#5856d6]': req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && !req.my_vote,
                    'hover:bg-muted/30': !(req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && !req.my_vote),
                  }"
                  @click="router.push(`/requests/${req.id}`)"
                >
                  <td class="py-2 px-2">
                    <a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a>
                  </td>
                  <td class="py-2 px-2 text-foreground">{{ req.supplier_name }}</td>
                  <td class="py-2 px-2 text-foreground direction-ltr font-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</td>
                  <td class="py-2 px-2"><StatusBadge :status="req.status" :role="UserRole.EXECUTIVE_MEMBER" /></td>
                  <td class="py-2 px-2">
                    <span :class="myVoteLabel(req).cls">{{ myVoteLabel(req).text }}</span>
                  </td>
                  <td class="py-2 px-2">
                    <div v-if="req.votes_cast != null && req.total_voters != null" class="flex items-center gap-2 min-w-20">
                      <div class="flex-1 h-1.5 bg-muted rounded-full overflow-hidden">
                        <div class="h-full bg-[#5856d6] transition-all" :style="{ width: `${votingProgressPct(req)}%` }" />
                      </div>
                      <span class="text-xs text-muted-foreground whitespace-nowrap">{{ votingProgressText(req) }}</span>
                    </div>
                    <span v-else class="text-muted-foreground">—</span>
                  </td>
                  <td class="py-2 px-2" @click.stop>
                    <!-- Pending my vote: primary "تصويت" -->
                    <button
                      v-if="req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && !req.my_vote"
                      class="px-2 py-1 bg-[#5856d6] text-white text-xs font-semibold rounded hover:opacity-90 transition-opacity"
                      @click="router.push(`/requests/${req.id}`)"
                    >
                      تصويت
                    </button>
                    <!-- Already voted or other state: ghost "عرض" -->
                    <button
                      v-else
                      class="px-2 py-1 bg-background border border-border text-xs text-foreground rounded hover:border-primary hover:text-primary transition-colors"
                      @click="router.push(`/requests/${req.id}`)"
                    >
                      عرض
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </CardContent>
        </Card>
      </section>

      <!-- Director-only: customs declaration queue -->
      <section v-if="isDirector" aria-labelledby="customs-heading">
        <Card class="border-0 shadow">
          <CardContent class="p-4">
            <div class="flex items-center justify-between mb-4">
              <h2 id="customs-heading" class="text-sm font-semibold text-foreground">بيانات جمركية بانتظار الإصدار</h2>
            </div>
            <div v-if="customsDeclarationPending.length === 0" class="py-8 text-center text-sm text-muted-foreground" role="status">
              لا توجد بيانات جمركية بانتظار الإصدار حالياً ✓
            </div>
            <table v-else class="w-full border-collapse text-xs" role="table" aria-label="طلبات بانتظار إصدار البيان الجمركي">
              <thead>
                <tr class="border-b border-border">
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المرجع</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">البنك</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">المبلغ</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">الحالة</th>
                  <th scope="col" class="py-2 px-2 text-right font-medium text-muted-foreground">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="req in customsDeclarationPending" :key="req.id" class="border-t border-muted hover:bg-muted/30 transition-colors cursor-pointer" @click="router.push(`/requests/${req.id}`)">
                  <td class="py-2 px-2"><a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
                  <td class="py-2 px-2 text-foreground">{{ req.bank_name ?? '—' }}</td>
                  <td class="py-2 px-2 text-foreground direction-ltr font-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</td>
                  <td class="py-2 px-2"><StatusBadge :status="req.status" :role="UserRole.COMMITTEE_DIRECTOR" /></td>
                  <td class="py-2 px-2" @click.stop>
                    <button class="px-2 py-1 bg-primary text-primary-foreground text-xs font-semibold rounded hover:opacity-90 transition-opacity" @click="router.push(`/requests/${req.id}`)">إصدار</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </CardContent>
        </Card>
      </section>

    </template>
  </div>
</template>
