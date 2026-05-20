<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useProfile } from '../composables/useProfile'
import { useAuthStore } from '../stores/auth.store'
import { ROLE_LABELS } from '../constants/workflow'

definePageMeta({
  middleware: 'auth',
})

const { profile, loading, error, fetchProfile, changePassword } = useProfile()
const auth = useAuthStore()

const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const passwordLoading = ref(false)
const passwordError = ref<string | null>(null)
const passwordSuccess = ref(false)
const mfaEnabled = ref(true)

const roleLabel = computed(() => {
  if (!profile.value) return ''
  return ROLE_LABELS[profile.value.role] || profile.value.role
})

const avatarInitials = computed(() => {
  const name = profile.value?.name ?? auth.user?.name ?? ''
  const parts = name.trim().split(/\s+/).filter(Boolean)
  const firstInitial = parts[0]?.[0]
  const secondInitial = parts[1]?.[0]

  if (firstInitial && secondInitial) return firstInitial + secondInitial
  return firstInitial ?? '؟'
})

const lastLoginFormatted = computed(() => {
  return new Intl.DateTimeFormat('ar-YE', {
    dateStyle: 'long',
    timeStyle: 'short',
  }).format(new Date())
})

const handleChangePassword = async () => {
  passwordError.value = null
  passwordSuccess.value = false
  passwordLoading.value = true

  const success = await changePassword(passwordForm.value)

  if (success) {
    passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
    passwordSuccess.value = true
  }
  else {
    passwordError.value = error.value || 'فشل تغيير كلمة المرور'
  }

  passwordLoading.value = false
}

onMounted(fetchProfile)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">الملف الشخصي</h1>
    </div>

    <div v-if="loading" class="state-message">جارٍ التحميل…</div>

    <div v-else-if="profile" class="profile-layout">

      <!-- Left column: avatar + stats -->
      <div class="sidebar-col">
        <!-- Avatar Card -->
        <div class="section-card avatar-card">
          <div class="avatar-circle" data-testid="avatar-initials">
            {{ avatarInitials }}
          </div>
          <div class="avatar-name">{{ profile.name }}</div>
          <div class="avatar-role">
            <span class="badge badge-role">{{ roleLabel }}</span>
          </div>
          <div v-if="profile.bank_name_ar" class="avatar-bank">{{ profile.bank_name_ar }}</div>
        </div>

        <!-- Stats Card -->
        <div class="section-card stats-card">
          <h2 class="section-title">الإحصائيات</h2>
          <div class="stat-row" data-testid="stat-last-login">
            <span class="stat-label">آخر تسجيل دخول</span>
            <span class="stat-value">{{ lastLoginFormatted }}</span>
          </div>
          <div class="stat-row" data-testid="stat-total-actions">
            <span class="stat-label">إجمالي الإجراءات</span>
            <span class="stat-value">—</span>
          </div>
        </div>

        <!-- MFA Toggle Card -->
        <div class="section-card mfa-card">
          <h2 class="section-title">التحقق الثنائي (MFA)</h2>
          <div class="mfa-row">
            <div class="mfa-info">
              <span class="mfa-status" :class="mfaEnabled ? 'status-active' : 'status-inactive'">
                {{ mfaEnabled ? 'مُفعَّل' : 'معطَّل' }}
              </span>
              <span class="mfa-desc">{{ mfaEnabled ? 'حسابك محمي بالتحقق الثنائي عبر OTP' : 'يُنصح بتفعيل التحقق الثنائي' }}</span>
            </div>
            <button
              class="btn-mfa"
              :class="mfaEnabled ? 'btn-mfa-off' : 'btn-mfa-on'"
              data-testid="mfa-toggle-btn"
              @click="mfaEnabled = !mfaEnabled"
            >
              {{ mfaEnabled ? 'إلغاء تفعيل MFA' : 'تفعيل MFA' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Right column: info + password + activity -->
      <div class="main-col">
        <!-- User Info Card -->
        <div class="section-card">
          <h2 class="section-title">معلومات الحساب</h2>
          <div class="info-grid">
            <div class="info-field">
              <span class="info-label">الاسم الكامل</span>
              <span class="info-value" data-testid="profile-name">{{ profile.name }}</span>
            </div>
            <div class="info-field">
              <span class="info-label">البريد الإلكتروني</span>
              <span class="info-value email-val" data-testid="profile-email">{{ profile.email }}</span>
              <span class="readonly-note">قراءة فقط</span>
            </div>
            <div class="info-field">
              <span class="info-label">الدور الوظيفي</span>
              <span class="badge badge-role">{{ roleLabel }}</span>
            </div>
            <div v-if="profile.bank_name_ar" class="info-field">
              <span class="info-label">الجهة</span>
              <span class="info-value">{{ profile.bank_name_ar }}</span>
            </div>
          </div>
        </div>

        <!-- Change Password Card -->
        <div class="section-card">
          <h2 class="section-title">تغيير كلمة المرور</h2>
          <form @submit.prevent="handleChangePassword" class="password-form">
            <div class="form-field">
              <label class="form-label">كلمة المرور الحالية</label>
              <input
                v-model="passwordForm.current_password"
                type="password"
                class="form-input"
                required
              />
            </div>
            <div class="form-field">
              <label class="form-label">كلمة المرور الجديدة</label>
              <input
                v-model="passwordForm.password"
                type="password"
                class="form-input"
                placeholder="8+ أحرف"
                required
              />
            </div>
            <div class="form-field">
              <label class="form-label">تأكيد كلمة المرور</label>
              <input
                v-model="passwordForm.password_confirmation"
                type="password"
                class="form-input"
                required
              />
            </div>

            <div v-if="passwordError" class="error-banner">{{ passwordError }}</div>
            <div v-if="passwordSuccess" class="success-banner">تم تغيير كلمة المرور بنجاح</div>

            <button type="submit" :disabled="passwordLoading" class="btn-primary">
              {{ passwordLoading ? 'جارٍ التحديث...' : 'تغيير كلمة المرور' }}
            </button>
          </form>
        </div>

        <!-- Recent Activity Card -->
        <div class="section-card">
          <h2 class="section-title">النشاط الأخير</h2>
          <div class="activity-placeholder" data-testid="recent-activity">
            <p class="section-desc">سيتم عرض آخر الإجراءات التي قمت بها على النظام هنا.</p>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="state-message state-error">{{ error }}</div>
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 24px;
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

.profile-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 24px;
  align-items: start;
}

@media (max-width: 768px) {
  .profile-layout {
    grid-template-columns: 1fr;
  }
}

.sidebar-col,
.main-col {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Section card */
.section-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.section-title {
  font-size: 16px;
  font-weight: 500;
  color: var(--color-text-primary);
  margin: 0;
}

.section-desc {
  font-size: 13px;
  color: var(--color-text-secondary);
  margin: 0;
  line-height: 1.6;
}

/* Avatar */
.avatar-card {
  align-items: center;
  text-align: center;
}

.avatar-circle {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: #0066cc;
  color: #fff;
  font-size: 28px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
}

.avatar-name {
  font-size: 17px;
  font-weight: 500;
  color: var(--color-text-primary);
}

.avatar-role {
  display: flex;
  justify-content: center;
}

.avatar-bank {
  font-size: 13px;
  color: var(--color-text-secondary);
}

/* Stats */
.stat-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-border);
  font-size: 13px;
}

.stat-row:last-child {
  border-bottom: none;
}

.stat-label {
  color: var(--color-text-secondary);
}

.stat-value {
  font-weight: 500;
  color: var(--color-text-primary);
}

/* MFA */
.mfa-row {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.mfa-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.mfa-status {
  font-size: 14px;
  font-weight: 600;
}

.status-active {
  color: #1b5e20;
}

.status-inactive {
  color: #c62828;
}

.mfa-desc {
  font-size: 12px;
  color: var(--color-text-secondary);
  line-height: 1.4;
}

.btn-mfa {
  padding: 8px 16px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  border: 1px solid;
}

.btn-mfa-off {
  background: #fff0ef;
  color: #c62828;
  border-color: #c62828;
}

.btn-mfa-on {
  background: #e6f9ec;
  color: #1b5e20;
  border-color: #1b5e20;
}

/* Info grid */
.info-grid {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.info-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-label {
  font-size: 12px;
  color: var(--color-text-secondary);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.info-value {
  font-size: 15px;
  color: var(--color-text-primary);
}

.email-val {
  direction: ltr;
  text-align: right;
}

.readonly-note {
  font-size: 11px;
  color: #8e8e93;
}

/* Password form */
.password-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
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
}

.form-input:focus {
  border-color: #0066cc;
}

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
  border: 1px solid #1b5e20;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #1b5e20;
}

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
  align-self: flex-start;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Activity */
.activity-placeholder {
  padding: 16px;
  background: #f5f5f7;
  border-radius: 10px;
}

/* Badges */
.badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.badge-role {
  background: #f0f0f3;
  color: #6e6e73;
}

/* States */
.state-message {
  text-align: center;
  color: var(--color-text-secondary);
  padding: 32px;
}

.state-error {
  color: #c62828;
}
</style>
