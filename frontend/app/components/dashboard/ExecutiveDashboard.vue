// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/executive page level
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, CheckCircle2, FileCheck2, Globe, Scale, Vote, XCircle } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { RequestStatus, UserRole } from '../../types/enums'
import type { ExecutiveDashboardStats, VotingQueueItem } from '../../composables/useDashboard'
import type { ImportRequest } from '../../types/models'
import StatusBadge from '../shared/StatusBadge.vue'
import { Badge } from '../ui/badge'

const router = useRouter()
const store = useDashboardStore()
const auth = useAuthStore()

const stats = computed(() => store.stats as ExecutiveDashboardStats | null)
const isDirector = computed(() => auth.user?.role === UserRole.COMMITTEE_DIRECTOR)

const votingQueue = computed<VotingQueueItem[]>(() =>
  isDirector.value
    ? (stats.value?.voting_lifecycle_queue ?? stats.value?.voting_queue ?? [])
    : (stats.value?.voting_queue ?? []),
)

const fxQueue = computed<ImportRequest[]>(() => stats.value?.fx_confirmation_queue ?? [])

// Single source of truth for "pending my vote" — used by KPI count, oldest-row
// drilldown, and the row-action label. Keeps the rule consistent across surfaces.
function isPendingMyVote(req: VotingQueueItem): boolean {
  return req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && !req.my_vote
}

const pendingMyVoteCount = computed(() =>
  stats.value?.pending_my_vote ?? votingQueue.value.filter(isPendingMyVote).length,
)

const oldestPendingVote = computed(() =>
  votingQueue.value.find(isPendingMyVote) ?? null,
)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function ageHours(value?: string): number {
  if (!value) return 0
  const ts = new Date(value).getTime()
  if (Number.isNaN(ts)) return 0
  return Math.max(0, Math.floor((Date.now() - ts) / (1000 * 60 * 60)))
}

function rowAction(req: VotingQueueItem): string {
  if (isPendingMyVote(req)) return 'تصويت'
  if (req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && req.ready_to_close) return 'إغلاق الجلسة'
  if (req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && req.is_tie) return 'حسم التعادل'
  if (req.status === RequestStatus.EXECUTIVE_VOTING_CLOSED) return 'إصدار نهائي'
  return 'عرض'
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" >

    <!-- CBY-global scope chip -->
    <div class="mb-2">
      <Badge variant="outline" class="gap-1.5 rounded-full px-3 py-1 text-xs font-medium text-muted-foreground border-border">
        <Globe class="size-3" aria-hidden="true" />
        نطاق عبر البنوك
      </Badge>
    </div>

    <div v-if="store.loading" class="space-y-4" aria-busy="true">
      <div class="grid grid-cols-3 gap-4 max-lg:grid-cols-2 max-md:grid-cols-1">
        <div v-for="n in (isDirector ? 4 : 3)" :key="n" class="h-24 animate-pulse rounded-xl border border-border bg-muted" />
      </div>
      <div v-if="isDirector" class="rounded-xl border border-border bg-background">
        <div class="h-10 animate-pulse border-b border-border bg-muted/50" />
        <div class="space-y-2 p-3">
          <div v-for="n in 4" :key="`voting-skel-${n}`" class="h-8 animate-pulse rounded bg-muted" />
        </div>
      </div>
      <div v-if="isDirector" class="rounded-xl border border-border bg-background">
        <div class="h-10 animate-pulse border-b border-border bg-muted/50" />
        <div class="space-y-2 p-3">
          <div v-for="n in 4" :key="`fx-skel-${n}`" class="h-8 animate-pulse rounded bg-muted" />
        </div>
      </div>
    </div>

    <div v-else-if="store.error" class="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-destructive">
      {{ store.error }}
    </div>

    <template v-else-if="stats">
      <template v-if="isDirector">
        <div
          v-if="(stats.sessions_ready_to_close ?? 0) > 0 || (stats.sessions_with_tie ?? 0) > 0 || (stats.fx_confirmation_pending ?? 0) > 0"
          class="rounded-xl border border-border bg-background p-3"
        >
          <div class="flex flex-col gap-2">
            <button
              v-if="(stats.sessions_ready_to_close ?? 0) > 0"
              class="flex items-center gap-3 rounded-lg border border-[var(--voting)]/30 bg-[var(--voting)]/5 px-3 py-2 text-right"
              @click="router.push('/requests?tab=ready_to_close')"
            >
              <AlertTriangle class="h-4 w-4 text-[var(--voting)]" />
              <span class="text-sm">{{ stats.sessions_ready_to_close }} جلسات تصويت اكتملت وتنتظر الإغلاق</span>
            </button>
            <button
              v-if="(stats.sessions_with_tie ?? 0) > 0"
              class="flex items-center gap-3 rounded-lg border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/5 px-3 py-2 text-right"
              @click="router.push('/requests?tab=tie_break')"
            >
              <Scale class="h-4 w-4 text-[var(--severity-amber)]" />
              <span class="text-sm">{{ stats.sessions_with_tie }} جلسات تصويت بتعادل — يتطلب حسماً</span>
            </button>
            <button
              v-if="(stats.fx_confirmation_pending ?? 0) > 0"
              class="flex items-center gap-3 rounded-lg border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/5 px-3 py-2 text-right"
              @click="router.push('/requests?tab=fx_pending')"
            >
              <FileCheck2 class="h-4 w-4 text-[var(--severity-green)]" />
              <span class="text-sm">{{ stats.fx_confirmation_pending }} طلبات جاهزة لإتمام تأكيد المصارفة الخارجية</span>
            </button>
          </div>
        </div>

        <div class="grid grid-cols-4 gap-4 max-lg:grid-cols-2 max-md:grid-cols-1">
          <button class="rounded-xl border border-border bg-background p-4 text-start hover:shadow-sm" @click="router.push('/requests?tab=active_voting')">
            <div class="mb-2 inline-flex h-9 w-9 items-center justify-center rounded bg-[var(--voting)]/10 text-[var(--voting)]"><Vote class="h-5 w-5" /></div>
            <p class="text-2xl font-semibold text-[var(--voting)]">{{ stats.active_voting_sessions }}</p>
            <p class="text-xs text-muted-foreground">جلسات التصويت النشطة</p>
          </button>
          <button class="rounded-xl border border-border bg-background p-4 text-start hover:shadow-sm" @click="router.push('/requests?tab=fx_pending')">
            <div class="mb-2 inline-flex h-9 w-9 items-center justify-center rounded bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]"><FileCheck2 class="h-5 w-5" /></div>
            <p class="text-2xl font-semibold text-[var(--severity-amber)]">{{ stats.fx_confirmation_pending ?? stats.decisions_approved }}</p>
            <p class="text-xs text-muted-foreground">بانتظار تأكيد المصارفة</p>
          </button>
          <button class="rounded-xl border border-border bg-background p-4 text-start hover:shadow-sm" @click="router.push('/requests?tab=finalized')">
            <div class="mb-2 inline-flex h-9 w-9 items-center justify-center rounded bg-[var(--severity-green)]/10 text-[var(--severity-green)]"><CheckCircle2 class="h-5 w-5" /></div>
            <p class="text-2xl font-semibold text-[var(--severity-green)]">{{ stats.finalized_approved ?? stats.decisions_approved }}</p>
            <p class="text-xs text-muted-foreground">قرارات مُنهاة (اعتماد)</p>
          </button>
          <button class="rounded-xl border border-border bg-background p-4 text-start hover:shadow-sm" @click="router.push('/requests?tab=rejected')">
            <div class="mb-2 inline-flex h-9 w-9 items-center justify-center rounded bg-[var(--severity-red)]/10 text-[var(--severity-red)]"><XCircle class="h-5 w-5" /></div>
            <p class="text-2xl font-semibold text-[var(--severity-red)]">{{ stats.finalized_rejected ?? stats.decisions_rejected }}</p>
            <p class="text-xs text-muted-foreground">قرارات مُرفوضة</p>
          </button>
        </div>

        <section class="rounded-xl border border-border bg-background">
          <div class="border-b border-border px-4 py-3">
            <h2 class="text-sm font-semibold">جلسات التصويت — نظرة عامة</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead>
                <tr class="bg-muted/40">
                  <th class="px-3 py-2 text-right">المرجع</th>
                  <th class="px-3 py-2 text-right">التاجر</th>
                  <th class="px-3 py-2 text-right">المبلغ</th>
                  <th class="px-3 py-2 text-right">الأصوات</th>
                  <th class="px-3 py-2 text-right">الحالة</th>
                  <th class="px-3 py-2 text-right">إجراء المدير</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="req in votingQueue" :key="req.id" class="border-t border-border hover:bg-muted/20">
                  <td class="px-3 py-2 font-mono text-primary">{{ req.reference_number }}</td>
                  <td class="px-3 py-2">{{ req.merchant?.name ?? req.supplier_name }}</td>
                  <td class="px-3 py-2 font-mono">{{ formatAmount(req.amount, req.currency) }}</td>
                  <td class="px-3 py-2">{{ req.votes_cast ?? 0 }} / {{ req.total_voters ?? 0 }}</td>
                  <td class="px-3 py-2"><StatusBadge :status="req.status" :role="UserRole.COMMITTEE_DIRECTOR" /></td>
                  <td class="px-3 py-2">
                    <Button size="sm" variant="outline" @click="router.push(`/requests/${req.id}`)">{{ rowAction(req) }}</Button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="rounded-xl border border-border bg-background">
          <div class="border-b border-border px-4 py-3">
            <h2 class="text-sm font-semibold">قائمة انتظار تأكيد المصارفة الخارجية</h2>
          </div>
          <div v-if="fxQueue.length === 0" class="p-8 text-center text-sm text-muted-foreground">
            لا توجد طلبات في انتظار تأكيد المصارفة ✓
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead>
                <tr class="bg-muted/40">
                  <th class="px-3 py-2 text-right">المرجع</th>
                  <th class="px-3 py-2 text-right">التاجر</th>
                  <th class="px-3 py-2 text-right">المبلغ</th>
                  <th class="px-3 py-2 text-right">العمر</th>
                  <th class="px-3 py-2 text-right">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="req in fxQueue" :key="req.id" class="border-t border-border hover:bg-muted/20">
                  <td class="px-3 py-2 font-mono text-primary">{{ req.reference_number }}</td>
                  <td class="px-3 py-2">{{ req.merchant?.name ?? req.supplier_name }}</td>
                  <td class="px-3 py-2 font-mono">{{ formatAmount(req.amount, req.currency) }}</td>
                  <td class="px-3 py-2" :class="ageHours(req.updated_at) > 24 ? 'text-[var(--severity-amber)]' : 'text-muted-foreground'">{{ ageHours(req.updated_at) }} ساعة</td>
                  <td class="px-3 py-2">
                    <Button size="sm" variant="outline" @click="router.push(`/requests/${req.id}`)">إتمام التأكيد</Button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>

      <template v-else>
        <div
          v-if="pendingMyVoteCount > 0"
          class="rounded-xl border border-[var(--voting)]/40 bg-[var(--voting)]/5 p-4"
        >
          <div class="flex items-center gap-3">
            <Vote class="h-5 w-5 text-[var(--voting)]" />
            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold">{{ pendingMyVoteCount }} جلسات تصويت تنتظر صوتك</p>
              <p v-if="oldestPendingVote" class="truncate text-xs text-muted-foreground">{{ oldestPendingVote.reference_number }}</p>
            </div>
            <Button class="bg-[var(--voting)] text-white hover:bg-[var(--voting)]/90" @click="oldestPendingVote && router.push(`/requests/${oldestPendingVote.id}`)">
              ابدأ التصويت
            </Button>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4 max-lg:grid-cols-2 max-md:grid-cols-1">
          <button class="rounded-xl border border-border bg-background p-4 text-start hover:shadow-sm" @click="router.push('/requests?tab=pending_my_vote')">
            <p class="text-2xl font-semibold text-[var(--voting)]">{{ pendingMyVoteCount }}</p>
            <p class="text-xs text-muted-foreground">طابور التصويت</p>
          </button>
          <button class="rounded-xl border border-border bg-background p-4 text-start hover:shadow-sm" @click="router.push('/requests?tab=approved')">
            <p class="text-2xl font-semibold text-[var(--severity-green)]">{{ stats.decisions_approved }}</p>
            <p class="text-xs text-muted-foreground">قرارات اعتماد</p>
          </button>
          <button class="rounded-xl border border-border bg-background p-4 text-start hover:shadow-sm" @click="router.push('/requests?tab=rejected')">
            <p class="text-2xl font-semibold text-[var(--severity-red)]">{{ stats.decisions_rejected }}</p>
            <p class="text-xs text-muted-foreground">قرارات رفض</p>
          </button>
        </div>
      </template>
    </template>
  </div>
</template>
