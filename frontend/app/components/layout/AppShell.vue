<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next'
import AppSidebar from '@/components/AppSidebar.vue'
import { useAuthStore } from '@/stores/auth.store'
import {
  SidebarInset,
  SidebarProvider,
} from '@/components/ui/sidebar'

const authStore = useAuthStore()
const user = computed(() => authStore.user)
</script>

<template>
  <div
    v-if="user"
    class="min-h-screen bg-background text-foreground"
    dir="rtl"
  >
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset class="flex flex-col">
        <!-- Main Content -->
        <main class="flex-1 w-full overflow-auto">
          <slot />
        </main>

        <!-- Footer -->
        <footer class="flex flex-wrap items-center justify-between gap-2 border-t px-4 py-3 text-[10px] text-muted-foreground sm:px-6 sm:text-xs">
          <div>© 2025 البنك المركزي اليمني</div>
          <div class="flex shrink-0 items-center gap-2">
            <AlertTriangle class="h-3.5 w-3.5" />
            بيئة عرض توضيحي
          </div>
        </footer>
      </SidebarInset>
    </SidebarProvider>
  </div>
</template>
