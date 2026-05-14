<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { useAuthStore } from '../stores/auth.store'
import { useRouter } from 'vue-router'

definePageMeta({
  layout: 'auth',
  middleware: ['guest'],
})

const router = useRouter()
const auth = useAuthStore()

const schema = toTypedSchema(
  z.object({
    email: z.string().min(1, 'البريد الإلكتروني مطلوب').email('صيغة البريد الإلكتروني غير صحيحة'),
    password: z.string().min(8, 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'),
  }),
)

const { handleSubmit, defineField, errors } = useForm({ validationSchema: schema })

const [email, emailAttrs] = defineField('email')
const [password, passwordAttrs] = defineField('password')

const isLoading = ref(false)
const serverError = ref<string | null>(null)

const onSubmit = handleSubmit(async (values) => {
  isLoading.value = true
  serverError.value = null

  try {
    await auth.login(values.email, values.password)
    await router.push('/dashboard')
  }
  catch (err: unknown) {
    const status = (err as { statusCode?: number })?.statusCode
    if (status === 429) {
      serverError.value = 'لقد تجاوزت الحد المسموح به من محاولات تسجيل الدخول. يرجى الانتظار دقيقة ثم حاول مرة أخرى.'
    }
    else if (
      typeof err === 'object' && err !== null &&
      'data' in err && typeof (err as { data?: { message?: string } }).data === 'object'
    ) {
      const data = (err as { data?: { message?: string } }).data
      serverError.value = data?.message ?? 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.'
    }
    else {
      serverError.value = 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.'
    }
  }
  finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="login-card">
    <!-- Logo / Brand -->
    <div class="login-brand">
      <div class="brand-icon">🏦</div>
      <h1 class="brand-title">Yemen Flow Hub</h1>
      <p class="brand-subtitle">منصة إدارة طلبات التمويل</p>
    </div>

    <!-- Error alert -->
    <div v-if="serverError" class="error-alert" role="alert">
      {{ serverError }}
    </div>

    <!-- Login form -->
    <form class="login-form" novalidate @submit.prevent="onSubmit">
      <!-- Email field -->
      <div class="field-group">
        <label for="email" class="field-label">البريد الإلكتروني</label>
        <input
          id="email"
          v-model="email"
          v-bind="emailAttrs"
          type="email"
          class="field-input"
          :class="{ 'field-input--error': errors.email }"
          placeholder="user@example.com"
          autocomplete="email"
          dir="ltr"
        />
        <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
      </div>

      <!-- Password field -->
      <div class="field-group">
        <label for="password" class="field-label">كلمة المرور</label>
        <input
          id="password"
          v-model="password"
          v-bind="passwordAttrs"
          type="password"
          class="field-input"
          :class="{ 'field-input--error': errors.password }"
          placeholder="••••••••"
          autocomplete="current-password"
        />
        <span v-if="errors.password" class="field-error">{{ errors.password }}</span>
      </div>

      <!-- Submit -->
      <button
        type="submit"
        class="submit-btn"
        :disabled="isLoading"
      >
        <span v-if="isLoading" class="btn-spinner" aria-hidden="true" />
        <span>{{ isLoading ? 'جارٍ تسجيل الدخول...' : 'تسجيل الدخول' }}</span>
      </button>
    </form>
  </div>
</template>

<style scoped>
.login-card {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  padding: 40px 32px;
  box-shadow: 0 2px 8px rgba(29, 29, 31, 0.04);
}

.login-brand {
  text-align: center;
  margin-bottom: 32px;
}

.brand-icon {
  font-size: 40px;
  margin-bottom: 12px;
}

.brand-title {
  font-size: 20px;
  font-weight: 500;
  color: var(--color-text-primary);
  margin: 0 0 4px;
}

.brand-subtitle {
  font-size: 14px;
  color: var(--color-text-secondary);
  margin: 0;
}

.error-alert {
  background-color: #fff5f5;
  border: 1px solid var(--color-rejected);
  border-radius: 8px;
  color: var(--color-rejected);
  font-size: 14px;
  padding: 12px 16px;
  margin-bottom: 20px;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-primary);
}

.field-input {
  min-height: 44px;
  padding: 0 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-input);
  font-size: 15px;
  font-family: var(--font-arabic);
  color: var(--color-text-primary);
  background-color: var(--color-surface);
  transition: border-color 120ms ease;
  width: 100%;
  box-sizing: border-box;
}

.field-input:focus {
  outline: none;
  border-width: 1.5px;
  border-color: var(--color-primary);
}

.field-input--error {
  border-color: var(--color-rejected);
}

.field-error {
  font-size: 13px;
  color: var(--color-rejected);
}

.submit-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 24px;
  background-color: var(--color-primary);
  color: #ffffff;
  border: none;
  border-radius: var(--radius-button);
  font-size: 15px;
  font-weight: 500;
  font-family: var(--font-arabic);
  cursor: pointer;
  width: 100%;
  transition: background-color 120ms ease;
}

.submit-btn:hover:not(:disabled) {
  background-color: var(--color-primary-hover);
}

.submit-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.btn-spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
