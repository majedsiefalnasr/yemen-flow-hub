// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/executive page
level
<script setup lang="ts">
import type { ColumnDef } from '@tanstack/vue-table'
import { computed, h, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  AlertTriangle,
  CheckCircle2,
  FileCheck2,
  Globe,
  Scale,
  Vote,
  XCircle,
} from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { RequestStatus, UserRole } from '../../types/enums'
import { NOT_ELIGIBLE_EXECUTIVE_LABEL } from '../../constants/workflow'
import type { ExecutiveDashboardStats, VotingQueueItem } from '../../composables/useDashboard'
import type { ImportRequest } from '../../types/models'
import StatusBadge from '../shared/StatusBadge.vue'
import { Badge } from '../ui/badge'
import { Button } from '../ui/button'
import { Skeleton } from '../ui/skeleton'
import DataTable from '../ui/data-table/DataTable.vue'
import MetricCard from '../shared/dashboard/MetricCard.vue'
import MetricGrid from '../shared/dashboard/MetricGrid.vue'
import LoadErrorAlert from '../shared/LoadErrorAlert.vue'

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

const pendingMyVoteCount = computed(
  () => stats.value?.pending_my_vote ?? votingQueue.value.filter(isPendingMyVote).length,
)

const oldestPendingVote = computed(() => votingQueue.value.find(isPendingMyVote) ?? null)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', {
    style: 'currency',
    currency,
    minimumFractionDigits: 0,
  }).format(amount)
}

function ageHours(value?: string): number {
  if (!value) return 0
  const ts = new Date(value).getTime()
  if (Number.isNaN(ts)) return 0
  return Math.max(0, Math.floor((Date.now() - ts) / (1000 * 60 * 60)))
}

function rowAction(req: VotingQueueItem): string {
  if (isPendingMyVote(req)) return 'تصويت'
  if (req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && req.ready_to_close)
    return 'إغلاق الجلسة'
  if (req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && req.is_tie) return 'حسم التعادل'
  if (req.status === RequestStatus.EXECUTIVE_VOTING_CLOSED) return 'إصدار نهائي'
  return 'عرض'
}

const votingQueueColumns: ColumnDef<VotingQueueItem>[] = [
  {
    accessorKey: 'reference_number',
    header: 'المرجع',
    cell: ({ row }) =>
      h('span', { class: 'font-mono text-primary' }, row.original.reference_number),
  },
  {
    id: 'merchant',
    header: 'المستورد',
    cell: ({ row }) =>
      h('span', row.original.merchant?.name ?? row.original.supplier_name ?? 'غير متاح'),
  },
  {
    id: 'amount',
    header: 'المبلغ',
    cell: ({ row }) =>
      h('span', { class: 'font-mono' }, formatAmount(row.original.amount, row.original.currency)),
  },
  {
    id: 'votes',
    header: 'الأصوات',
    cell: ({ row }) =>
      h('span', `${row.original.votes_cast ?? 0} / ${row.original.total_voters ?? 0}`),
  },
  {
    id: 'status',
    header: 'الحالة',
    cell: ({ row }) =>
      h(StatusBadge as any, { status: row.original.status, role: UserRole.COMMITTEE_DIRECTOR }),
  },
  {
    id: 'action',
    header: 'إجراء المدير',
    cell: ({ row }) =>
      h(
        Button,
        {
          size: 'sm',
          variant: 'outline',
          onClick: (e: Event) => {
            e.stopPropagation()
            router.push(`/requests/${row.original.id}`)
          },
        },
        () => rowAction(row.original),
      ),
  },
]

const fxQueueColumns: ColumnDef<ImportRequest>[] = [
  {
    accessorKey: 'reference_number',
    header: 'المرجع',
    cell: ({ row }) =>
      h('span', { class: 'font-mono text-primary' }, row.original.reference_number),
  },
  {
    id: 'merchant',
    header: 'المستورد',
    cell: ({ row }) =>
      h('span', row.original.merchant?.name ?? row.original.supplier_name ?? 'غير متاح'),
  },
  {
    id: 'amount',
    header: 'المبلغ',
    cell: ({ row }) =>
      h('span', { class: 'font-mono' }, formatAmount(row.original.amount, row.original.currency)),
  },
  {
    id: 'age',
    header: 'العمر',
    cell: ({ row }) =>
      h(
        'span',
        {
          class:
            ageHours(row.original.updated_at) > 24
              ? 'text-[var(--severity-amber)]'
              : 'text-muted-foreground',
        },
        `${ageHours(row.original.updated_at)} ساعة`,
      ),
  },
  {
    id: 'action',
    header: 'إجراء',
    cell: ({ row }) =>
      h(
        Button,
        {
          size: 'sm',
          variant: 'outline',
          onClick: (e: Event) => {
            e.stopPropagation()
            router.push(`/requests/${row.original.id}`)
          },
        },
        () => 'إتمام التأكيد',
      ),
  },
]

onMounted(() => {
  store.loadStats()
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- CBY-global scope chip -->
    <div class="mb-2">
      <Badge
        variant="outline"
        class="text-muted-foreground border-border gap-1.5 rounded-full px-3 py-1 text-xs font-medium"
      >
        <Globe class="size-3" aria-hidden="true" />
        نطاق عبر البنوك
      </Badge>
    </div>

    <div v-if="store.loading" class="space-y-4" aria-busy="true">
      <div class="grid grid-cols-3 gap-4 max-lg:grid-cols-2 max-md:grid-cols-1">
        <Skeleton v-for="n in isDirector ? 4 : 3" :key="n" class="h-24 rounded-xl" />
      </div>
      <div v-if="isDirector" class="border-border bg-background rounded-xl border">
        <Skeleton class="border-border h-10 rounded-none border-b" />
        <div class="space-y-2 p-3">
          <Skeleton v-for="n in 4" :key="`voting-skel-${n}`" class="h-8 rounded" />
        </div>
      </div>
      <div v-if="isDirector" class="border-border bg-background rounded-xl border">
        <Skeleton class="border-border h-10 rounded-none border-b" />
        <div class="space-y-2 p-3">
          <Skeleton v-for="n in 4" :key="`fx-skel-${n}`" class="h-8 rounded" />
        </div>
      </div>
    </div>

    <LoadErrorAlert
      v-else-if="store.error"
      :message="store.error"
      title="تعذّر تحميل لوحة التصويت"
      @retry="store.loadStats()"
    />

    <template v-else-if="stats">
      <template v-if="isDirector">
        <div
          v-if="
            (stats.sessions_ready_to_close ?? 0) > 0 ||
            (stats.sessions_with_tie ?? 0) > 0 ||
            (stats.fx_confirmation_pending ?? 0) > 0
          "
          class="border-border bg-background rounded-xl border p-3"
        >
          <div class="flex flex-col gap-2">
            <Button
              v-if="(stats.sessions_ready_to_close ?? 0) > 0"
              variant="outline"
              class="h-auto justify-start gap-3 px-3 py-2 text-start"
              @click="router.push('/requests?tab=ready_to_close')"
            >
              <AlertTriangle class="h-4 w-4" />
              <span class="text-sm"
                >{{ stats.sessions_ready_to_close }} جلسات تصويت اكتملت وتنتظر الإغلاق</span
              >
            </Button>
            <Button
              v-if="(stats.sessions_with_tie ?? 0) > 0"
              variant="outline"
              class="h-auto justify-start gap-3 px-3 py-2 text-start"
              @click="router.push('/requests?tab=tie_break')"
            >
              <Scale class="h-4 w-4" />
              <span class="text-sm"
                >{{ stats.sessions_with_tie }} جلسات تصويت بتعادل، تتطلب حسماً</span
              >
            </Button>
            <Button
              v-if="(stats.fx_confirmation_pending ?? 0) > 0"
              variant="outline"
              class="h-auto justify-start gap-3 px-3 py-2 text-start"
              @click="router.push('/requests?tab=fx_pending')"
            >
              <FileCheck2 class="h-4 w-4" />
              <span class="text-sm"
                >{{ stats.fx_confirmation_pending }} طلبات جاهزة لإتمام تأكيد المصارفة
                الخارجية</span
              >
            </Button>
          </div>
        </div>

        <MetricGrid :columns="4">
          <MetricCard
            label="جلسات التصويت النشطة"
            :value="stats.active_voting_sessions"
            :icon="Vote"
            tone="voting"
            :highlighted="stats.active_voting_sessions > 0"
            @click="router.push('/requests?tab=active_voting')"
          />
          <MetricCard
            label="بانتظار تأكيد المصارفة"
            :value="stats.fx_confirmation_pending ?? stats.decisions_approved"
            :icon="FileCheck2"
            tone="warning"
            :highlighted="(stats.fx_confirmation_pending ?? stats.decisions_approved) > 0"
            @click="router.push('/requests?tab=fx_pending')"
          />
          <MetricCard
            label="قرارات مُعتمدة نهائياً"
            :value="stats.finalized_approved ?? stats.decisions_approved"
            :icon="CheckCircle2"
            tone="success"
            @click="router.push('/requests?tab=finalized')"
          />
          <MetricCard
            label="قرارات مُرفوضة"
            :value="stats.finalized_rejected ?? stats.decisions_rejected"
            :icon="XCircle"
            tone="danger"
            :highlighted="(stats.finalized_rejected ?? stats.decisions_rejected) > 0"
            @click="router.push('/requests?tab=rejected')"
          />
        </MetricGrid>

        <section class="border-border bg-background rounded-xl border">
          <div class="border-border border-b px-4 py-3">
            <h2 class="text-sm font-semibold">جلسات التصويت، نظرة عامة</h2>
          </div>
          <div class="p-4">
            <DataTable
              :data="votingQueue"
              :columns="votingQueueColumns"
              @row-click="(row) => router.push(`/requests/${row.id}`)"
            />
          </div>
        </section>

        <section class="border-border bg-background rounded-xl border">
          <div class="border-border border-b px-4 py-3">
            <h2 class="text-sm font-semibold">قائمة انتظار تأكيد المصارفة الخارجية</h2>
          </div>
          <div v-if="fxQueue.length === 0" class="text-muted-foreground p-8 text-center text-sm">
            لا توجد طلبات في انتظار تأكيد المصارفة ✓
          </div>
          <div v-else class="p-4">
            <DataTable
              :data="fxQueue"
              :columns="fxQueueColumns"
              @row-click="(row) => router.push(`/requests/${row.id}`)"
            />
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
              <p v-if="oldestPendingVote" class="text-muted-foreground truncate text-xs">
                {{ oldestPendingVote.reference_number }}
              </p>
            </div>
            <Button @click="oldestPendingVote && router.push(`/requests/${oldestPendingVote.id}`)">
              ابدأ التصويت
            </Button>
          </div>
        </div>

        <MetricGrid :columns="3">
          <MetricCard
            label="طابور التصويت"
            :value="pendingMyVoteCount"
            :icon="Vote"
            tone="voting"
            :highlighted="pendingMyVoteCount > 0"
            @click="router.push('/requests?tab=pending_my_vote')"
          />
          <MetricCard
            label="قرارات اعتماد"
            :value="stats.decisions_approved"
            :icon="CheckCircle2"
            tone="success"
            @click="router.push('/requests?tab=approved')"
          />
          <MetricCard
            :label="NOT_ELIGIBLE_EXECUTIVE_LABEL"
            :value="stats.decisions_rejected"
            :icon="XCircle"
            tone="danger"
            :highlighted="stats.decisions_rejected > 0"
            @click="router.push('/requests?tab=rejected')"
          />
        </MetricGrid>
      </template>
    </template>
  </div>
</template>
