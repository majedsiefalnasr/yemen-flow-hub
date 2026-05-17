<template>
  <div class="min-h-screen bg-[#f5f5f7] p-6">
    <div class="mx-auto max-w-2xl">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-[#1d1d1f]">الملف الشخصي</h1>
        <p class="text-[#6e6e73]">عرض وإدارة بيانات حسابك</p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="rounded-lg bg-white p-8 shadow-sm">
        <div class="flex items-center justify-center">
          <div class="h-8 w-8 animate-spin rounded-full border-4 border-[#d2d2d7] border-t-[#0071e3]"></div>
          <span class="mr-3 text-[#6e6e73]">جاري التحميل...</span>
        </div>
      </div>

      <!-- Profile Section -->
      <div v-else-if="profile" class="space-y-6">
        <!-- User Info Card -->
        <div class="rounded-lg bg-white p-6 shadow-sm">
          <h2 class="mb-4 text-lg font-semibold text-[#1d1d1f]">معلومات المستخدم</h2>
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <p class="text-sm text-[#6e6e73]">الاسم</p>
              <p class="text-[#1d1d1f]">{{ profile.name }}</p>
            </div>
            <div>
              <p class="text-sm text-[#6e6e73]">البريد الإلكتروني</p>
              <p class="text-[#1d1d1f]">{{ profile.email }}</p>
            </div>
            <div>
              <p class="text-sm text-[#6e6e73]">الدور</p>
              <p class="text-[#1d1d1f]">{{ roleLabel }}</p>
            </div>
            <div v-if="profile.bank_name_ar">
              <p class="text-sm text-[#6e6e73]">البنك</p>
              <p class="text-[#1d1d1f]">{{ profile.bank_name_ar }}</p>
            </div>
          </div>
        </div>

        <!-- Change Password Card -->
        <div class="rounded-lg bg-white p-6 shadow-sm">
          <h2 class="mb-4 text-lg font-semibold text-[#1d1d1f]">تغيير كلمة المرور</h2>
          <form @submit.prevent="handleChangePassword" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-[#1d1d1f]">كلمة المرور الحالية</label>
              <input
                v-model="passwordForm.current_password"
                type="password"
                class="mt-1 w-full rounded-lg border border-[#d2d2d7] px-3 py-2 text-sm focus:border-[#0071e3] focus:outline-none"
                required
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-[#1d1d1f]">كلمة المرور الجديدة</label>
              <input
                v-model="passwordForm.password"
                type="password"
                class="mt-1 w-full rounded-lg border border-[#d2d2d7] px-3 py-2 text-sm focus:border-[#0071e3] focus:outline-none"
                placeholder="8+ أحرف، مع أحرف كبيرة وصغيرة وأرقام"
                required
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-[#1d1d1f]">تأكيد كلمة المرور</label>
              <input
                v-model="passwordForm.password_confirmation"
                type="password"
                class="mt-1 w-full rounded-lg border border-[#d2d2d7] px-3 py-2 text-sm focus:border-[#0071e3] focus:outline-none"
                required
              />
            </div>

            <!-- Password Error -->
            <div v-if="passwordError" class="rounded-lg bg-[#fff5f5] p-3">
              <p class="text-sm text-[#ff3b30]">{{ passwordError }}</p>
            </div>

            <!-- Submit Button -->
            <button
              type="submit"
              :disabled="passwordLoading"
              class="w-full rounded-lg bg-[#0071e3] py-2 text-sm font-medium text-white hover:bg-blue-600 disabled:opacity-50"
            >
              {{ passwordLoading ? 'جاري التحديث...' : 'تغيير كلمة المرور' }}
            </button>
          </form>
        </div>
      </div>

      <!-- Error State -->
      <div v-else class="rounded-lg bg-white p-8">
        <p class="text-center text-[#ff3b30]">{{ error }}</p>
      </div>
    </div>
  </div>
</template>

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

const roleLabel = computed(() => {
  if (!profile.value) return ''
  return ROLE_LABELS[profile.value.role] || profile.value.role
})

const handleChangePassword = async () => {
  passwordError.value = null
  passwordLoading.value = true

  const success = await changePassword(passwordForm.value)

  if (success) {
    passwordForm.value = {
      current_password: '',
      password: '',
      password_confirmation: '',
    }
    // Show success toast here
    // useToast().success('تم تغيير كلمة المرور بنجاح')
  }
  else {
    passwordError.value = error.value || 'فشل تغيير كلمة المرور'
  }

  passwordLoading.value = false
}

onMounted(() => {
  fetchProfile()
})
</script>
