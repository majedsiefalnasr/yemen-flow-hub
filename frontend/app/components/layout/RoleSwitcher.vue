<script setup lang="ts">
import { ref } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole } from '../../types/enums'
import { Button } from '../ui/button'

const DEMO_ROLE_LABELS: Record<UserRole, string> = {
  [UserRole.DATA_ENTRY]: 'إدخال بيانات',
  [UserRole.BANK_REVIEWER]: 'مراجع بنك',
  [UserRole.BANK_ADMIN]: 'مدير بنك',
  [UserRole.SWIFT_OFFICER]: 'ضابط SWIFT',
  [UserRole.SUPPORT_COMMITTEE]: 'لجنة الدعم',
  [UserRole.EXECUTIVE_MEMBER]: 'عضو تنفيذي',
  [UserRole.COMMITTEE_DIRECTOR]: 'مدير اللجنة',
  [UserRole.CBY_ADMIN]: 'مدير CBY',
}

const auth = useAuthStore()
const router = useRouter()
const roleOptions = Object.entries(DEMO_ROLE_LABELS) as Array<[UserRole, string]>

const open = ref(false)
const switching = ref(false)
const error = ref<string | null>(null)

async function switchRole(role: UserRole) {
  switching.value = true
  error.value = null
  try {
    await auth.switchDemoRole(role)
    open.value = false
    await router.push('/dashboard')
  } catch {
    error.value = 'تعذر تبديل الدور. تأكد من تشغيل الخادم.'
  } finally {
    switching.value = false
  }
}
</script>

<template>
  <div class="relative">
    <Button
      variant="outline"
      size="sm"
      :aria-expanded="open"
      aria-haspopup="listbox"
      aria-label="تبديل الدور"
      class="gap-1.5"
      @click="open = !open"
    >
      <span>تبديل الدور</span>
      <ChevronDown :size="14" :class="{ 'rotate-180': open }" class="transition-transform" />
    </Button>

    <div
      v-if="open"
      class="absolute top-full start-0 z-50 mt-1 min-w-40 rounded-md border border-border bg-white p-1 shadow-md"
      role="listbox"
      aria-label="اختر الدور"
    >
      <p v-if="error" class="px-3 py-2 text-xs text-destructive">{{ error }}</p>
      <button
        v-for="([roleKey, label]) in roleOptions"
        :key="roleKey"
        class="w-full rounded px-3 py-2 text-start text-sm text-foreground hover:bg-muted disabled:cursor-wait disabled:opacity-50"
        :disabled="switching"
        role="option"
        @click="switchRole(roleKey)"
      >
        {{ label }}
      </button>
    </div>

    <div v-if="open" class="fixed inset-0 z-40" aria-hidden="true" @click="open = false" />
  </div>
</template>
