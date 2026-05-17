<template>
  <div class="min-h-screen bg-[#f5f5f7] p-6">
    <div class="mx-auto max-w-4xl">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-[#1d1d1f]">إعدادات النظام</h1>
        <p class="text-[#6e6e73]">إدارة إعدادات النظام والميزات</p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="rounded-lg bg-white p-8 shadow-sm">
        <div class="flex items-center justify-center">
          <div class="h-8 w-8 animate-spin rounded-full border-4 border-[#d2d2d7] border-t-[#0071e3]"></div>
          <span class="mr-3 text-[#6e6e73]">جاري التحميل...</span>
        </div>
      </div>

      <!-- Settings -->
      <div v-else-if="settings" class="space-y-6">
        <!-- Numeric Settings -->
        <div class="rounded-lg bg-white p-6 shadow-sm">
          <h2 class="mb-4 text-lg font-semibold text-[#1d1d1f]">إعدادات الوقت والحجم</h2>
          <div class="space-y-6">
            <!-- Support Claim TTL -->
            <div class="flex items-end justify-between rounded-lg border border-[#d2d2d7] p-4">
              <div class="flex-1">
                <label class="block text-sm font-medium text-[#1d1d1f]">مدة صلاحية المطالبة (دقيقة)</label>
                <p class="text-xs text-[#6e6e73]">النطاق: 5-60 دقيقة</p>
                <div class="mt-2 text-lg font-semibold text-[#1d1d1f]">{{ settings.support_claim_ttl }}</div>
              </div>
              <div class="ml-4 flex gap-2">
                <button
                  @click="updateValue('support_claim_ttl', Math.max(5, settings.support_claim_ttl - 1))"
                  :disabled="loading || settings.support_claim_ttl <= 5"
                  class="rounded bg-[#f5f5f7] px-3 py-1 text-sm hover:bg-[#e5e5e7] disabled:opacity-50"
                >
                  −
                </button>
                <button
                  @click="updateValue('support_claim_ttl', Math.min(60, settings.support_claim_ttl + 1))"
                  :disabled="loading || settings.support_claim_ttl >= 60"
                  class="rounded bg-[#f5f5f7] px-3 py-1 text-sm hover:bg-[#e5e5e7] disabled:opacity-50"
                >
                  +
                </button>
              </div>
            </div>

            <!-- Voting Session Timeout -->
            <div class="flex items-end justify-between rounded-lg border border-[#d2d2d7] p-4">
              <div class="flex-1">
                <label class="block text-sm font-medium text-[#1d1d1f]">انتظار جلسة التصويت (دقيقة)</label>
                <p class="text-xs text-[#6e6e73]">النطاق: 15-120 دقيقة</p>
                <div class="mt-2 text-lg font-semibold text-[#1d1d1f]">{{ settings.voting_session_timeout }}</div>
              </div>
              <div class="ml-4 flex gap-2">
                <button
                  @click="updateValue('voting_session_timeout', Math.max(15, settings.voting_session_timeout - 1))"
                  :disabled="loading || settings.voting_session_timeout <= 15"
                  class="rounded bg-[#f5f5f7] px-3 py-1 text-sm hover:bg-[#e5e5e7] disabled:opacity-50"
                >
                  −
                </button>
                <button
                  @click="updateValue('voting_session_timeout', Math.min(120, settings.voting_session_timeout + 1))"
                  :disabled="loading || settings.voting_session_timeout >= 120"
                  class="rounded bg-[#f5f5f7] px-3 py-1 text-sm hover:bg-[#e5e5e7] disabled:opacity-50"
                >
                  +
                </button>
              </div>
            </div>

            <!-- PDF Upload Size Limit -->
            <div class="flex items-end justify-between rounded-lg border border-[#d2d2d7] p-4">
              <div class="flex-1">
                <label class="block text-sm font-medium text-[#1d1d1f]">حد رفع PDF (MB)</label>
                <p class="text-xs text-[#6e6e73]">النطاق: 1-50 MB</p>
                <div class="mt-2 text-lg font-semibold text-[#1d1d1f]">{{ settings.pdf_upload_size_limit }}</div>
              </div>
              <div class="ml-4 flex gap-2">
                <button
                  @click="updateValue('pdf_upload_size_limit', Math.max(1, settings.pdf_upload_size_limit - 1))"
                  :disabled="loading || settings.pdf_upload_size_limit <= 1"
                  class="rounded bg-[#f5f5f7] px-3 py-1 text-sm hover:bg-[#e5e5e7] disabled:opacity-50"
                >
                  −
                </button>
                <button
                  @click="updateValue('pdf_upload_size_limit', Math.min(50, settings.pdf_upload_size_limit + 1))"
                  :disabled="loading || settings.pdf_upload_size_limit >= 50"
                  class="rounded bg-[#f5f5f7] px-3 py-1 text-sm hover:bg-[#e5e5e7] disabled:opacity-50"
                >
                  +
                </button>
              </div>
            </div>

            <!-- Login Lockout Duration -->
            <div class="flex items-end justify-between rounded-lg border border-[#d2d2d7] p-4">
              <div class="flex-1">
                <label class="block text-sm font-medium text-[#1d1d1f]">مدة حظر الدخول (دقيقة)</label>
                <p class="text-xs text-[#6e6e73]">النطاق: 5-60 دقيقة</p>
                <div class="mt-2 text-lg font-semibold text-[#1d1d1f]">{{ settings.login_lockout_duration }}</div>
              </div>
              <div class="ml-4 flex gap-2">
                <button
                  @click="updateValue('login_lockout_duration', Math.max(5, settings.login_lockout_duration - 1))"
                  :disabled="loading || settings.login_lockout_duration <= 5"
                  class="rounded bg-[#f5f5f7] px-3 py-1 text-sm hover:bg-[#e5e5e7] disabled:opacity-50"
                >
                  −
                </button>
                <button
                  @click="updateValue('login_lockout_duration', Math.min(60, settings.login_lockout_duration + 1))"
                  :disabled="loading || settings.login_lockout_duration >= 60"
                  class="rounded bg-[#f5f5f7] px-3 py-1 text-sm hover:bg-[#e5e5e7] disabled:opacity-50"
                >
                  +
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Feature Toggles -->
        <div class="rounded-lg bg-white p-6 shadow-sm">
          <h2 class="mb-4 text-lg font-semibold text-[#1d1d1f]">تفعيل الميزات</h2>
          <div class="space-y-4">
            <label class="flex items-center rounded-lg border border-[#d2d2d7] p-4">
              <input
                type="checkbox"
                :checked="settings.notifications_phase_1_enabled"
                @change="updateValue('notifications_phase_1_enabled', !settings.notifications_phase_1_enabled)"
                :disabled="loading"
                class="h-4 w-4"
              />
              <span class="mr-3 text-sm font-medium text-[#1d1d1f]">تفعيل الإشعارات (المرحلة الأولى)</span>
            </label>
            <label class="flex items-center rounded-lg border border-[#d2d2d7] p-4">
              <input
                type="checkbox"
                :checked="settings.search_phase_1_enabled"
                @change="updateValue('search_phase_1_enabled', !settings.search_phase_1_enabled)"
                :disabled="loading"
                class="h-4 w-4"
              />
              <span class="mr-3 text-sm font-medium text-[#1d1d1f]">تفعيل البحث (المرحلة الأولى)</span>
            </label>
            <label class="flex items-center rounded-lg border border-[#d2d2d7] p-4">
              <input
                type="checkbox"
                :checked="settings.customs_print_preview_enabled"
                @change="updateValue('customs_print_preview_enabled', !settings.customs_print_preview_enabled)"
                :disabled="loading"
                class="h-4 w-4"
              />
              <span class="mr-3 text-sm font-medium text-[#1d1d1f]">معاينة الطباعة للتصريحات الجمركية</span>
            </label>
          </div>
        </div>

        <!-- Error -->
        <div v-if="error" class="rounded-lg bg-[#fff5f5] p-4">
          <p class="text-sm text-[#ff3b30]">{{ error }}</p>
        </div>
      </div>

      <!-- Forbidden -->
      <div v-else-if="forbidden" class="rounded-lg bg-white p-8">
        <p class="text-center text-[#ff3b30]">لا توجد صلاحيات كافية للوصول إلى هذه الصفحة</p>
      </div>

      <!-- Error State -->
      <div v-else class="rounded-lg bg-white p-8">
        <p class="text-center text-[#ff3b30]">{{ error }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAdminSettings } from '../../composables/useAdminSettings'
import { useAuthStore } from '../../stores/auth.store'
import { useRouter } from 'nuxt/app'

definePageMeta({
  middleware: 'auth',
})

const router = useRouter()
const auth = useAuthStore()

// Check admin access
if (!auth.isCbyAdmin) {
  router.push('/dashboard')
}

const { settings, loading, error, fetchSettings, updateSetting } = useAdminSettings()
const forbidden = ref(false)

const updateValue = async (key: string, value: any) => {
  const success = await updateSetting(key, value)
  if (!success) {
    // Error is shown in the component
  }
}

onMounted(async () => {
  if (!auth.isCbyAdmin) {
    forbidden.value = true
    return
  }
  await fetchSettings()
})
</script>
