<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { UserRole } from '../../types/enums'
import { RequestStatus } from '../../types/enums'
import type { ImportRequest } from '../../types/models'
import { useRequests } from '../../composables/useRequests'

definePageMeta({
  middleware: 'role',
  requiredRoles: [UserRole.COMMITTEE_DIRECTOR],
})

const router = useRouter()
const { fetchRequests, generateCustomsDeclaration, downloadCustomsDeclaration } = useRequests()

const queue = ref<ImportRequest[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const issuingId = ref<number | null>(null)
const issueError = ref<string | null>(null)

const downloadingId = ref<number | null>(null)
const downloadError = ref<string | null>(null)

async function loadQueue() {
  loading.value = true
  error.value = null
  try {
    const result = await fetchRequests({ status: RequestStatus.EXECUTIVE_APPROVED, per_page: 100 })
    queue.value = result.data
  }
  catch {
    error.value = 'تعذّر تحميل قائمة انتظار البيانات الجمركية.'
  }
  finally {
    loading.value = false
  }
}

async function handleIssue(request: ImportRequest) {
  issuingId.value = request.id
  issueError.value = null
  try {
    await generateCustomsDeclaration(request.id)
    await loadQueue()
  }
  catch (err: unknown) {
    const e = err as { data?: { message?: string } }
    issueError.value = e.data?.message ?? 'تعذّر إصدار البيان الجمركي. حاول مرة أخرى.'
  }
  finally {
    issuingId.value = null
  }
}

async function handleDownload(request: ImportRequest) {
  if (!request.customs_declaration?.id) return
  downloadingId.value = request.id
  downloadError.value = null
  try {
    const blob = await downloadCustomsDeclaration(request.customs_declaration.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `customs-${request.reference_number}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  }
  catch {
    downloadError.value = 'تعذّر تحميل البيان الجمركي.'
  }
  finally {
    downloadingId.value = null
  }
}

function viewRequest(request: ImportRequest) {
  router.push(`/requests/${request.id}`)
}

function formatAmount(amount: number, currency: string): string {
  return `${amount.toLocaleString('ar')} ${currency}`
}

onMounted(loadQueue)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1 class="page-title">البيانات الجمركية</h1>
        <p class="page-subtitle">الطلبات المعتمدة تنفيذياً وتنتظر إصدار البيان الجمركي</p>
      </div>
    </div>

    <div v-if="issueError" class="alert-error">
      {{ issueError }}
      <button class="alert-dismiss" @click="issueError = null">✕</button>
    </div>

    <div v-if="downloadError" class="alert-error">
      {{ downloadError }}
      <button class="alert-dismiss" @click="downloadError = null">✕</button>
    </div>

    <div v-if="loading" class="state-card">
      <div class="spinner" />
      <span>جارٍ التحميل…</span>
    </div>

    <div v-else-if="error" class="state-card state-error">
      {{ error }}
      <button class="btn-retry" @click="loadQueue">إعادة المحاولة</button>
    </div>

    <div v-else-if="queue.length === 0" class="state-card">
      <div class="empty-icon">📋</div>
      <p class="empty-text">لا توجد طلبات تنتظر إصدار البيان الجمركي.</p>
      <p class="empty-sub">ستظهر هنا الطلبات المعتمدة تنفيذياً عند توفرها.</p>
    </div>

    <div v-else class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>رقم المرجع</th>
            <th>اسم المورد</th>
            <th>الجهة المصرفية</th>
            <th>المبلغ</th>
            <th>تاريخ الاعتماد</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="request in queue" :key="request.id">
            <td>
              <button class="link-btn" @click="viewRequest(request)">
                {{ request.reference_number }}
              </button>
            </td>
            <td>{{ request.supplier_name }}</td>
            <td>{{ request.bank_name ?? '—' }}</td>
            <td class="amount-cell">{{ formatAmount(request.amount, request.currency) }}</td>
            <td class="date-cell">{{ request.bank_approved_at ? new Date(request.bank_approved_at).toLocaleDateString('ar') : '—' }}</td>
            <td class="actions-cell">
              <button
                class="btn-issue"
                :disabled="issuingId === request.id"
                @click="handleIssue(request)"
              >
                {{ issuingId === request.id ? 'جارٍ الإصدار…' : 'إصدار بيان جمركي' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.page-title {
  font-size: 28px;
  font-weight: 500;
  color: var(--color-text-primary);
  margin: 0 0 4px;
}

.page-subtitle {
  font-size: 14px;
  color: var(--color-text-secondary);
  margin: 0;
}

.alert-error {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff0ef;
  border: 1px solid #c62828;
  border-radius: 10px;
  padding: 12px 16px;
  font-size: 14px;
  color: #c62828;
}

.alert-dismiss {
  background: none;
  border: none;
  color: #c62828;
  cursor: pointer;
  font-size: 16px;
  padding: 0 4px;
}

.state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  padding: 48px 24px;
  color: var(--color-text-secondary);
  text-align: center;
}

.state-error {
  color: #c62828;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--color-border);
  border-top-color: #0066cc;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-icon {
  font-size: 40px;
}

.empty-text {
  font-size: 16px;
  font-weight: 500;
  color: var(--color-text-primary);
  margin: 0;
}

.empty-sub {
  font-size: 13px;
  color: var(--color-text-secondary);
  margin: 0;
}

.btn-retry {
  padding: 8px 20px;
  background: transparent;
  color: #0066cc;
  border: 1px solid #0066cc;
  border-radius: 8px;
  font-size: 13px;
  cursor: pointer;
}

.card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  direction: rtl;
}

.data-table th,
.data-table td {
  padding: 14px 16px;
  text-align: right;
  font-size: 14px;
}

.data-table th {
  background: #f5f5f7;
  color: var(--color-text-secondary);
  font-weight: 500;
  border-bottom: 1px solid var(--color-border);
}

.data-table td {
  border-bottom: 1px solid var(--color-border);
  color: var(--color-text-primary);
}

.data-table tr:last-child td {
  border-bottom: none;
}

.link-btn {
  background: none;
  border: none;
  color: #0066cc;
  font-size: 14px;
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
  font-family: inherit;
}

.amount-cell {
  direction: ltr;
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.date-cell {
  font-size: 13px;
  color: var(--color-text-secondary);
}

.actions-cell {
  white-space: nowrap;
}

.btn-issue {
  padding: 8px 16px;
  background: #0066cc;
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: opacity 120ms;
}

.btn-issue:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
