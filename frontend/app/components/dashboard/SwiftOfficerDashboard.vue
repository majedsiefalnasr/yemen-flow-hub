// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/swift-officer page level
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { UserRole } from '../../types/enums'
import type { SwiftOfficerDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { getRequestProgress } from '../../utils/requestProgress'

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
const queue = computed(() => stats.value?.swift_queue ?? [])

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-4 gap-4 md:grid-cols-2 sm:grid-cols-1" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 4" :key="n" class="bg-background border border-border rounded-md p-5 flex flex-col gap-3 animate-pulse" aria-hidden="true">
        <div class="h-3.5 w-2/5 bg-muted rounded" />
        <div class="h-8 w-1/2 bg-muted rounded" />
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="store.error" class="bg-background border border-border rounded-md p-5 text-destructive flex items-center gap-3" role="alert">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
      </svg>
      <span>{{ store.error }}</span>
      <button class="ml-auto px-4 py-1.5 bg-background border border-destructive rounded text-destructive text-xs hover:bg-destructive/10 transition-colors" @click="store.loadStats()">إعادة المحاولة</button>
    </div>

    <template v-else-if="stats">

      <!-- KPI grid -->
      <div class="grid grid-cols-4 gap-4 md:grid-cols-2 sm:grid-cols-1">
        <!-- مرفوض نهائياً -->
        <div class="bg-background border border-border rounded-md p-6 flex flex-col gap-1.5">
          <div class="w-9 h-9 rounded bg-destructive/10 flex items-center justify-center text-destructive mb-1" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line x1="9" y1="9" x2="15" y2="15" />
            </svg>
          </div>
          <span class="text-2xl font-bold text-destructive leading-none">{{ stats.final_rejected }}</span>
          <span class="text-xs text-muted-foreground">مرفوض نهائياً</span>
        </div>

        <!-- مُعتمد نهائياً -->
        <div class="bg-background border border-border rounded-md p-6 flex flex-col gap-1.5">
          <div class="w-9 h-9 rounded bg-success/10 flex items-center justify-center text-success mb-1" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <span class="text-2xl font-bold text-success leading-none">{{ stats.final_approved }}</span>
          <span class="text-xs text-muted-foreground">مُعتمد نهائياً</span>
        </div>

        <!-- تم رفع السويفت -->
        <div class="bg-background border border-border rounded-md p-6 flex flex-col gap-1.5">
          <div class="w-9 h-9 rounded bg-info/10 flex items-center justify-center text-info mb-1" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="16 16 12 12 8 16" /><line x1="12" y1="12" x2="12" y2="21" />
              <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3" />
            </svg>
          </div>
          <span class="text-2xl font-bold text-info leading-none">{{ stats.uploaded }}</span>
          <span class="text-xs text-muted-foreground">تم رفع السويفت</span>
        </div>

        <!-- بانتظار رفع السويفت -->
        <div :class="{ 'border-l-4 border-l-warning': stats.pending_swift_upload > 0 }" class="bg-background border border-border rounded-md p-6 flex flex-col gap-1.5">
          <div :class="stats.pending_swift_upload > 0 ? 'bg-warning/10 text-warning' : 'bg-muted text-muted-foreground'" class="w-9 h-9 rounded flex items-center justify-center mb-1" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
            </svg>
          </div>
          <span :class="stats.pending_swift_upload > 0 ? 'text-warning' : 'text-foreground'" class="text-2xl font-bold leading-none">{{ stats.pending_swift_upload }}</span>
          <span class="text-xs text-muted-foreground">بانتظار رفع السويفت</span>
        </div>
      </div>

      <!-- Quick action (single card) -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="flex items-center gap-2 text-sm font-bold text-foreground mb-3">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
          </svg>
          إجراءات سريعة
        </h2>
        <div class="w-full sm:max-w-sm">
          <button class="w-full flex flex-col items-start gap-1 p-5 bg-info border border-info rounded-md hover:opacity-90 transition-opacity cursor-pointer" @click="router.push('/requests')">
            <div class="w-8 h-8 flex items-center justify-center text-white mb-1" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="16 16 12 12 8 16" /><line x1="12" y1="12" x2="12" y2="21" />
                <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3" />
              </svg>
            </div>
            <span class="text-sm font-bold text-white">طابور رفع السويفت</span>
            <span class="text-xs text-white/75">{{ stats.pending_swift_upload }} طلب بانتظار الرفع MT103</span>
          </button>
        </div>
      </section>

      <!-- SWIFT queue table -->
      <section aria-labelledby="swift-queue-heading">
        <div class="flex items-center justify-between mb-3">
          <h2 id="swift-queue-heading" class="text-sm font-bold text-foreground">طابور رفع السويفت</h2>
          <a class="text-xs text-primary hover:underline" href="/requests" @click.prevent="router.push('/requests')">عرض الكل</a>
        </div>

        <div v-if="queue.length === 0" class="bg-background border border-border rounded-md p-10 flex flex-col items-center gap-3 text-muted-foreground text-sm text-center" role="status">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--color-locked)" stroke-width="1.5" aria-hidden="true">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          <p class="m-0">لا توجد طلبات بانتظار رفع SWIFT حالياً</p>
        </div>

        <div v-else class="bg-background border border-border rounded-md overflow-hidden">
          <table class="w-full border-collapse text-xs" role="table" aria-label="طابور رفع السويفت">
            <thead>
              <tr class="bg-muted/50">
                <th scope="col" class="text-right py-2.5 px-3.5 font-medium text-muted-foreground border-b border-border whitespace-nowrap">المرجع</th>
                <th scope="col" class="text-right py-2.5 px-3.5 font-medium text-muted-foreground border-b border-border whitespace-nowrap">البنك</th>
                <th scope="col" class="text-right py-2.5 px-3.5 font-medium text-muted-foreground border-b border-border whitespace-nowrap">المبلغ</th>
                <th scope="col" class="text-right py-2.5 px-3.5 font-medium text-muted-foreground border-b border-border whitespace-nowrap">الحالة</th>
                <th scope="col" class="text-right py-2.5 px-3.5 font-medium text-muted-foreground border-b border-border whitespace-nowrap">التقدم</th>
                <th scope="col" class="text-right py-2.5 px-3.5 font-medium text-muted-foreground border-b border-border whitespace-nowrap">إجراء</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="req in queue" :key="req.id" class="hover:bg-muted/50 border-t border-border">
                <td class="text-right py-2.5 px-3.5 text-foreground"><a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}/swift`" @click.prevent="router.push(`/requests/${req.id}/swift`)">{{ req.reference_number }}</a></td>
                <td class="text-right py-2.5 px-3.5 text-foreground">{{ req.bank_name ?? '—' }}</td>
                <td class="text-right py-2.5 px-3.5 text-foreground ltr tabular-nums">{{ formatAmount(req.amount, req.currency) }}</td>
                <td class="text-right py-2.5 px-3.5"><StatusBadge :status="req.status" :role="UserRole.SWIFT_OFFICER" /></td>
                <td class="text-right py-2.5 px-3.5">
                  <div class="flex items-center gap-1.5 min-w-24">
                    <div class="flex-1 h-1.5 bg-muted rounded-full overflow-hidden">
                      <div class="h-full rounded-full" :style="{ width: `${getRequestProgress(req.status)}%`, backgroundColor: 'var(--color-info)' }" />
                    </div>
                    <span class="text-xs text-muted-foreground whitespace-nowrap">{{ getRequestProgress(req.status) }}%</span>
                  </div>
                </td>
                <td class="text-right py-2.5 px-3.5"><button class="px-3.5 py-1.5 bg-info text-white rounded text-xs hover:opacity-90 transition-opacity" :aria-label="`رفع SWIFT للطلب ${req.reference_number}`" @click="router.push(`/requests/${req.id}/swift`)">رفع SWIFT</button></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

    </template>
  </div>
</template>
