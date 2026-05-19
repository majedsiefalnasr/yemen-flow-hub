<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useSettings } from '../composables/useSettings'
import { useAuthStore } from '../stores/auth.store'
import { UserRole } from '../types/enums'

definePageMeta({
  middleware: 'auth',
})

const { preferences, loading, error, fetchSettings, updateSettings, resetSettings } = useSettings()
const auth = useAuthStore()

const isDemoMode = computed(() => {
  const config = useRuntimeConfig()
  return (config.public as Record<string, unknown>).demoMode === true
    || (config.public as Record<string, unknown>).demoMode === 'true'
})

// Tab navigation
type TabId = 'workflow' | 'email' | 'notifications' | 'security' | 'general' | 'demo'

const activeTab = ref<TabId>('general')

const tabs: Array<{ id: TabId, label: string }> = [
  { id: 'workflow', label: 'سير العمل' },
  { id: 'email', label: 'البريد الإلكتروني' },
  { id: 'notifications', label: 'الإشعارات' },
  { id: 'security', label: 'الأمن' },
  { id: 'general', label: 'عام' },
  { id: 'demo', label: 'بيانات العرض التوضيحي' },
]

const visibleTabs = computed(() =>
  tabs.filter(t => t.id !== 'demo' || isDemoMode.value),
)

// General tab form data
const formData = ref({
  language: 'ar',
  dashboard_view: 'normal',
  table_density: 'normal',
  page_size: 25,
})

// Notification preferences
interface NotifPrefItem {
  key: string
  label: string
  mandatory: boolean
  roles: UserRole[]
}

const ALL_NOTIF_PREFS: NotifPrefItem[] = [
  {
    key: 'request_submitted',
    label: 'إشعار تقديم الطلبات الجديدة',
    mandatory: false,
    roles: [UserRole.BANK_REVIEWER],
  },
  {
    key: 'request_approved',
    label: 'إشعار الموافقة على الطلبات',
    mandatory: false,
    roles: [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER],
  },
  {
    key: 'request_rejected',
    label: 'إشعار رفض الطلبات',
    mandatory: true,
    roles: [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER],
  },
  {
    key: 'request_returned',
    label: 'إشعار إعادة الطلبات للمراجعة',
    mandatory: true,
    roles: [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER],
  },
  {
    key: 'swift_upload_requested',
    label: 'إشعار طلب رفع SWIFT',
    mandatory: false,
    roles: [UserRole.SWIFT_OFFICER],
  },
  {
    key: 'voting_opened',
    label: 'إشعار فتح جلسة التصويت',
    mandatory: false,
    roles: [UserRole.EXECUTIVE_MEMBER, UserRole.COMMITTEE_DIRECTOR],
  },
  {
    key: 'customs_issued',
    label: 'إشعار إصدار البيان الجمركي',
    mandatory: false,
    roles: [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER],
  },
]

const visibleNotifPrefs = computed(() => {
  const role = auth.user?.role as UserRole | undefined
  if (!role) return []
  return ALL_NOTIF_PREFS.filter(p => p.roles.includes(role))
})

const notifPrefs = ref<Record<string, boolean>>({})

function isNotifEnabled(key: string): boolean {
  return notifPrefs.value[key] !== false
}

async function toggleNotifPref(key: string, enabled: boolean) {
  notifPrefs.value = { ...notifPrefs.value, [key]: enabled }
  const current = preferences.value?.notification_preferences ?? {}
  await updateSettings({ notification_preferences: { ...current, [key]: enabled } })
}

const handleSave = async () => {
  await updateSettings({
    language: formData.value.language,
    dashboard_view: formData.value.dashboard_view,
    table_density: formData.value.table_density,
    page_size: formData.value.page_size,
  })
}

const handleReset = async () => {
  if (!confirm('هل أنت متأكد من رغبتك في إعادة تعيين الإعدادات للافتراضيات؟')) return

  const success = await resetSettings()
  if (success && preferences.value) {
    formData.value = {
      language: preferences.value.language,
      dashboard_view: preferences.value.dashboard_view,
      table_density: preferences.value.table_density,
      page_size: preferences.value.page_size,
    }
  }
}

onMounted(() => {
  fetchSettings()

  watch(
    () => preferences.value,
    (newPrefs) => {
      if (newPrefs) {
        formData.value = {
          language: newPrefs.language,
          dashboard_view: newPrefs.dashboard_view,
          table_density: newPrefs.table_density,
          page_size: newPrefs.page_size,
        }
        notifPrefs.value = { ...(newPrefs.notification_preferences ?? {}) }
      }
    },
  )
})
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">الإعدادات</h1>
    </div>

    <!-- Tab Nav -->
    <nav class="tab-nav" role="tablist">
      <button
        v-for="tab in visibleTabs"
        :key="tab.id"
        :data-tab="tab.id"
        :class="['tab-btn', { active: activeTab === tab.id }]"
        role="tab"
        :aria-selected="activeTab === tab.id"
        @click="activeTab = tab.id"
      >
        {{ tab.label }}
      </button>
    </nav>

    <!-- Loading -->
    <div v-if="loading" class="state-message">جارٍ التحميل…</div>

    <!-- Tab Panels -->
    <div v-else class="tab-content">

      <!-- سير العمل -->
      <div v-show="activeTab === 'workflow'" class="panel" data-panel="workflow">
        <div class="section-card">
          <h2 class="section-title">إعدادات سير العمل</h2>
          <p class="section-desc">يمكن تعديل هذه الإعدادات من صفحة إعدادات النظام المخصصة للمسؤول.</p>
          <div class="info-grid">
            <div class="info-row">
              <span class="info-label">مدة صلاحية مطالبة الدعم</span>
              <span class="info-value">15 دقيقة</span>
            </div>
            <div class="info-row">
              <span class="info-label">فترة نبضة القلب</span>
              <span class="info-value">60 ثانية</span>
            </div>
            <div class="info-row">
              <span class="info-label">حد التصويت التلقائي</span>
              <span class="info-value">أغلبية بسيطة</span>
            </div>
          </div>
        </div>
      </div>

      <!-- البريد الإلكتروني -->
      <div v-show="activeTab === 'email'" class="panel" data-panel="email">
        <div class="section-card">
          <h2 class="section-title">إعدادات البريد الإلكتروني</h2>
          <p class="section-desc">يتم إرسال الإشعارات الإلكترونية تلقائياً بناءً على تفضيلات الإشعارات.</p>
          <div class="info-row">
            <span class="info-label">بريدك الإلكتروني المسجّل</span>
            <span class="info-value">{{ auth.user?.email ?? '—' }}</span>
          </div>
        </div>
      </div>

      <!-- الإشعارات -->
      <div v-show="activeTab === 'notifications'" class="panel" data-panel="notifications">
        <div class="section-card">
          <h2 class="section-title">تفضيلات الإشعارات</h2>
          <p v-if="visibleNotifPrefs.length === 0" class="section-desc">
            لا توجد تفضيلات إشعارات متاحة لهذا الدور.
          </p>
          <div v-else class="notif-list">
            <div
              v-for="pref in visibleNotifPrefs"
              :key="pref.key"
              class="notif-row"
            >
              <div class="notif-info">
                <span class="notif-label">{{ pref.label }}</span>
                <span
                  v-if="pref.mandatory"
                  class="notif-lock-badge"
                  data-testid="lock-badge"
                  :aria-label="pref.label + ' — مطلوب دائماً'"
                >مطلوب دائماً</span>
              </div>
              <input
                v-if="!pref.mandatory"
                type="checkbox"
                :checked="isNotifEnabled(pref.key)"
                :aria-label="pref.label"
                class="toggle-check"
                @change="(e) => toggleNotifPref(pref.key, (e.target as HTMLInputElement).checked)"
              />
              <input
                v-else
                type="checkbox"
                checked
                disabled
                class="toggle-check"
                :aria-label="pref.label + ' — مطلوب دائماً'"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- الأمن -->
      <div v-show="activeTab === 'security'" class="panel" data-panel="security">
        <div class="section-card">
          <h2 class="section-title">إعدادات الأمن</h2>

          <div class="security-grid">
            <div class="security-row">
              <div class="security-info">
                <div class="security-label">حد محاولات الدخول</div>
                <div class="security-desc">عدد المحاولات الفاشلة قبل قفل الحساب</div>
              </div>
              <span class="security-value" data-testid="lockout-threshold">10 محاولات</span>
            </div>

            <div class="security-row">
              <div class="security-info">
                <div class="security-label">مدة قفل الحساب</div>
                <div class="security-desc">المدة التي يظل فيها الحساب مقفلاً بعد تجاوز الحد</div>
              </div>
              <span class="security-value" data-testid="lockout-duration">15 دقيقة</span>
            </div>

            <div class="security-row">
              <div class="security-info">
                <div class="security-label">مهلة الجلسة</div>
                <div class="security-desc">المدة قبل انتهاء صلاحية الجلسة تلقائياً عند عدم النشاط</div>
              </div>
              <span class="security-value">8 ساعات</span>
            </div>

            <div class="security-row">
              <div class="security-info">
                <div class="security-label">التحقق الثنائي (MFA)</div>
                <div class="security-desc">إلزام جميع المستخدمين بإدخال رمز OTP عند تسجيل الدخول</div>
              </div>
              <div class="toggle-wrapper">
                <input
                  type="checkbox"
                  id="mfa-toggle"
                  class="toggle-check"
                  checked
                  disabled
                  aria-label="إلزام التحقق الثنائي"
                  data-testid="mfa-toggle"
                />
                <label for="mfa-toggle" class="toggle-label">مُفعَّل</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- عام -->
      <div v-show="activeTab === 'general'" class="panel" data-panel="general">
        <div v-if="preferences" class="section-card">
          <h2 class="section-title">الإعدادات العامة</h2>
          <form @submit.prevent="handleSave" class="general-form">
            <div class="form-field">
              <label class="form-label">اللغة</label>
              <select v-model="formData.language" class="form-input">
                <option value="ar">العربية</option>
                <option value="en">English</option>
              </select>
            </div>

            <div class="form-field">
              <label class="form-label">عرض لوحة التحكم</label>
              <div class="radio-group">
                <label class="radio-item">
                  <input v-model="formData.dashboard_view" type="radio" value="compact" /> مضغوط
                </label>
                <label class="radio-item">
                  <input v-model="formData.dashboard_view" type="radio" value="normal" /> عادي
                </label>
                <label class="radio-item">
                  <input v-model="formData.dashboard_view" type="radio" value="expanded" /> موسع
                </label>
              </div>
            </div>

            <div class="form-field">
              <label class="form-label">كثافة الجدول</label>
              <div class="radio-group">
                <label class="radio-item">
                  <input v-model="formData.table_density" type="radio" value="compact" /> مضغوط
                </label>
                <label class="radio-item">
                  <input v-model="formData.table_density" type="radio" value="normal" /> عادي
                </label>
                <label class="radio-item">
                  <input v-model="formData.table_density" type="radio" value="comfortable" /> مريح
                </label>
              </div>
            </div>

            <div class="form-field">
              <label class="form-label">حجم الصفحة</label>
              <select v-model.number="formData.page_size" class="form-input">
                <option value="10">10 صفوف</option>
                <option value="25">25 صف</option>
                <option value="50">50 صف</option>
                <option value="100">100 صف</option>
              </select>
            </div>

            <div v-if="error" class="error-banner">{{ error }}</div>

            <div class="form-actions">
              <button type="submit" :disabled="loading" class="btn-primary">
                {{ loading ? 'جاري الحفظ...' : 'حفظ التغييرات' }}
              </button>
              <button type="button" :disabled="loading" class="btn-secondary" @click="handleReset">
                إعادة تعيين
              </button>
            </div>
          </form>
        </div>
        <div v-else class="state-message state-error">{{ error }}</div>
      </div>

      <!-- بيانات العرض التوضيحي -->
      <div v-show="activeTab === 'demo'" class="panel" data-panel="demo">
        <div class="section-card">
          <h2 class="section-title">بيانات العرض التوضيحي</h2>
          <p class="section-desc">هذه الإعدادات متاحة في وضع العرض التوضيحي فقط وتُخفى في بيئة الإنتاج.</p>
          <div class="info-row">
            <span class="info-label">وضع العرض التوضيحي</span>
            <span class="badge badge-active">مُفعَّل</span>
          </div>
          <div class="info-row">
            <span class="info-label">محدد الشخصية (RoleSwitcher)</span>
            <span class="info-value">ظاهر في صفحة تسجيل الدخول</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 800px;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-title {
  font-size: 28px;
  font-weight: 500;
  color: var(--color-text-primary);
  margin: 0;
}

/* Tab nav */
.tab-nav {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--color-border);
  padding-bottom: 0;
  flex-wrap: wrap;
}

.tab-btn {
  padding: 10px 18px;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-secondary);
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s;
  margin-bottom: -1px;
}

.tab-btn.active {
  color: #0066cc;
  border-bottom-color: #0066cc;
}

.tab-btn:hover:not(.active) {
  color: var(--color-text-primary);
}

.tab-content {
  min-height: 200px;
}

/* Section card */
.section-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section-title {
  font-size: 18px;
  font-weight: 500;
  color: var(--color-text-primary);
  margin: 0;
}

.section-desc {
  font-size: 14px;
  color: var(--color-text-secondary);
  margin: 0;
  line-height: 1.6;
}

/* Info rows (workflow / email) */
.info-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: #f5f5f7;
  border-radius: 10px;
}

.info-label {
  font-size: 14px;
  color: var(--color-text-secondary);
}

.info-value {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-primary);
}

/* Notifications */
.notif-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.notif-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
}

.notif-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.notif-label {
  font-size: 14px;
  color: var(--color-text-primary);
}

.notif-lock-badge {
  font-size: 11px;
  color: #8e8e93;
  background: #f5f5f7;
  border: 1px solid #cccccc;
  border-radius: 4px;
  padding: 1px 6px;
}

.toggle-check {
  width: 18px;
  height: 18px;
  cursor: pointer;
  flex-shrink: 0;
}

/* Security */
.security-grid {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.security-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
}

.security-info {
  flex: 1;
}

.security-label {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-primary);
}

.security-desc {
  font-size: 12px;
  color: var(--color-text-secondary);
  margin-top: 2px;
}

.security-value {
  font-size: 14px;
  font-weight: 600;
  color: #0066cc;
  white-space: nowrap;
  margin-right: 16px;
}

.toggle-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-right: 16px;
}

.toggle-label {
  font-size: 13px;
  color: var(--color-text-secondary);
}

/* General form */
.general-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-label {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-secondary);
}

.form-input {
  height: 44px;
  padding: 0 12px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  font-size: 14px;
  color: var(--color-text-primary);
  background: var(--color-surface);
  outline: none;
  max-width: 280px;
}

.form-input:focus {
  border-color: #0066cc;
}

.radio-group {
  display: flex;
  gap: 20px;
}

.radio-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: var(--color-text-primary);
  cursor: pointer;
}

.form-actions {
  display: flex;
  gap: 12px;
}

.error-banner {
  background: #fff0ef;
  border: 1px solid #c62828;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #c62828;
}

/* Shared buttons */
.btn-primary {
  height: 44px;
  padding: 0 20px;
  background: #0066cc;
  color: #fff;
  border: none;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  height: 44px;
  padding: 0 20px;
  background: transparent;
  color: var(--color-text-primary);
  border: 1px solid var(--color-border);
  border-radius: 16px;
  font-size: 14px;
  cursor: pointer;
}

.btn-secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.state-message {
  text-align: center;
  color: var(--color-text-secondary);
  padding: 32px;
}

.state-error {
  color: #c62828;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.badge-active {
  background: #e6f9ec;
  color: #1a7a35;
}
</style>
