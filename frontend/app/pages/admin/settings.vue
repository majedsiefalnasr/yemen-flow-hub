<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useAdminSettings } from '../../composables/useAdminSettings'
import { useAuthStore } from '../../stores/auth.store'
import { useRouter } from 'nuxt/app'
import Icon from '../../components/shared/Icon.vue'
import PageHeader from '../../components/layout/PageHeader.vue'
import LoadErrorAlert from '../../components/shared/LoadErrorAlert.vue'
import { Skeleton } from '@/components/ui/skeleton'
import type { IconName } from '../../utils/icon-map'

definePageMeta({
  middleware: 'auth',
})

const router = useRouter()
const auth = useAuthStore()

if (!auth.isCbyAdmin) {
  router.push('/dashboard')
}

const {
  settings,
  loading,
  error,
  pendingKeys,
  smtpSettings,
  securityPolicies,
  fetchSettings,
  fetchSmtpSettings,
  fetchSecurityPolicies,
  updateSetting,
  updateSmtpSettings,
  updateSecurityPolicy,
} = useAdminSettings()

type TabId = 'workflow' | 'email' | 'security' | 'general'

const activeTab = ref<TabId>('workflow')

const tabs: Array<{ id: TabId; label: string; icon: IconName }> = [
  { id: 'workflow', label: 'سير العمل', icon: 'workflow' },
  { id: 'email', label: 'البريد الإلكتروني', icon: 'mail' },
  { id: 'security', label: 'الأمن', icon: 'shield-alert' },
  { id: 'general', label: 'عام', icon: 'settings' },
]

// ── SMTP ─────────────────────────────────────────────────────────────────────
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

// ── Security policies ─────────────────────────────────────────────────────────
const SECURITY_ROWS = [
  {
    key: 'mfa_required',
    label: 'إلزام التحقق الثنائي (MFA)',
    desc: 'يُجبر جميع المستخدمين على إدخال رمز OTP عند تسجيل الدخول',
  },
  {
    key: 'password_expiry_90_days',
    label: 'انتهاء صلاحية كلمة المرور (90 يوم)',
    desc: 'يُجبر المستخدمين على تغيير كلمة المرور كل 90 يوماً',
  },
  {
    key: 'lockout_after_5_attempts',
    label: 'قفل الحساب بعد 5 محاولات',
    desc: 'يُقفل الحساب تلقائياً بعد 5 محاولات دخول فاشلة متتالية',
  },
  {
    key: 'encrypt_uploads_aes256',
    label: 'تشفير الملفات المرفوعة (AES-256)',
    desc: 'تشفير جميع الملفات المرفوعة باستخدام معيار AES-256',
  },
  {
    key: 'log_all_audit',
    label: 'تسجيل جميع أحداث التدقيق',
    desc: 'تسجيل جميع الإجراءات في سجل التدقيق الشامل',
  },
  {
    key: 'allow_external_access',
    label: 'السماح بالوصول الخارجي',
    desc: 'السماح للمستخدمين بالوصول من خارج الشبكة الداخلية',
  },
]

function getSecurityValue(key: string): boolean {
  return (securityPolicies.value as any)?.[key] ?? false
}

// ── Feature toggles (عام tab) ─────────────────────────────────────────────────
const FEATURE_ROWS = [
  {
    key: 'notifications_phase_1_enabled',
    label: 'الإشعارات (المرحلة الأولى)',
    desc: 'تفعيل نظام الإشعارات التجريبي',
  },
  {
    key: 'search_phase_1_enabled',
    label: 'البحث (المرحلة الأولى)',
    desc: 'تفعيل ميزة البحث المتقدم التجريبية',
  },
  {
    key: 'customs_print_preview_enabled',
    label: 'معاينة الطباعة الجمركية',
    desc: 'تفعيل معاينة PDF للبيان الجمركي قبل الإصدار',
  },
]

// ── Mount ─────────────────────────────────────────────────────────────────────
onMounted(async () => {
  if (!auth.isCbyAdmin) return

  await fetchSettings()
  fetchSmtpSettings()
  fetchSecurityPolicies()

  watch(
    () => smtpSettings.value,
    (s) => {
      if (s) {
        smtpForm.value = {
          host: s.host,
          port: s.port,
          username: s.username,
          password: '',
          template: s.template,
        }
      }
    },
  )
})
</script>

<template>
  <div class="admin-settings-page">
    <PageHeader
      title="إعدادات النظام"
      subtitle="إدارة إعدادات المنصة وسياسات الأمن والميزات"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/dashboard' }, { label: 'إعدادات النظام' }]"
    />

    <!-- Loading -->
    <div v-if="loading" class="space-y-4" aria-busy="true" aria-label="جارٍ تحميل الإعدادات">
      <Skeleton class="h-10 w-full max-w-xl rounded-lg" />
      <Skeleton class="h-48 w-full rounded-xl" />
      <Skeleton class="h-48 w-full rounded-xl" />
    </div>

    <template v-else>
      <!-- Tab nav -->
      <nav class="tab-nav" role="tablist">
        <button
          v-for="tab in tabs"
          :key="tab.id"
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

      <LoadErrorAlert
        v-if="error"
        class="mb-4"
        :message="error"
        title="تعذّر تحميل الإعدادات"
        @retry="fetchSettings()"
      />

      <!-- Tab panels -->
      <div class="tab-content">
        <!-- ── سير العمل ────────────────────────────────────────────── -->
        <div v-show="activeTab === 'workflow'" class="panel">
          <div class="section-card">
            <div class="section-header">
              <h2 class="section-title">إعدادات وقت وحجم النظام</h2>
              <p class="section-desc">ضبط مهل المراحل وحجم الملفات</p>
            </div>

            <div v-if="settings" class="two-col-grid">
              <div class="stepper-field">
                <label class="field-label">مدة صلاحية المطالبة (دقيقة)</label>
                <p class="field-hint">القيمة المسموحة من 5 إلى 60 دقيقة</p>
                <div class="stepper-row">
                  <button
                    class="stepper-btn"
                    :disabled="loading || settings.support_claim_ttl <= 5"
                    @click="
                      updateSetting(
                        'support_claim_ttl',
                        Math.max(5, settings.support_claim_ttl - 1),
                      )
                    "
                  >
                    −
                  </button>
                  <span class="stepper-value">{{ settings.support_claim_ttl }}</span>
                  <button
                    class="stepper-btn"
                    :disabled="loading || settings.support_claim_ttl >= 60"
                    @click="
                      updateSetting(
                        'support_claim_ttl',
                        Math.min(60, settings.support_claim_ttl + 1),
                      )
                    "
                  >
                    +
                  </button>
                </div>
              </div>

              <div class="stepper-field">
                <label class="field-label">انتظار جلسة التصويت (دقيقة)</label>
                <p class="field-hint">القيمة المسموحة من 15 إلى 120 دقيقة</p>
                <div class="stepper-row">
                  <button
                    class="stepper-btn"
                    :disabled="loading || settings.voting_session_timeout <= 15"
                    @click="
                      updateSetting(
                        'voting_session_timeout',
                        Math.max(15, settings.voting_session_timeout - 1),
                      )
                    "
                  >
                    −
                  </button>
                  <span class="stepper-value">{{ settings.voting_session_timeout }}</span>
                  <button
                    class="stepper-btn"
                    :disabled="loading || settings.voting_session_timeout >= 120"
                    @click="
                      updateSetting(
                        'voting_session_timeout',
                        Math.min(120, settings.voting_session_timeout + 1),
                      )
                    "
                  >
                    +
                  </button>
                </div>
              </div>

              <div class="stepper-field">
                <label class="field-label">حد رفع PDF (MB)</label>
                <p class="field-hint">القيمة المسموحة من 1 إلى 50 MB</p>
                <div class="stepper-row">
                  <button
                    class="stepper-btn"
                    :disabled="loading || settings.pdf_upload_size_limit <= 1"
                    @click="
                      updateSetting(
                        'pdf_upload_size_limit',
                        Math.max(1, settings.pdf_upload_size_limit - 1),
                      )
                    "
                  >
                    −
                  </button>
                  <span class="stepper-value">{{ settings.pdf_upload_size_limit }}</span>
                  <button
                    class="stepper-btn"
                    :disabled="loading || settings.pdf_upload_size_limit >= 50"
                    @click="
                      updateSetting(
                        'pdf_upload_size_limit',
                        Math.min(50, settings.pdf_upload_size_limit + 1),
                      )
                    "
                  >
                    +
                  </button>
                </div>
              </div>

              <div class="stepper-field">
                <label class="field-label">مدة حظر الدخول (دقيقة)</label>
                <p class="field-hint">القيمة المسموحة من 5 إلى 60 دقيقة</p>
                <div class="stepper-row">
                  <button
                    class="stepper-btn"
                    :disabled="loading || settings.login_lockout_duration <= 5"
                    @click="
                      updateSetting(
                        'login_lockout_duration',
                        Math.max(5, settings.login_lockout_duration - 1),
                      )
                    "
                  >
                    −
                  </button>
                  <span class="stepper-value">{{ settings.login_lockout_duration }}</span>
                  <button
                    class="stepper-btn"
                    :disabled="loading || settings.login_lockout_duration >= 60"
                    @click="
                      updateSetting(
                        'login_lockout_duration',
                        Math.min(60, settings.login_lockout_duration + 1),
                      )
                    "
                  >
                    +
                  </button>
                </div>
              </div>
            </div>

            <template v-if="settings">
              <div class="section-header" style="margin-top: 8px">
                <h3 class="section-title section-title--sub">دورة الموافقة</h3>
              </div>
              <div class="two-col-grid">
                <div class="field-group">
                  <label class="field-label">حجم لجنة المساندة</label>
                  <input
                    type="number"
                    class="form-input"
                    :value="settings.support_committee_size"
                    @change="
                      (e) =>
                        updateSetting(
                          'support_committee_size',
                          Number((e.target as HTMLInputElement).value),
                        )
                    "
                  />
                </div>
                <div class="field-group">
                  <label class="field-label">حجم اللجنة التنفيذية</label>
                  <input
                    type="number"
                    class="form-input"
                    :value="settings.executive_committee_size"
                    @change="
                      (e) =>
                        updateSetting(
                          'executive_committee_size',
                          Number((e.target as HTMLInputElement).value),
                        )
                    "
                  />
                </div>
                <div class="field-group">
                  <label class="field-label">الحد الأدنى للنصاب</label>
                  <input
                    type="number"
                    class="form-input"
                    :value="settings.minimum_quorum"
                    @change="
                      (e) =>
                        updateSetting(
                          'minimum_quorum',
                          Number((e.target as HTMLInputElement).value),
                        )
                    "
                  />
                </div>
                <div class="field-group">
                  <label class="field-label">مهلة المراجعة (ساعة)</label>
                  <input
                    type="number"
                    class="form-input"
                    :value="settings.review_timeout_hours"
                    @change="
                      (e) =>
                        updateSetting(
                          'review_timeout_hours',
                          Number((e.target as HTMLInputElement).value),
                        )
                    "
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
                      :checked="settings.secret_voting"
                      :disabled="pendingKeys.has('secret_voting')"
                      @change="
                        (e) =>
                          updateSetting('secret_voting', (e.target as HTMLInputElement).checked)
                      "
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
                      :checked="settings.director_tiebreak"
                      :disabled="pendingKeys.has('director_tiebreak')"
                      @change="
                        (e) =>
                          updateSetting('director_tiebreak', (e.target as HTMLInputElement).checked)
                      "
                    />
                    <span class="toggle-knob" />
                  </label>
                </div>
              </div>

              <!-- Duplicate invoice policy (AC9) -->
              <div class="section-divider" />
              <div class="section-sub-header">
                <h3 class="section-sub-title">سياسة الفواتير المكررة</h3>
                <p class="section-sub-desc">
                  تحديد سلوك النظام عند اكتشاف رقم فاتورة مكرر عبر البنوك
                </p>
              </div>
              <div
                class="field-group"
                style="max-width: 320px"
                data-testid="duplicate-policy-field"
              >
                <label class="field-label">إجراء التكرار</label>
                <select
                  class="form-input"
                  :value="settings.duplicate_invoice_policy"
                  :disabled="pendingKeys.has('duplicate_invoice_policy')"
                  data-testid="duplicate-policy-select"
                  @change="
                    (e) =>
                      updateSetting(
                        'duplicate_invoice_policy',
                        (e.target as HTMLSelectElement).value,
                      )
                  "
                >
                  <option value="warn">تحذير (warn): إنشاء الطلب مع تسجيل تحذير</option>
                  <option value="block">حظر (block): رفض الطلب المكرر</option>
                </select>
              </div>
            </template>
          </div>
        </div>

        <!-- ── البريد الإلكتروني ──────────────────────────────────── -->
        <div v-show="activeTab === 'email'" class="panel">
          <div class="section-card">
            <div class="section-header">
              <h2 class="section-title">إعدادات البريد الإلكتروني (SMTP)</h2>
              <p class="section-desc">تهيئة خادم البريد لإرسال الإشعارات</p>
            </div>

            <div class="two-col-grid">
              <div class="field-group">
                <label class="field-label">الخادم (Host)</label>
                <input
                  v-model="smtpForm.host"
                  type="text"
                  class="form-input"
                  placeholder="smtp.example.com"
                />
              </div>
              <div class="field-group">
                <label class="field-label">المنفذ (Port)</label>
                <input
                  v-model.number="smtpForm.port"
                  type="number"
                  class="form-input"
                  placeholder="587"
                />
              </div>
              <div class="field-group">
                <label class="field-label">اسم المستخدم</label>
                <input v-model="smtpForm.username" type="text" class="form-input" />
              </div>
              <div class="field-group">
                <label class="field-label">كلمة المرور</label>
                <input
                  v-model="smtpForm.password"
                  type="password"
                  class="form-input"
                  placeholder="••••••••"
                />
              </div>
            </div>
            <div class="field-group">
              <label class="field-label">قالب البريد</label>
              <textarea v-model="smtpForm.template" class="form-input form-textarea" rows="3" />
            </div>

            <LoadErrorAlert
              v-if="smtpError"
              class="mb-3"
              :message="smtpError"
              title="تعذّر حفظ إعدادات البريد"
              :show-retry="false"
            />
            <div v-if="smtpSuccess" class="success-banner">تم حفظ إعدادات SMTP بنجاح</div>

            <div class="form-actions">
              <button class="btn-primary" :disabled="smtpSaving" @click="handleSmtpSave">
                <Icon name="save" :size="16" />
                {{ smtpSaving ? 'جاري الحفظ...' : 'حفظ إعدادات SMTP' }}
              </button>
            </div>
          </div>
        </div>

        <!-- ── الأمن ─────────────────────────────────────────────── -->
        <div v-show="activeTab === 'security'" class="panel">
          <div class="section-card">
            <div class="section-header">
              <h2 class="section-title">سياسات الأمن</h2>
              <p class="section-desc">ضبط سياسات الأمان على مستوى النظام</p>
            </div>

            <div class="switch-section">
              <div v-for="row in SECURITY_ROWS" :key="row.key" class="switch-row">
                <div class="switch-info">
                  <span class="switch-label">{{ row.label }}</span>
                  <span class="switch-desc">{{ row.desc }}</span>
                </div>
                <label class="toggle-switch">
                  <input
                    type="checkbox"
                    :checked="getSecurityValue(row.key)"
                    :disabled="pendingKeys.has(row.key)"
                    @change="
                      (e) => updateSecurityPolicy(row.key, (e.target as HTMLInputElement).checked)
                    "
                  />
                  <span class="toggle-knob" />
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- ── عام ───────────────────────────────────────────────── -->
        <div v-show="activeTab === 'general'" class="panel">
          <div class="section-card">
            <div class="section-header">
              <h2 class="section-title">تفعيل الميزات</h2>
              <p class="section-desc">تشغيل وإيقاف ميزات المنصة التجريبية</p>
            </div>

            <div v-if="settings" class="switch-section">
              <div v-for="row in FEATURE_ROWS" :key="row.key" class="switch-row">
                <div class="switch-info">
                  <span class="switch-label">{{ row.label }}</span>
                  <span class="switch-desc">{{ row.desc }}</span>
                </div>
                <label class="toggle-switch">
                  <input
                    type="checkbox"
                    :checked="(settings as any)[row.key]"
                    :disabled="loading || pendingKeys.has(row.key)"
                    @change="(e) => updateSetting(row.key, (e.target as HTMLInputElement).checked)"
                  />
                  <span class="toggle-knob" />
                </label>
              </div>
            </div>
          </div>

          <!-- Platform info -->
          <div class="section-card" style="margin-top: 16px">
            <div class="section-header">
              <h2 class="section-title">معلومات المنصة</h2>
            </div>
            <div class="info-grid">
              <div class="info-row">
                <span class="info-label">اسم المنصة</span>
                <span class="info-value">اللجنة الوطنية لتنظيم وتمويل الواردات</span>
              </div>
              <div class="info-row">
                <span class="info-label">الجهة</span>
                <span class="info-value">اللجنة الوطنية لتنظيم وتمويل الواردات</span>
              </div>
              <div class="info-row">
                <span class="info-label">المنطقة الزمنية</span>
                <span class="info-value">Asia/Aden (UTC+3)</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.admin-settings-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 900px;
}

/* Tab nav */
.tab-nav {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  font-family: var(--font-section);
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.25rem;
  color: var(--muted-foreground);
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  transition:
    color 0.15s,
    border-color 0.15s;
  margin-bottom: -1px;
  white-space: nowrap;
}

.tab-btn.active {
  color: var(--primary);
  border-bottom-color: var(--primary);
}

.tab-btn:hover:not(.active) {
  color: var(--foreground);
}

.tab-content {
  min-height: 200px;
}

/* Section card */
.section-card {
  background: var(--background);
  border: 1px solid var(--border);
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
  font-family: var(--font-heading);
  font-size: 1.125rem;
  font-weight: 600;
  line-height: 1.75rem;
  color: var(--foreground);
  margin: 0;
}

.section-title--sub {
  font-size: 0.9375rem;
  line-height: 1.5rem;
}

.section-desc {
  font-size: 0.8125rem;
  line-height: 1.25rem;
  color: var(--muted-foreground);
  margin: 0;
}

.section-divider {
  border: none;
  border-top: 1px solid var(--border);
  margin: 20px 0;
}

.section-sub-header {
  margin-bottom: 12px;
}

.section-sub-title {
  font-family: var(--font-section);
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.5rem;
  color: var(--foreground);
  margin: 0 0 2px;
}

.section-sub-desc {
  font-size: 0.8125rem;
  line-height: 1.25rem;
  color: var(--muted-foreground);
  margin: 0;
}

/* Stepper */
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

.stepper-field {
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  font-family: var(--font-section);
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1rem;
  color: var(--muted-foreground);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.field-hint {
  font-size: 0.6875rem;
  line-height: 1rem;
  color: var(--muted-foreground);
  margin: 0;
}

.stepper-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 4px;
}

.stepper-btn {
  width: 32px;
  height: 32px;
  background: var(--muted);
  border: 1px solid var(--border);
  border-radius: 8px;
  font-family: var(--font-section);
  font-size: 1rem;
  font-weight: 600;
  line-height: 1;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stepper-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.stepper-value {
  font-size: 1.125rem;
  font-weight: 600;
  line-height: 1.75rem;
  color: var(--foreground);
  min-width: 36px;
  text-align: center;
  font-variant-numeric: tabular-nums;
}

/* Form fields */
.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-input {
  height: 44px;
  padding: 0 12px;
  border: 1px solid var(--border);
  border-radius: 12px;
  font-size: 0.875rem;
  line-height: 1.5rem;
  color: var(--foreground);
  background: var(--background);
  outline: none;
  width: 100%;
}

.form-input:focus {
  border-color: var(--primary);
}

.form-textarea {
  height: auto;
  padding: 10px 12px;
  resize: vertical;
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
  border: 1px solid var(--border);
  border-radius: 10px;
}

.switch-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
}

.switch-label {
  font-family: var(--font-section);
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.5rem;
  color: var(--foreground);
}

.switch-desc {
  font-size: 0.75rem;
  line-height: 1.25rem;
  color: var(--muted-foreground);
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
  background: var(--border);
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
  background: var(--background);
  border-radius: 50%;
  transition: transform 0.2s;
}

.toggle-switch input:checked + .toggle-knob {
  background: var(--primary);
}

.toggle-switch input:checked + .toggle-knob::before {
  transform: translateX(20px);
}

.toggle-switch input:disabled + .toggle-knob {
  opacity: 0.5;
  cursor: not-allowed;
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
  background: var(--muted);
  border-radius: 10px;
}

.info-label {
  font-size: 0.875rem;
  line-height: 1.5rem;
  color: var(--muted-foreground);
}

.info-value {
  font-family: var(--font-section);
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.5rem;
  color: var(--foreground);
}

/* Banners */
.success-banner {
  background: color-mix(in srgb, var(--color-success) 10%, var(--background));
  border: 1px solid color-mix(in srgb, var(--color-success) 40%, transparent);
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 0.8125rem;
  line-height: 1.25rem;
  color: var(--color-success);
}

/* Form actions */
.form-actions {
  display: flex;
  gap: 12px;
}

/* Buttons */
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 44px;
  padding: 0 20px;
  background: var(--primary);
  color: var(--primary-foreground);
  border: none;
  border-radius: 16px;
  font-family: var(--font-section);
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.25rem;
  cursor: pointer;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.state-loading {
  text-align: center;
  color: var(--muted-foreground);
  padding: 32px;
}
</style>
