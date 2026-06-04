<script setup lang="ts">
import AppSidebar from '@/components/AppSidebar.vue'
import GlobalTopbar from '@/components/layout/GlobalTopbar.vue'
import { useAuthStore } from '@/stores/auth.store'
import { useThemingStore } from '@/stores/theming.store'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'

const authStore = useAuthStore()
const themingStore = useThemingStore()
const user = computed(() => authStore.user)

const insetRef = ref<{ $el: HTMLElement } | null>(null)
const route = useRoute()

watch(
  () => route.path,
  () => {
    insetRef.value?.$el?.scrollTo({ top: 0 })
  },
)
</script>

<template>
  <div v-if="user" class="bg-background text-foreground min-h-dvh">
    <SidebarProvider class="flex-row">
      <AppSidebar
        side="right"
        :variant="themingStore.sidebarVariant"
        :collapsible="themingStore.sidebarCollapsible"
      />
      <SidebarInset ref="insetRef">
        <GlobalTopbar />

        <main class="flex min-w-0 flex-1 flex-col p-4 py-12">
          <div
            :class="[
              'content-layout w-full min-w-0',
              themingStore.layout === 'boxed' ? 'layout-boxed' : 'layout-full',
            ]"
          >
            <slot />
          </div>
        </main>

        <!-- Footer -->
        <!-- <footer class="flex flex-wrap items-center justify-between gap-2 border-t text-[10px] text-muted-foreground sm:px-6 sm:text-xs">
          <div>© 2025 البنك المركزي اليمني</div>
          <div class="flex shrink-0 items-center gap-2 text-[var(--color-text-warning)] dark:text-amber-400">
            <AlertTriangle class="h-3.5 w-3.5" />
            بيئة عرض توضيحي
          </div>
        </footer> -->
      </SidebarInset>
    </SidebarProvider>
  </div>
</template>
