// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/swift-officer page level
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, CheckCircle2, Clock3, UploadCloud, XCircle } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import type { SwiftOfficerDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { UserRole } from '../../types/enums'

const router = useRouter()
const store = useDashboardStore()

const stats = computed<SwiftOfficerDashboardStats | null>(() => {
  const raw = store.stats as Partial<SwiftOfficerDashboardStats> | null
  if (!raw) return null
  return {
    pending_swift_upload: raw.pending_swift_upload ?? 0,
    uploaded: raw.uploaded ?? 0,
    final_approved: raw.final_approved ?? 0,
    final_rejected: raw.final_rejected ?? 0,
    swift_queue: Array.isArray(raw.swift_queue) ? raw.swift_queue : [],
  }
})

const queue = computed(() =>
  [...(stats.value?.swift_queue ?? [])].sort((a, b) => {
    const at = new Date(a.updated_at ?? a.created_at ?? 0).getTime()
    const bt = new Date(b.updated_at ?? b.created_at ?? 0).getTime()
    return at - bt
  }),
)

const oldestPending = computed(() => queue.value[0] ?? null)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function hoursInStage(updatedAt: string): number {
  const updated = new Date(updatedAt).getTime()
  if (Number.isNaN(updated)) return 0
  return Math.max(0, Math.floor((Date.now() - updated) / (1000 * 60 * 60)))
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">
    <div v-if="store.loading" class="grid grid-cols-4 gap-4 md:grid-cols-2 sm:grid-cols-1" aria-busy="true">
      <div v-for="n in 4" :key="n" class="h-24 animate-pulse rounded-xl border border-border bg-muted" />
    </div>

    <div v-else-if="store.error" class="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-destructive">
      {{ store.error }}
    </div>

    <template v-else-if="stats">
      <div
        v-if="stats.pending_swift_upload > 0"
        class="rounded-xl border border-[var(--severity-amber)]/40 bg-[var(--severity-amber)]/5 p-4"
      >
        <div class="flex items-center gap-3">
          <AlertTriangle class="h-5 w-5 text-[var(--severity-amber)]" />
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-foreground">{{ stats.pending_swift_upload }} طلبات بانتظار رفع وثائق السويفت</p>
            <p v-if="oldestPending" class="truncate text-xs text-muted-foreground">
              {{ oldestPending.reference_number }} • منذ {{ hoursInStage(oldestPending.updated_at) }} ساعة
            </p>
          </div>
          <Button class="bg-[var(--severity-amber)] text-white hover:opacity-90" @click="router.push('/requests?tab=pending_swift')">
            ابدأ الرفع
          </Button>
        </div>
      </div>

      <div class="grid grid-cols-4 gap-4 md:grid-cols-2 sm:grid-cols-1">
        <button class="rounded-xl border border-border bg-background p-4 text-start transition hover:shadow-sm" @click="router.push('/requests?tab=pending_swift')">
          <div class="mb-2 inline-flex h-9 w-9 items-center justify-center rounded bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]">
            <Clock3 class="h-5 w-5" />
          </div>
          <p class="text-2xl font-semibold text-[var(--severity-amber)]">{{ stats.pending_swift_upload }}</p>
          <p class="text-xs text-muted-foreground">بانتظار رفع السويفت</p>
        </button>

        <button class="rounded-xl border border-border bg-background p-4 text-start transition hover:shadow-sm" @click="router.push('/requests?tab=swift_done')">
          <div class="mb-2 inline-flex h-9 w-9 items-center justify-center rounded bg-[var(--swift)]/10 text-[var(--swift)]">
            <UploadCloud class="h-5 w-5" />
          </div>
          <p class="text-2xl font-semibold text-[var(--swift)]">{{ stats.uploaded }}</p>
          <p class="text-xs text-muted-foreground">تم رفع السويفت</p>
        </button>

        <button class="rounded-xl border border-border bg-background p-4 text-start transition hover:shadow-sm" @click="router.push('/requests?tab=completed')">
          <div class="mb-2 inline-flex h-9 w-9 items-center justify-center rounded bg-[var(--severity-green)]/10 text-[var(--severity-green)]">
            <CheckCircle2 class="h-5 w-5" />
          </div>
          <p class="text-2xl font-semibold text-[var(--severity-green)]">{{ stats.final_approved }}</p>
          <p class="text-xs text-muted-foreground">مكتمل</p>
        </button>

        <button class="rounded-xl border border-border bg-background p-4 text-start transition hover:shadow-sm" @click="router.push('/requests?tab=rejected')">
          <div class="mb-2 inline-flex h-9 w-9 items-center justify-center rounded bg-[var(--severity-red)]/10 text-[var(--severity-red)]">
            <XCircle class="h-5 w-5" />
          </div>
          <p class="text-2xl font-semibold text-[var(--severity-red)]">{{ stats.final_rejected }}</p>
          <p class="text-xs text-muted-foreground">رُفض من اللجنة</p>
        </button>
      </div>

      <section class="rounded-xl border border-border bg-background">
        <div class="border-b border-border px-4 py-3">
          <h2 class="text-sm font-semibold">طابور السويفت</h2>
        </div>

        <div v-if="queue.length === 0" class="p-8 text-center text-sm text-muted-foreground">
          لا توجد طلبات بانتظار رفع السويفت حالياً ✓
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="bg-muted/40">
                <th class="px-3 py-2 text-right">المرجع</th>
                <th class="px-3 py-2 text-right">التاجر</th>
                <th class="px-3 py-2 text-right">المبلغ</th>
                <th class="px-3 py-2 text-right">الحالة</th>
                <th class="px-3 py-2 text-right">العمر بالمرحلة</th>
                <th class="px-3 py-2 text-right">المستندات</th>
                <th class="px-3 py-2 text-right">إجراء</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="req in queue"
                :key="req.id"
                class="border-t border-border hover:bg-muted/20"
              >
                <td class="px-3 py-2 font-mono text-primary">{{ req.reference_number }}</td>
                <td class="px-3 py-2">{{ req.merchant?.name ?? '—' }}</td>
                <td class="px-3 py-2 font-mono">{{ formatAmount(req.amount, req.currency) }}</td>
                <td class="px-3 py-2">
                  <StatusBadge :status="req.status" :role="UserRole.SWIFT_OFFICER" />
                </td>
                <td class="px-3 py-2" :class="hoursInStage(req.updated_at) > 24 ? 'text-[var(--severity-amber)]' : 'text-muted-foreground'">
                  {{ hoursInStage(req.updated_at) }} ساعة
                </td>
                <td class="px-3 py-2">
                  <div class="flex items-center gap-1.5">
                    <span :class="req.has_swift_document ? 'rounded-full border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 px-2 py-0.5 text-[var(--severity-green)]' : 'rounded-full border border-border bg-muted px-2 py-0.5 text-muted-foreground'">السويفت</span>
                    <span :class="req.has_fx_request_document ? 'rounded-full border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 px-2 py-0.5 text-[var(--severity-green)]' : 'rounded-full border border-border bg-muted px-2 py-0.5 text-muted-foreground'">طلب تأكيد المصارفة</span>
                  </div>
                </td>
                <td class="px-3 py-2">
                  <div class="flex items-center gap-2">
                    <Button size="sm" class="bg-info text-white hover:bg-info/90" @click="router.push(`/requests/${req.id}/swift`)">
                      رفع وثائق السويفت
                    </Button>
                    <Button size="sm" variant="outline" @click="router.push(`/requests/${req.id}`)">
                      تحميل النموذج
                    </Button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
