<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { DashboardIcon, WalletCardsIcon, FileTextIcon, BarChartIcon, UsersIcon, CogIcon, LogOutIcon } from 'lucide-vue-next'
import { useAuthStore } from '../../stores/auth.store'
import { useSidebar } from '../../composables/useSidebar'
import { NAV_ITEMS, ROLE_LABELS } from '../../constants/workflow'

const props = defineProps<{
  mobileOpen: boolean
}>()

const emit = defineEmits<{
  closeMobile: []
}>()

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { isCollapsed, toggle } = useSidebar()

const visibleNavItems = computed(() => {
  if (!auth.user) return []
  return NAV_ITEMS.filter(item => item.roles.includes(auth.user!.role))
})

function isActive(itemRoute: string): boolean {
  return route.path === itemRoute || route.path.startsWith(itemRoute + '/')
}

async function handleLogout() {
  await auth.logout()
  await router.push('/login')
}
</script>

<template>
  <!-- Mobile overlay -->
  <div
    v-if="props.mobileOpen"
    class="fixed inset-0 z-40 bg-black/40"
    aria-hidden="true"
    @click="emit('closeMobile')"
  />

  <!-- Sidebar -->
  <aside
    class="fixed top-0 bottom-0 end-0 z-50 flex flex-col border-s border-gray-300 bg-white overflow-y-auto overflow-x-hidden transition-all duration-200"
    :class="{
      'w-[280px]': !isCollapsed,
      'w-[72px]': isCollapsed,
      'translate-x-full max-md:translate-x-0 max-md:w-[280px]': !props.mobileOpen && true,
    }"
    aria-label="القائمة الرئيسية"
  >
    <!-- Brand -->
    <div class="flex items-center gap-2.5 px-4 py-5 overflow-hidden whitespace-nowrap">
      <div class="grid place-items-center w-10 h-10 rounded-md bg-blue-600 text-white text-sm font-bold flex-shrink-0">
        ب م
      </div>
      <div v-if="!isCollapsed" class="flex flex-col gap-0.5 min-w-0">
        <div class="text-sm font-bold text-gray-900">منصة الواردات</div>
        <div class="text-xs text-gray-400">البنك المركزي اليمني</div>
      </div>
    </div>

    <div class="h-px bg-gray-300 mx-4 my-1" />

    <!-- Navigation -->
    <nav class="flex-1 px-2 py-2 flex flex-col gap-0.5" aria-label="روابط التنقل">
      <NuxtLink
        v-for="item in visibleNavItems"
        :key="item.route"
        :to="item.route"
        class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-normal text-gray-900 no-underline transition-colors duration-120 overflow-hidden whitespace-nowrap"
        :class="isActive(item.route) ? 'bg-blue-600 text-white font-medium' : 'hover:bg-blue-50'"
        :title="isCollapsed ? item.label : undefined"
        @click="emit('closeMobile')"
      >
        <component
          :is="item.icon"
          class="w-5 h-5 flex-shrink-0"
          aria-hidden="true"
        />
        <span v-if="!isCollapsed" class="flex-1 text-start">{{ item.label }}</span>
      </NuxtLink>
    </nav>

    <!-- Footer -->
    <div class="px-2 py-2">
      <div class="h-px bg-gray-300 mx-2 my-1" />
      <div
        class="flex items-center px-2 py-2.5 overflow-hidden"
        :class="isCollapsed ? 'justify-center' : 'gap-2.5'"
      >
        <div class="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-semibold flex-shrink-0">
          {{ auth.user?.name?.charAt(0) ?? '؟' }}
        </div>
        <div v-if="!isCollapsed" class="flex flex-col gap-0.5 min-w-0">
          <div class="text-sm font-medium text-gray-900 truncate overflow-hidden text-ellipsis">{{ auth.user?.name }}</div>
          <div class="inline-block text-xs font-medium text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded-full">
            {{ auth.user ? (ROLE_LABELS[auth.user.role] ?? auth.user.role) : '' }}
          </div>
        </div>
      </div>
      <button
        class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-transparent text-gray-600 text-sm transition-colors duration-120 hover:bg-blue-50 hover:text-red-700 mt-1"
        :class="{ 'hidden': isCollapsed }"
        @click="handleLogout"
      >
        تسجيل الخروج
      </button>
      <button
        class="w-full px-3 py-2.5 rounded-lg border border-gray-300 bg-transparent text-gray-900 text-sm transition-colors duration-120 hover:bg-blue-50 mt-1"
        :class="{ 'hidden max-md:block': isCollapsed }"
        @click="toggle"
      >
        {{ isCollapsed ? 'توسيع ›' : '‹ طي الشريط الجانبي' }}
      </button>
    </div>
  </aside>
</template>
