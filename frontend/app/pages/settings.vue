<template>
  <div class="min-h-screen bg-[#f5f5f7] p-6">
    <div class="mx-auto max-w-2xl">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-[#1d1d1f]">الإعدادات</h1>
        <p class="text-[#6e6e73]">إدارة تفضيلات حسابك</p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="rounded-lg bg-white p-8 shadow-sm">
        <div class="flex items-center justify-center">
          <div class="h-8 w-8 animate-spin rounded-full border-4 border-[#d2d2d7] border-t-[#0071e3]"></div>
          <span class="mr-3 text-[#6e6e73]">جاري التحميل...</span>
        </div>
      </div>

      <!-- Settings Form -->
      <div v-else-if="preferences" class="space-y-6">
        <form @submit.prevent="handleSave" class="rounded-lg bg-white p-6 shadow-sm">
          <!-- Language -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-[#1d1d1f]">اللغة</label>
            <select
              v-model="formData.language"
              class="mt-2 w-full rounded-lg border border-[#d2d2d7] px-3 py-2 text-sm focus:border-[#0071e3] focus:outline-none"
            >
              <option value="ar">العربية</option>
              <option value="en">English</option>
            </select>
          </div>

          <!-- Dashboard View -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-[#1d1d1f]">عرض لوحة التحكم</label>
            <div class="mt-3 space-y-2">
              <label class="flex items-center">
                <input
                  v-model="formData.dashboard_view"
                  type="radio"
                  value="compact"
                  class="h-4 w-4"
                />
                <span class="mr-2 text-sm text-[#1d1d1f]">مضغوط</span>
              </label>
              <label class="flex items-center">
                <input
                  v-model="formData.dashboard_view"
                  type="radio"
                  value="normal"
                  class="h-4 w-4"
                />
                <span class="mr-2 text-sm text-[#1d1d1f]">عادي</span>
              </label>
              <label class="flex items-center">
                <input
                  v-model="formData.dashboard_view"
                  type="radio"
                  value="expanded"
                  class="h-4 w-4"
                />
                <span class="mr-2 text-sm text-[#1d1d1f]">موسع</span>
              </label>
            </div>
          </div>

          <!-- Table Density -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-[#1d1d1f]">كثافة الجدول</label>
            <div class="mt-3 space-y-2">
              <label class="flex items-center">
                <input
                  v-model="formData.table_density"
                  type="radio"
                  value="compact"
                  class="h-4 w-4"
                />
                <span class="mr-2 text-sm text-[#1d1d1f]">مضغوط</span>
              </label>
              <label class="flex items-center">
                <input
                  v-model="formData.table_density"
                  type="radio"
                  value="normal"
                  class="h-4 w-4"
                />
                <span class="mr-2 text-sm text-[#1d1d1f]">عادي</span>
              </label>
              <label class="flex items-center">
                <input
                  v-model="formData.table_density"
                  type="radio"
                  value="comfortable"
                  class="h-4 w-4"
                />
                <span class="mr-2 text-sm text-[#1d1d1f]">مريح</span>
              </label>
            </div>
          </div>

          <!-- Page Size -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-[#1d1d1f]">حجم الصفحة</label>
            <select
              v-model.number="formData.page_size"
              class="mt-2 w-full rounded-lg border border-[#d2d2d7] px-3 py-2 text-sm focus:border-[#0071e3] focus:outline-none"
            >
              <option value="10">10 صفوف</option>
              <option value="25">25 صف</option>
              <option value="50">50 صف</option>
              <option value="100">100 صف</option>
            </select>
          </div>

          <!-- Error -->
          <div v-if="error" class="mb-6 rounded-lg bg-[#fff5f5] p-3">
            <p class="text-sm text-[#ff3b30]">{{ error }}</p>
          </div>

          <!-- Actions -->
          <div class="flex gap-3">
            <button
              type="submit"
              :disabled="loading"
              class="flex-1 rounded-lg bg-[#0071e3] py-2 text-sm font-medium text-white hover:bg-blue-600 disabled:opacity-50"
            >
              {{ loading ? 'جاري الحفظ...' : 'حفظ التغييرات' }}
            </button>
            <button
              type="button"
              @click="handleReset"
              :disabled="loading"
              class="flex-1 rounded-lg border border-[#d2d2d7] py-2 text-sm font-medium text-[#1d1d1f] hover:bg-[#f5f5f7] disabled:opacity-50"
            >
              إعادة تعيين للافتراضيات
            </button>
          </div>
        </form>
      </div>

      <!-- Error State -->
      <div v-else class="rounded-lg bg-white p-8">
        <p class="text-center text-[#ff3b30]">{{ error }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useSettings } from '../composables/useSettings'

definePageMeta({
  middleware: 'auth',
})

const { preferences, loading, error, fetchSettings, updateSettings, resetSettings } = useSettings()

const formData = ref({
  language: 'ar',
  dashboard_view: 'normal',
  table_density: 'normal',
  page_size: 25,
})

const handleSave = async () => {
  const success = await updateSettings({
    language: formData.value.language,
    dashboard_view: formData.value.dashboard_view,
    table_density: formData.value.table_density,
    page_size: formData.value.page_size,
  })

  if (success) {
    // Show success toast here
    // useToast().success('تم حفظ الإعدادات بنجاح')
  }
}

const handleReset = async () => {
  if (!confirm('هل أنت متأكد من رغبتك في إعادة تعيين الإعدادات للافتراضيات؟')) {
    return
  }

  const success = await resetSettings()

  if (success && preferences.value) {
    formData.value = {
      language: preferences.value.language,
      dashboard_view: preferences.value.dashboard_view,
      table_density: preferences.value.table_density,
      page_size: preferences.value.page_size,
    }
    // Show success toast here
    // useToast().success('تم إعادة تعيين الإعدادات بنجاح')
  }
}

onMounted(() => {
  fetchSettings()

  // Watch preferences and update form
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
      }
    }
  )
})
</script>
