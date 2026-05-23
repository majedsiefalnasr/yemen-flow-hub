<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next'
import { useRoute } from 'vue-router'
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'

interface NavItem {
  to: string
  label: string
  icon: LucideIcon
  roles?: string[]
}

defineProps<{
  items: NavItem[]
}>()

const route = useRoute()

function isActive(path: string): boolean {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}
</script>

<template>
  <SidebarMenu class="space-y-1">
    <SidebarMenuItem v-for="item in items" :key="item.to">
      <SidebarMenuButton
        as-child
        :is-active="isActive(item.to)"
        class="group/menu-button flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-all"
      >
        <NuxtLink
          :to="item.to"
          class="flex w-full items-center gap-3"
        >
          <component
            :is="item.icon"
            class="h-5 w-5 shrink-0"
          />
          <span class="truncate group-data-[collapsible=icon]/sidebar-wrapper:hidden">
            {{ item.label }}
          </span>
        </NuxtLink>
      </SidebarMenuButton>
    </SidebarMenuItem>
  </SidebarMenu>
</template>
