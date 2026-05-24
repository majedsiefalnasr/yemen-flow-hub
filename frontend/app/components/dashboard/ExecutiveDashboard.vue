// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/executive page level
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole, RequestStatus } from '../../types/enums'
import type { ExecutiveDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'

const router = useRouter()
const store = useDashboardStore()
const auth = useAuthStore()

const stats = computed(() => store.stats as ExecutiveDashboardStats | null)
const isDirector = computed(() => auth.user?.role === UserRole.COMMITTEE_DIRECTOR)
const customsDeclarationPending = computed(() => stats.value?.customs_declaration_pending ?? [])

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function isVotingOpen(status: RequestStatus): boolean {
  return status === RequestStatus.EXECUTIVE_VOTING_OPEN
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-3 md:grid-cols-2 sm:grid-cols-1 gap-4" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 3" :key="n" class="bg-white border border-gray-200 rounded-md p-6 flex flex-col gap-1.5 animate-pulse" aria-hidden="true">
        <div class="h-3.5 w-2/5 bg-gray-50 rounded" />
        <div class="h-8 w-1/3 bg-gray-50 rounded" />
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="store.error" class="border border-gray-200 rounded-md p-5 text-error-text flex items-center gap-3" role="alert">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
      </svg>
      <span>{{ store.error }}</span>
      <button class="ms-auto px-4 py-1.5 bg-white border border-error-text rounded text-error-text text-xs hover:bg-red-700/10 transition-colors" @click="store.loadStats()">إعادة المحاولة</button>
    </div>

    <template v-else-if="stats">

      <!-- KPI grid — 3 cards for EXECUTIVE_MEMBER, 4 for COMMITTEE_DIRECTOR -->
      <div :class="isDirector ? 'grid grid-cols-4 lg:grid-cols-2 md:grid-cols-1 gap-4' : 'grid grid-cols-3 md:grid-cols-2 sm:grid-cols-1 gap-4'">
        <!-- قرارات رفض -->
        <div class="bg-white border border-gray-200 rounded-md p-6 flex flex-col gap-1.5">
          <div class="w-9 h-9 rounded bg-red-700/10 text-error-text flex items-center justify-center mb-1" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line x1="9" y1="9" x2="15" y2="15" />
            </svg>
          </div>
          <span class="text-2xl font-semibold text-error-text">{{ stats.decisions_rejected }}</span>
          <span class="text-xs text-gray-600">قرارات رفض</span>
        </div>

        <!-- قرارات اعتماد -->
        <div class="bg-white border border-gray-200 rounded-md p-6 flex flex-col gap-1.5">
          <div class="w-9 h-9 rounded bg-green-50/10 text-green-700-text flex items-center justify-center mb-1" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
            </svg>
          </div>
          <span class="text-2xl font-semibold text-green-700-text">{{ isDirector ? stats.finalized_decisions : stats.decisions_approved }}</span>
          <span class="text-xs text-gray-600">قرارات اعتماد</span>
        </div>

        <!-- طابور التصويت -->
        <div class="bg-white border rounded-md p-6 flex flex-col gap-1.5" :class="stats.active_voting_sessions > 0 ? 'border-voting-indigo border-l-4' : 'border-gray-200'">
          <div class="w-9 h-9 rounded flex items-center justify-center mb-1 text-voting-indigo bg-indigo-50" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 11l3 3L22 4" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
            </svg>
          </div>
          <span class="text-2xl font-semibold text-voting-indigo">{{ stats.active_voting_sessions }}</span>
          <span class="text-xs text-gray-600">طابور التصويت</span>
        </div>

        <!-- D6: Director override count (amber) — COMMITTEE_DIRECTOR only -->
        <div v-if="isDirector" class="bg-white border border-l-4 border-warning-text rounded-md p-6 flex flex-col gap-1.5">
          <div class="w-9 h-9 rounded bg-yellow-50 text-amber-600-text flex items-center justify-center mb-1" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            </svg>
          </div>
          <span class="text-2xl font-semibold text-amber-600-text">{{ (stats as any).director_override_count ?? 0 }}</span>
          <span class="text-xs text-gray-600">تجاوزات المدير</span>
        </div>
      </div>

      <!-- Quick actions (2 cards) -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="flex items-center gap-2 text-sm font-semibold text-blue-600-text mb-3">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
          </svg>
          إجراءات سريعة
        </h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-1">
          <button class="flex flex-col items-start gap-1 p-5 bg-voting-indigo border border-voting-indigo rounded-md hover:opacity-90 transition-opacity text-white" @click="router.push('/requests')">
            <div class="w-8 h-8 flex items-center justify-center text-white mb-1" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
              </svg>
            </div>
            <span class="text-sm font-semibold">طابور التصويت</span>
            <span class="text-xs opacity-75">{{ stats.active_voting_sessions }} طلب بانتظار التصويت</span>
          </button>

          <button class="flex flex-col items-start gap-1 p-5 bg-white border border-gray-200 rounded-md hover:border-primary-blue hover:text-blue-600-blue transition-colors" @click="router.push('/reports')">
            <div class="w-8 h-8 flex items-center justify-center text-blue-600-blue mb-1" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="20" x2="18" y2="10" /><line x1="12" y1="20" x2="12" y2="4" /><line x1="6" y1="20" x2="6" y2="14" />
              </svg>
            </div>
            <span class="text-sm font-semibold text-blue-600-text">التقارير</span>
            <span class="text-xs text-gray-600">تقارير التصويت والقرارات</span>
          </button>
        </div>
      </section>

      <!-- Voting queue — "طلبات بانتظار تصويتك" -->
      <section aria-labelledby="voting-queue-heading">
        <div class="flex items-center justify-between mb-3">
          <h2 id="voting-queue-heading" class="text-sm font-semibold text-blue-600-text">طلبات بانتظار تصويتك</h2>
          <a class="text-xs text-blue-600-blue hover:underline" href="/requests" @click.prevent="router.push('/requests')">عرض الكل</a>
        </div>

        <div v-if="(stats.voting_queue?.length ?? 0) === 0" class="bg-white border border-gray-200 rounded-md p-10 flex flex-col items-center gap-3 text-gray-600 text-sm" role="status">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          <p>لا توجد طلبات في طابور التصويت حالياً</p>
        </div>

        <table v-else class="w-full border-collapse text-xs bg-white border border-gray-200 rounded-md overflow-hidden" role="table" aria-label="طلبات بانتظار تصويتك">
          <thead>
            <tr class="bg-gray-50">
              <th class="px-3.5 py-2.5 text-right font-medium text-gray-600 border-b border-gray-200 whitespace-nowrap">المرجع</th>
              <th class="px-3.5 py-2.5 text-right font-medium text-gray-600 border-b border-gray-200 whitespace-nowrap">المورد</th>
              <th class="px-3.5 py-2.5 text-right font-medium text-gray-600 border-b border-gray-200 whitespace-nowrap">المبلغ</th>
              <th class="px-3.5 py-2.5 text-right font-medium text-gray-600 border-b border-gray-200 whitespace-nowrap">الحالة</th>
              <th class="px-3.5 py-2.5 text-right font-medium text-gray-600 border-b border-gray-200 whitespace-nowrap">التصويت</th>
              <th class="px-3.5 py-2.5 text-right font-medium text-gray-600 border-b border-gray-200 whitespace-nowrap">إجراء</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="req in (stats.voting_queue ?? [])"
              :key="req.id"
              class="hover:bg-gray-50 border-t border-gray-200"
              :class="{ 'bg-indigo-50': isVotingOpen(req.status) }"
            >
              <td class="px-3.5 py-2.5"><a class="text-blue-600-blue hover:underline font-mono" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
              <td class="px-3.5 py-2.5 text-right">{{ req.supplier_name }}</td>
              <td class="px-3.5 py-2.5 text-left direction-ltr font-variant-numeric-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</td>
              <td class="px-3.5 py-2.5"><StatusBadge :status="req.status" :role="UserRole.EXECUTIVE_MEMBER" /></td>
              <td class="px-3.5 py-2.5">
                <span v-if="isVotingOpen(req.status)" class="inline-flex items-center px-2.5 py-1 bg-voting-indigo text-white rounded-full text-xs font-medium animate-pulse">باب التصويت مفتوح</span>
                <span v-else class="inline-flex items-center px-2.5 py-1 bg-gray-50 text-gray-600 rounded-full text-xs">انتظار فتح التصويت</span>
              </td>
              <td class="px-3.5 py-2.5"><button class="px-3.5 py-1.5 bg-white border border-gray-200 rounded text-xs hover:border-primary-blue hover:text-blue-600-blue transition-colors" :aria-label="`عرض الطلب ${req.reference_number}`" @click="router.push(`/requests/${req.id}`)">عرض</button></td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Director-only: customs declaration queue -->
      <section v-if="isDirector" aria-labelledby="customs-heading">
        <div class="flex items-center justify-between mb-3">
          <h2 id="customs-heading" class="text-sm font-semibold text-blue-600-text">بيانات جمركية بانتظار الإصدار</h2>
        </div>
        <div v-if="customsDeclarationPending.length === 0" class="bg-white border border-gray-200 rounded-md p-10 flex flex-col items-center gap-3 text-gray-600 text-sm" role="status">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
          </svg>
          <p>لا توجد بيانات جمركية بانتظار الإصدار حالياً</p>
        </div>
        <table v-else class="w-full border-collapse text-xs bg-white border border-gray-200 rounded-md overflow-hidden" role="table" aria-label="طلبات بانتظار إصدار البيان الجمركي">
          <thead>
            <tr class="bg-gray-50">
              <th class="px-3.5 py-2.5 text-right font-medium text-gray-600 border-b border-gray-200 whitespace-nowrap">المرجع</th>
              <th class="px-3.5 py-2.5 text-right font-medium text-gray-600 border-b border-gray-200 whitespace-nowrap">البنك</th>
              <th class="px-3.5 py-2.5 text-right font-medium text-gray-600 border-b border-gray-200 whitespace-nowrap">المبلغ</th>
              <th class="px-3.5 py-2.5 text-right font-medium text-gray-600 border-b border-gray-200 whitespace-nowrap">الحالة</th>
              <th class="px-3.5 py-2.5 text-right font-medium text-gray-600 border-b border-gray-200 whitespace-nowrap">إجراء</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="req in customsDeclarationPending" :key="req.id" class="hover:bg-gray-50 border-t border-gray-200">
              <td class="px-3.5 py-2.5"><a class="text-blue-600-blue hover:underline font-mono" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">{{ req.reference_number }}</a></td>
              <td class="px-3.5 py-2.5 text-right">{{ req.bank_name ?? '—' }}</td>
              <td class="px-3.5 py-2.5 text-left direction-ltr font-variant-numeric-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</td>
              <td class="px-3.5 py-2.5"><StatusBadge :status="req.status" :role="UserRole.COMMITTEE_DIRECTOR" /></td>
              <td class="px-3.5 py-2.5"><button class="px-3.5 py-1.5 bg-blue-600-blue text-white rounded text-xs hover:opacity-90 transition-opacity" :aria-label="`إصدار البيان الجمركي للطلب ${req.reference_number}`" @click="router.push(`/requests/${req.id}`)">إصدار</button></td>
            </tr>
          </tbody>
        </table>
      </section>

    </template>
  </div>
</template>
