<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole } from '../../types/enums'
import Icon from '../shared/Icon.vue'

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
  <div class="role-switcher">
    <button
      class="switcher-trigger"
      :aria-expanded="open"
      aria-haspopup="listbox"
      aria-label="تبديل الدور"
      @click="open = !open"
    >
      <span class="switcher-label">تبديل الدور</span>
      <Icon name="chevron-down" :size="14" :class="{ 'rotate-180': open }" />
    </button>

    <div v-if="open" class="switcher-dropdown" role="listbox" aria-label="اختر الدور">
      <p v-if="error" class="switcher-error">{{ error }}</p>
      <button
        v-for="([roleKey, label]) in roleOptions"
        :key="roleKey"
        class="switcher-option"
        :disabled="switching"
        role="option"
        @click="switchRole(roleKey)"
      >
        {{ label }}
      </button>
    </div>

    <!-- Close on outside click -->
    <div v-if="open" class="switcher-backdrop" aria-hidden="true" @click="open = false" />
  </div>
</template>

<style scoped>
.role-switcher {
  position: relative;
}

.switcher-trigger {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 10px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  background: transparent;
  color: var(--color-text-secondary);
  font-size: 13px;
  font-family: var(--font-body);
  cursor: pointer;
  transition: background-color 120ms ease;
  white-space: nowrap;
}

.switcher-trigger:hover {
  background-color: var(--color-surface-dim);
}

.switcher-label {
  font-size: 13px;
}

.switcher-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  inset-inline-start: 0;
  min-width: 160px;
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  box-shadow: var(--shadow-md);
  z-index: 100;
  padding: 4px;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.switcher-option {
  width: 100%;
  padding: 8px 12px;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: var(--color-text-primary);
  font-size: 13px;
  font-family: var(--font-body);
  text-align: start;
  cursor: pointer;
  transition: background-color 120ms ease;
}

.switcher-option:hover:not(:disabled) {
  background-color: var(--color-surface-dim);
}

.switcher-option:disabled {
  opacity: 0.5;
  cursor: wait;
}

.switcher-error {
  font-size: 12px;
  color: var(--color-error-text);
  padding: 4px 12px;
  margin: 0;
}

.switcher-backdrop {
  position: fixed;
  inset: 0;
  z-index: 99;
}

.rotate-180 {
  transform: rotate(180deg);
}
</style>
