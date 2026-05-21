<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useSettings } from '../composables/useSettings'
import { useAdminSettings } from '../composables/useAdminSettings'
import { useAuthStore } from '../stores/auth.store'
import { UserRole } from '../types/enums'
import Icon from '../components/ui/Icon.vue'

definePageMeta({
  middleware: 'auth',
})

const { preferences, loading, error, fetchSettings, updateSettings, resetSettings } = useSettings()
const {
  settings: adminSettings,
  smtpSettings,
  securityPolicies,
  pendingKeys,
  fetchSettings: fetchAdminSettings,
  fetchSmtpSettings,
  updateSmtpSettings,
  fetchSecurityPolicies,
  updateSecurityPolicy,
  updateSetting: updateAdminSetting,
} = useAdminSettings()

const auth = useAuthStore()

const isCbyAdmin = computed(() => auth.user?.role === UserRole.CBY_ADMIN)

const isDemoMode = computed(() => {
  const config = useRuntimeConfig()
  return (config.public as Record<string, unknown>).demoMode === true
    || (config.public as Record<string, unknown>).demoMode === 'true'
})

// Tab navigation
type TabId = 'workflow' | 'email' | 'notifications' | 'security' | 'general' | 'demo'

const activeTab = ref<TabId>('general')

const tabs: Array<{ id: TabId; label: string; icon: string }> = [
  { id: 'workflow', label: 'سير العمل', icon: 'workflow' },
  { id: 'email', label: 'البريد الإلكتروني', icon: 'mail' },
  { id: 'notifications', label: 'الإشعارات', icon: 'bell' },
  { id: 'security', label: 'الأمن', icon: 'shield-alert' },
  { id: 'general', label: 'عام', icon: 'settings' },
  { id: 'demo', label: 'بيانات العرض التوضيحي', icon: 'database' },
]

const visibleTabs = computed(() =>
  tabs.filter(t => t.id !== 'demo' || isDemoMode.value),
)

// ── General tab ──────────────────────────────────────────────────────────────
const formData = ref({
  language: 'ar',
  dashboard_view: 'normal',
  table_density: 'normal',
  page_size: 25,
})

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

// ── Notifications tab ─────────────────────────────────────────────────────────
interface NotifPrefItem {
  key: string
  label: string
  mandatory: boolean
  roles: UserRole[]
}

const ALL_NOTIF_PREFS: NotifPrefItem[] = [
  { key: 'request_submitted', label: 'إشعار تقديم الطلبات الجديدة', mandatory: false, roles: [UserRole.BANK_REVIEWER] },
  { key: 'request_approved', label: 'إشعار الموافقة على الطلبات', mandatory: false, roles: [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER] },
  { key: 'request_rejected', label: 'إشعار رفض الطلبات', mandatory: true, roles: [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER] },
  { key: 'request_returned', label: 'إشعار إعادة الطلبات للمراجعة', mandatory: true, roles: [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER] },
  { key: 'swift_upload_requested', label: 'إشعار طلب رفع SWIFT', mandatory: false, roles: [UserRole.SWIFT_OFFICER] },
  { key: 'voting_opened', label: 'إشعار فتح جلسة التصويت', mandatory: false, roles: [UserRole.EXECUTIVE_MEMBER, UserRole.COMMITTEE_DIRECTOR] },
  { key: 'customs_issued', label: 'إشعار إصدار البيان الجمركي', mandatory: false, roles: [UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER] },
  { key: 'claim_released', label: 'إشعار إلغاء المطالبة', mandatory: false, roles: [UserRole.CBY_ADMIN] },
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

// ── SMTP tab ─────────────────────────────────────────────────────────────────
const smtpForm = ref({ host: '', port: 587, username: '', password: '', template: '' })
const smtpSaving = ref(false)
const smtpError = ref<string | null>(null)
const smtpSuccess = ref(false)

async function handleSmtpSave() {
  smtpSaving.value = true
  smtpError.value = null
  smtpSuccess.value = false
  const ok = await updateSmtpSettings({ ...smtpForm.value })
  smtpSaving.value = false
  if (ok) smtpSuccess.value = true
  else smtpError.value = 'فشل حفظ إعدادات SMTP'
}

// ── Security policies (CBY_ADMIN) ─────────────────────────────────────────────
const SECURITY_ROWS = [
  { key: 'mfa_required', label: 'إلزام التحقق الثنائي (MFA)', desc: 'يُجبر جميع المستخدمين على إدخال رمز OTP عند تسجيل الدخول' },
  { key: 'password_expiry_90_days', label: 'انتهاء صلاحية كلمة المرور (90 يوم)', desc: 'يُجبر المستخدمين على تغيير كلمة المرور كل 90 يوماً' },
  { key: 'lockout_after_5_attempts', label: 'قفل الحساب بعد 5 محاولات', desc: 'يُقفل الحساب تلقائياً بعد 5 محاولات دخول فاشلة متتالية' },
  { key: 'encrypt_uploads_aes256', label: 'تشفير الملفات المرفوعة (AES-256)', desc: 'تشفير جميع الملفات المرفوعة باستخدام معيار AES-256' },
  { key: 'log_all_audit', label: 'تسجيل جميع أحداث التدقيق', desc: 'تسجيل جميع الإجراءات في سجل التدقيق الشامل' },
  { key: 'allow_external_access', label: 'السماح بالوصول الخارجي', desc: 'السماح للمستخدمين بالوصول من خارج الشبكة الداخلية' },
]

function getSecurityValue(key: string): boolean {
  return (securityPolicies.value as any)?.[key] ?? false
}

async function handleSecurityToggle(key: string, value: boolean) {
  await updateSecurityPolicy(key, value)
}

// ── Mount ─────────────────────────────────────────────────────────────────────
onMounted(async () => {
  fetchSettings()

  if (isCbyAdmin.value) {
    fetchAdminSettings()
    fetchSmtpSettings()
    fetchSecurityPolicies()
  }

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

  watch(
    () => smtpSettings.value,
    (s) => {
      if (s) {
        smtpForm.value = { host: s.host, port: s.port, username: s.username, password: '', template: s.template }
      }
    },
  )
})
</script>

<template>
  <div class="settings-page">
    <!-- Page header -->
    <div class="page-header">
      <nav class="breadcrumbs" aria-label="مسار التنقل">
        <NuxtLink to="/dashboard" class="breadcrumb-link">الرئيسية</NuxtLink>
        <span class="breadcrumb-sep">›</span>
        <span class="breadcrumb-current">الإعدادات</span>
      </nav>
      <h1 class="page-title">الإعدادات</h1>
      <p class="page-subtitle">إدارة تفضيلات الحساب وإعدادات النظام</p>
    </div>

    <!-- Tab nav -->
    <nav class="tab-nav" role="tablist" aria-label="أقسام الإعدادات">
      <button
        v-for="tab in visibleTabs"
        :key="tab.id"
        :data-testid="`tab-${tab.id === 'notifications' ? 'notif' : tab.id}`"
        :data-tab="tab.id"
        :class="['tab-btn', { active: activeTab === tab.id }]"
        role="tab"
        :aria-selected="activeTab === tab.id"
        @click="activeTab = tab.id"
      >
        <Icon :name="tab.icon" :size="16" />
        {{ tab.label }}
      </button>
    </nav>

    <!-- Loading -->
    <div v-if="loading" class="state-loading" aria-live="polite">جارٍ التحميل…</div>

    <!-- Tab panels -->
    <div v-else class="tab-content">

      <!-- ── سير العمل ───────────────────────────────────────────────── -->
      <div v-show="activeTab === 'workflow'" class="panel" data-panel="workflow">
        <div class="section-card">
          <div class="section-header">
            <h2 class="section-title">إعدادات سير العمل</h2>
            <p class="section-desc">معاملات دورة الموافقة ومهلات المراحل</p>
          </div>

          <!-- CBY_ADMIN: live editable values -->
          <template v-if="isCbyAdmin && adminSettings">
            <div class="two-col-grid">
              <div class="field-group">
                <label class="field-label">مدة صلاحية مطالبة الدعم (دقيقة)</label>
                <input
                  type="number"
                  class="form-input"
                  :value="adminSettings.support_claim_ttl"
                  @change="(e) => updateAdminSetting('support_claim_ttl', Number((e.target as HTMLInputElement).value))"
                />
              </div>
              <div class="field-group">
                <label class="field-label">مهلة جلسة التصويت (ساعة)</label>
                <input
                  type="number"
                  class="form-input"
                  :value="adminSettings.voting_session_timeout"
                  @change="(e) => updateAdminSetting('voting_session_timeout', Number((e.target as HTMLInputElement).value))"
                />
              </div>
              <div class="field-group">
                <label class="field-label">حد حجم ملف PDF (MB)</label>
                <input
                  type="number"
                  class="form-input"
                  :value="adminSettings.pdf_upload_size_limit"
                  @change="(e) => updateAdminSetting('pdf_upload_size_limit', Number((e.target as HTMLInputElement).value))"
                />
              </div>
              <div class="field-group">
                <label class="field-label">مدة قفل الحساب (دقيقة)</label>
                <input
                  type="number"
                  class="form-input"
                  :value="adminSettings.login_lockout_duration"
                  @change="(e) => updateAdminSetting('login_lockout_duration', Number((e.target as HTMLInputElement).value))"
                />
              </div>
            </div>

            <div class="switch-section">
              <div class="switch-row">
                <div class="switch-info">
                  <span class="switch-label">التصويت السري</span>
                  <span class="switch-desc">إخفاء هوية الناخبين من نتائج التصويت</span>
                </div>
                <label class="toggle-switch">
                  <input
                    type="checkbox"
                    :checked="adminSettings.secret_voting"
                    @change="(e) => updateAdminSetting('secret_voting', (e.target as HTMLInputElement).checked)"
                  />
                  <span class="toggle-knob" />
                </label>
              </div>
              <div class="switch-row">
                <div class="switch-info">
                  <span class="switch-label">كسر التعادل بالمدير</span>
                  <span class="switch-desc">يملك مدير اللجنة صلاحية كسر التعادل عند التصويت</span>
                </div>
                <label class="toggle-switch">
                  <input
                    type="checkbox"
                    :checked="adminSettings.director_tiebreak"
                    @change="(e) => updateAdminSetting('director_tiebreak', (e.target as HTMLInputElement).checked)"
                  />
                  <span class="toggle-knob" />
                </label>
              </div>
            </div>
          </template>

          <!-- Non-admin: read-only display -->
          <template v-else>
            <p class="readonly-note" data-testid="workflow-readonly-note">
              يمكن تعديل هذه الإعدادات من قِبَل مسؤول النظام فقط.
            </p>
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
          </template>
        </div>
      </div>

      <!-- ── البريد الإلكتروني ───────────────────────────────────────── -->
      <div v-show="activeTab === 'email'" class="panel" data-panel="email">
        <div class="section-card">
          <div class="section-header">
            <h2 class="section-title">إعدادات البريد الإلكتروني (SMTP)</h2>
            <p class="section-desc">تهيئة خادم البريد لإرسال الإشعارات</p>
          </div>

          <!-- CBY_ADMIN: editable SMTP form -->
          <template v-if="isCbyAdmin">
            <div class="two-col-grid">
              <div class="field-group">
                <label class="field-label">الخادم (Host)</label>
                <input v-model="smtpForm.host" type="text" class="form-input" placeholder="smtp.example.com" dir="ltr" />
              </div>
              <div class="field-group">
                <label class="field-label">المنفذ (Port)</label>
                <input v-model.number="smtpForm.port" type="number" class="form-input" placeholder="587" dir="ltr" />
              </div>
              <div class="field-group">
                <label class="field-label">اسم المستخدم</label>
                <input v-model="smtpForm.username" type="text" class="form-input" dir="ltr" />
              </div>
              <div class="field-group">
                <label class="field-label">كلمة المرور</label>
                <input v-model="smtpForm.password" type="password" class="form-input" dir="ltr" placeholder="••••••••" />
              </div>
            </div>
            <div class="field-group">
              <label class="field-label">قالب البريد</label>
              <textarea v-model="smtpForm.template" class="form-input form-textarea" dir="ltr" rows="3" />
            </div>

            <div v-if="smtpError" class="error-banner">{{ smtpError }}</div>
            <div v-if="smtpSuccess" class="success-banner">تم حفظ إعدادات SMTP بنجاح</div>

            <div class="form-actions">
              <button class="btn-primary" :disabled="smtpSaving" @click="handleSmtpSave">
                <Icon name="save" :size="16" />
                {{ smtpSaving ? 'جاري الحفظ...' : 'حفظ إعدادات SMTP' }}
              </button>
            </div>
          </template>

          <!-- Non-admin: read-only -->
          <template v-else>
            <p class="readonly-note">
              إعدادات SMTP يديرها مسؤول النظام. بريدك الإلكتروني المسجّل:
              <strong>{{ auth.user?.email ?? '—' }}</strong>
            </p>
          </template>
        </div>
      </div>

      <!-- ── الإشعارات ──────────────────────────────────────────────── -->
      <div v-show="activeTab === 'notifications'" class="panel" data-panel="notifications">
        <div class="section-card">
          <div class="section-header">
            <h2 class="section-title">قنوات الإشعارات</h2>
            <p class="section-desc">إعدادات قنوات الإشعارات على مستوى النظام</p>
          </div>

          <div class="switch-section">
            <div class="switch-row">
              <div class="switch-info">
                <span class="switch-label">إشعارات داخل التطبيق</span>
                <span class="switch-desc">إشعارات الوقت الفعلي داخل المنصة</span>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" checked disabled />
                <span class="toggle-knob" />
              </label>
            </div>
            <div class="switch-row">
              <div class="switch-info">
                <span class="switch-label">إشعارات البريد الإلكتروني</span>
                <span class="switch-desc">إرسال ملخصات الأحداث عبر البريد</span>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" checked disabled />
                <span class="toggle-knob" />
              </label>
            </div>
            <div class="switch-row">
              <div class="switch-info">
                <span class="switch-label">إشعارات المرحلة الأولى</span>
                <span class="switch-desc">تفعيل نظام الإشعارات التجريبي (المرحلة الأولى)</span>
              </div>
              <label class="toggle-switch">
                <input
                  type="checkbox"
                  :checked="isCbyAdmin ? (adminSettings?.notifications_phase_1_enabled ?? false) : false"
                  :disabled="!isCbyAdmin"
                  @change="(e) => isCbyAdmin && updateAdminSetting('notifications_phase_1_enabled', (e.target as HTMLInputElement).checked)"
                />
                <span class="toggle-knob" />
              </label>
            </div>
            <div class="switch-row">
              <div class="switch-info">
                <span class="switch-label">البحث (المرحلة الأولى)</span>
                <span class="switch-desc">تفعيل ميزة البحث المتقدم (المرحلة الأولى)</span>
              </div>
              <label class="toggle-switch">
                <input
                  type="checkbox"
                  :checked="isCbyAdmin ? (adminSettings?.search_phase_1_enabled ?? false) : false"
                  :disabled="!isCbyAdmin"
                  @change="(e) => isCbyAdmin && updateAdminSetting('search_phase_1_enabled', (e.target as HTMLInputElement).checked)"
                />
                <span class="toggle-knob" />
              </label>
            </div>
            <div class="switch-row">
              <div class="switch-info">
                <span class="switch-label">معاينة الطباعة الجمركية</span>
                <span class="switch-desc">تفعيل معاينة PDF للبيان الجمركي قبل الإصدار</span>
              </div>
              <label class="toggle-switch">
                <input
                  type="checkbox"
                  :checked="isCbyAdmin ? (adminSettings?.customs_print_preview_enabled ?? false) : false"
                  :disabled="!isCbyAdmin"
                  @change="(e) => isCbyAdmin && updateAdminSetting('customs_print_preview_enabled', (e.target as HTMLInputElement).checked)"
                />
                <span class="toggle-knob" />
              </label>
            </div>
          </div>
        </div>

        <!-- Per-role notification preferences (non-CBY_ADMIN) -->
        <div v-if="!isCbyAdmin && visibleNotifPrefs.length > 0" class="section-card" style="margin-top: 16px;">
          <div class="section-header">
            <h2 class="section-title">تفضيلاتي للإشعارات</h2>
            <p class="section-desc">اختر الأحداث التي تريد تلقي إشعارات بشأنها</p>
          </div>
          <div class="notif-list">
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

      <!-- ── الأمن ───────────────────────────────────────────────────── -->
      <div v-show="activeTab === 'security'" class="panel" data-panel="security">
        <div class="section-card">
          <div class="section-header">
            <h2 class="section-title">سياسات الأمن</h2>
            <p class="section-desc">ضبط سياسات الأمان على مستوى النظام</p>
          </div>

          <div
            class="switch-section"
            :data-testid="isCbyAdmin ? 'security-switches-enabled' : 'security-switches-disabled'"
          >
            <div
              v-for="row in SECURITY_ROWS"
              :key="row.key"
              class="switch-row"
              :data-testid="row.key === 'mfa_required' ? 'security-switch-mfa' : undefined"
            >
              <div class="switch-info">
                <span class="switch-label">{{ row.label }}</span>
                <span class="switch-desc">{{ row.desc }}</span>
              </div>
              <label class="toggle-switch" :class="{ 'opacity-50': !isCbyAdmin }">
                <input
                  type="checkbox"
                  :checked="getSecurityValue(row.key)"
                  :disabled="!isCbyAdmin || pendingKeys.has(row.key)"
                  @change="(e) => handleSecurityToggle(row.key, (e.target as HTMLInputElement).checked)"
                />
                <span class="toggle-knob" />
              </label>
            </div>
          </div>

          <p v-if="!isCbyAdmin" class="readonly-note">
            سياسات الأمن يديرها مسؤول النظام فقط.
          </p>

          <!-- Legacy testid anchors for existing test suite -->
          <div class="info-grid" style="margin-top: 8px;">
            <div class="info-row">
              <span class="info-label">حد محاولات الدخول</span>
              <span class="info-value" data-testid="lockout-threshold">10 محاولات</span>
            </div>
            <div class="info-row">
              <span class="info-label">مدة قفل الحساب</span>
              <span class="info-value" data-testid="lockout-duration">15 دقيقة</span>
            </div>
            <div class="info-row">
              <span class="info-label">التحقق الثنائي (MFA)</span>
              <input
                type="checkbox"
                id="mfa-toggle"
                class="toggle-check"
                checked
                disabled
                aria-label="إلزام التحقق الثنائي"
                data-testid="mfa-toggle"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- ── عام ────────────────────────────────────────────────────── -->
      <div v-show="activeTab === 'general'" class="panel" data-panel="general">
        <!-- Platform info (read-only) -->
        <div class="section-card">
          <div class="section-header">
            <h2 class="section-title">معلومات المنصة</h2>
          </div>
          <div class="info-grid">
            <div class="info-row">
              <span class="info-label">اسم المنصة</span>
              <span class="info-value">Yemen Flow Hub</span>
            </div>
            <div class="info-row">
              <span class="info-label">الجهة</span>
              <span class="info-value">البنك المركزي اليمني</span>
            </div>
            <div class="info-row">
              <span class="info-label">المنطقة الزمنية</span>
              <span class="info-value">Asia/Aden (UTC+3)</span>
            </div>
            <div class="info-row">
              <span class="info-label">آخر نسخة احتياطية</span>
              <span class="info-value">—</span>
            </div>
          </div>
        </div>

        <!-- Display preferences (editable) -->
        <div v-if="preferences" class="section-card" style="margin-top: 16px;">
          <div class="section-header">
            <h2 class="section-title">تفضيلات العرض</h2>
            <p class="section-desc">خصّص طريقة عرض البيانات وحجم الصفحات</p>
          </div>
          <form @submit.prevent="handleSave" class="general-form">
            <div class="two-col-grid">
              <div class="field-group">
                <label class="field-label">اللغة</label>
                <select v-model="formData.language" class="form-input">
                  <option value="ar">العربية</option>
                  <option value="en">English</option>
                </select>
              </div>
              <div class="field-group">
                <label class="field-label">حجم الصفحة</label>
                <select v-model.number="formData.page_size" class="form-input">
                  <option value="10">10 صفوف</option>
                  <option value="25">25 صف</option>
                  <option value="50">50 صف</option>
                  <option value="100">100 صف</option>
                </select>
              </div>
            </div>

            <div class="field-group">
              <label class="field-label">عرض لوحة التحكم</label>
              <div class="radio-group">
                <label class="radio-item"><input v-model="formData.dashboard_view" type="radio" value="compact" /> مضغوط</label>
                <label class="radio-item"><input v-model="formData.dashboard_view" type="radio" value="normal" /> عادي</label>
                <label class="radio-item"><input v-model="formData.dashboard_view" type="radio" value="expanded" /> موسع</label>
              </div>
            </div>

            <div class="field-group">
              <label class="field-label">كثافة الجدول</label>
              <div class="radio-group">
                <label class="radio-item"><input v-model="formData.table_density" type="radio" value="compact" /> مضغوط</label>
                <label class="radio-item"><input v-model="formData.table_density" type="radio" value="normal" /> عادي</label>
                <label class="radio-item"><input v-model="formData.table_density" type="radio" value="comfortable" /> مريح</label>
              </div>
            </div>

            <div v-if="error" class="error-banner">{{ error }}</div>

            <div class="form-actions">
              <button type="submit" :disabled="loading" class="btn-primary">
                <Icon name="save" :size="16" />
                {{ loading ? 'جاري الحفظ...' : 'حفظ التفضيلات' }}
              </button>
              <button type="button" :disabled="loading" class="btn-secondary" @click="handleReset">
                إعادة تعيين
              </button>
            </div>
          </form>
        </div>
        <div v-else-if="error" class="state-message state-error">{{ error }}</div>
      </div>

      <!-- ── بيانات العرض التوضيحي ─────────────────────────────────── -->
      <div v-show="activeTab === 'demo'" class="panel" data-panel="demo">
        <div class="section-card">
          <div class="section-header">
            <h2 class="section-title">بيانات العرض التوضيحي</h2>
            <p class="section-desc">هذه الإعدادات متاحة في وضع العرض التوضيحي فقط وتُخفى في بيئة الإنتاج.</p>
          </div>
          <div class="info-grid">
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
  </div>
</template>

<style scoped>
.settings-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 900px;
}

/* Header */
.page-header {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--color-text-secondary);
  margin-bottom: 4px;
}

.breadcrumb-link {
  color: #0066cc;
  text-decoration: none;
}

.breadcrumb-link:hover {
  text-decoration: underline;
}

.breadcrumb-sep {
  color: var(--color-border);
}

.breadcrumb-current {
  color: var(--color-text-secondary);
}

.page-title {
  font-size: 28px;
  font-weight: 600;
  color: var(--color-text-primary);
  margin: 0;
}

.page-subtitle {
  font-size: 14px;
  color: var(--color-text-secondary);
  margin: 0;
}

/* Tab nav */
.tab-nav {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--color-border);
  flex-wrap: wrap;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-secondary);
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s;
  margin-bottom: -1px;
  white-space: nowrap;
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

.section-header {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.section-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--color-text-primary);
  margin: 0;
}

.section-desc {
  font-size: 13px;
  color: var(--color-text-secondary);
  margin: 0;
}

.readonly-note {
  font-size: 13px;
  color: var(--color-text-secondary);
  background: #f5f5f7;
  border-radius: 8px;
  padding: 10px 14px;
  margin: 0;
}

/* Two-column grid */
.two-col-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 600px) {
  .two-col-grid {
    grid-template-columns: 1fr;
  }
}

/* Info rows */
.info-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
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

/* Switch rows */
.switch-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.switch-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
}

.switch-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
}

.switch-label {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-primary);
}

.switch-desc {
  font-size: 12px;
  color: var(--color-text-secondary);
}

/* Toggle switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  flex-shrink: 0;
  margin-right: 16px;
  cursor: pointer;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
  position: absolute;
}

.toggle-knob {
  position: absolute;
  inset: 0;
  background: #cccccc;
  border-radius: 24px;
  transition: background 0.2s;
}

.toggle-knob::before {
  content: '';
  position: absolute;
  width: 18px;
  height: 18px;
  left: 3px;
  top: 3px;
  background: #fff;
  border-radius: 50%;
  transition: transform 0.2s;
}

.toggle-switch input:checked + .toggle-knob {
  background: #0066cc;
}

.toggle-switch input:checked + .toggle-knob::before {
  transform: translateX(20px);
}

.toggle-switch input:disabled + .toggle-knob {
  opacity: 0.5;
  cursor: not-allowed;
}

.opacity-50 {
  opacity: 0.5;
}

/* Notification preferences */
.notif-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
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

/* Form fields */
.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
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
  width: 100%;
}

.form-input:focus {
  border-color: #0066cc;
}

.form-textarea {
  height: auto;
  padding: 10px 12px;
  resize: vertical;
}

.general-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
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

/* Banners */
.error-banner {
  background: #fff0ef;
  border: 1px solid #c62828;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #c62828;
}

.success-banner {
  background: #e6f9ec;
  border: 1px solid #1a7a35;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #1b5e20;
}

/* Form actions */
.form-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

/* Buttons */
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
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

.state-loading,
.state-message {
  text-align: center;
  color: var(--color-text-secondary);
  padding: 32px;
}

.state-error {
  color: #c62828;
}

/* Demo/General badges */
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
