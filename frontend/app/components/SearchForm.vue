<script setup lang="ts">
import { ref } from 'vue'
import { Search, FileText, Building2, Users, BarChart3, Settings, Bell } from 'lucide-vue-next'
import { useMagicKeys, whenever } from '@vueuse/core'
import { useAuthStore } from '@/stores/auth.store'
import { UserRole } from '@/types/enums'
import {
  SidebarGroup,
  SidebarGroupContent,
} from '@/components/ui/sidebar'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Dialog,
  DialogContent,
} from '@/components/ui/dialog'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
  CommandShortcut,
} from '@/components/ui/command'
import { Kbd } from '@/components/ui/kbd'

const open = ref(false)
const authStore = useAuthStore()
const router = useRouter()

const { meta_k } = useMagicKeys()

whenever(meta_k, () => {
  open.value = true
})

const quickActions = computed(() => {
  const user = authStore.user
  const actions = [
    {
      title: 'طلبات التمويل',
      url: '/requests',
      icon: FileText,
      roles: [
        UserRole.DATA_ENTRY,
        UserRole.BANK_REVIEWER,
        UserRole.BANK_ADMIN,
        UserRole.SUPPORT_COMMITTEE,
        UserRole.SWIFT_OFFICER,
        UserRole.EXECUTIVE_MEMBER,
        UserRole.COMMITTEE_DIRECTOR,
        UserRole.CBY_ADMIN,
      ],
      shortcut: 'R',
    },
    {
      title: 'تقديم طلب جديد',
      url: '/requests/new',
      icon: FileText,
      roles: [UserRole.DATA_ENTRY, UserRole.BANK_ADMIN],
      shortcut: 'N',
    },
    {
      title: 'إدارة التجار',
      url: '/merchants',
      icon: Building2,
      roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR],
      shortcut: 'M',
    },
    {
      title: 'إدارة البنوك',
      url: '/admin/entities',
      icon: Building2,
      roles: [UserRole.CBY_ADMIN],
      shortcut: 'B',
    },
    {
      title: 'مستخدمي النظام',
      url: '/admin/cby-staff',
      icon: Users,
      roles: [UserRole.CBY_ADMIN],
      shortcut: 'U',
    },
    {
      title: 'التقارير والتحليلات',
      url: '/reports',
      icon: BarChart3,
      roles: [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR, UserRole.BANK_ADMIN],
      shortcut: 'T',
    },
    {
      title: 'الإشعارات',
      url: '/notifications',
      icon: Bell,
      roles: [
        UserRole.DATA_ENTRY,
        UserRole.BANK_REVIEWER,
        UserRole.BANK_ADMIN,
        UserRole.SUPPORT_COMMITTEE,
        UserRole.SWIFT_OFFICER,
        UserRole.EXECUTIVE_MEMBER,
        UserRole.COMMITTEE_DIRECTOR,
        UserRole.CBY_ADMIN,
      ],
      shortcut: 'I',
    },
    {
      title: 'الإعدادات',
      url: '/settings',
      icon: Settings,
      roles: [
        UserRole.DATA_ENTRY,
        UserRole.BANK_REVIEWER,
        UserRole.BANK_ADMIN,
        UserRole.SUPPORT_COMMITTEE,
        UserRole.SWIFT_OFFICER,
        UserRole.EXECUTIVE_MEMBER,
        UserRole.COMMITTEE_DIRECTOR,
        UserRole.CBY_ADMIN,
      ],
      shortcut: 'S',
    },
  ]

  if (!user) return []
  return actions.filter(action => action.roles.includes(user.role))
})

function navigateTo(path: string) {
  router.push(path)
  open.value = false
}

function handleSelect(path: string) {
  navigateTo(path)
}
</script>

<template>
  <SidebarGroup class="py-0">
    <SidebarGroupContent class="relative">
      <Button
        variant="outline"
        class="w-full justify-start rounded-lg border-muted-foreground/20 text-sm text-muted-foreground"
        @click="open = true"
      >
        <Search class="h-4 w-4" />
        <span class="hidden flex-1 text-start md:inline-flex">ابحث...</span>
        <div class="flex items-center gap-1">
          <Kbd>⌘</Kbd>
          <Kbd>K</Kbd>
        </div>
      </Button>
    </SidebarGroupContent>
  </SidebarGroup>

  <Dialog v-model:open="open">
    <DialogContent class="overflow-hidden p-0 shadow-lg">
      <Command class="[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:text-muted-foreground [&_[cmdk-group-heading]]:text-xs [&_[cmdk-group]:overflow-hidden [&_[cmdk-group]>:first-child]:border-t [&_[cmdk-input-wrapper]_svg]:h-5 [&_[cmdk-input-wrapper]_svg]:w-5 [&_[cmdk-input]]:h-12 [&_[cmdk-item]]:px-2 [&_[cmdk-item]]:py-3 [&_[cmdk-item]_svg]:h-5 [&_[cmdk-item]_svg]:w-5">
        <CommandInput placeholder="ابحث أو اختر إجراء..." />
        <CommandList>
          <CommandEmpty>لم يتم العثور على نتائج.</CommandEmpty>
          <CommandGroup heading="الإجراءات السريعة">
            <CommandItem
              v-for="action in quickActions"
              :key="action.url"
              value={action.title}
              @select="handleSelect(action.url)"
            >
              <component :is="action.icon" class="mr-2 h-4 w-4" />
              <span>{{ action.title }}</span>
              <CommandShortcut>⌘{{ action.shortcut }}</CommandShortcut>
            </CommandItem>
          </CommandGroup>
        </CommandList>
      </Command>
    </DialogContent>
  </Dialog>
</template>
