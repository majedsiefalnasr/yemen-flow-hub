<script setup lang="ts">
import { ref } from 'vue'
import { Search, FileText, Building2, Users, BarChart3, Settings, Bell } from 'lucide-vue-next'
import { useMagicKeys, whenever } from '@vueuse/core'
import { useAuthStore } from '@/stores/auth.store'
import { UserRole } from '@/types/enums'
import { Button } from '@/components/ui/button'
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
  <Button
    variant="ghost"
    size="sm"
    class="h-7 gap-1.5 rounded-md px-2 text-muted-foreground hover:text-foreground"
    aria-label="بحث ⌘K"
    @click="open = true"
  >
    <Search class="h-4 w-4" />
    <span class="hidden text-sm sm:inline">ابحث...</span>
    <div class="hidden items-center gap-0.5 sm:flex">
      <Kbd class="h-5 px-1 text-[10px]">⌘</Kbd>
      <Kbd class="h-5 px-1 text-[10px]">K</Kbd>
    </div>
  </Button>

  <Dialog v-model:open="open">
    <DialogContent class="overflow-hidden p-0 shadow-lg">
      <Command class="[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:text-muted-foreground [&_[cmdk-group-heading]]:text-xs [&_[cmdk-input-wrapper]_svg]:h-5 [&_[cmdk-input-wrapper]_svg]:w-5 [&_[cmdk-input]]:h-12 [&_[cmdk-item]]:px-2 [&_[cmdk-item]]:py-3 [&_[cmdk-item]_svg]:h-5 [&_[cmdk-item]_svg]:w-5">
        <CommandInput placeholder="ابحث أو اختر إجراء..." />
        <CommandList>
          <CommandEmpty>لم يتم العثور على نتائج.</CommandEmpty>
          <CommandGroup heading="الإجراءات السريعة">
            <CommandItem
              v-for="action in quickActions"
              :key="action.url"
              :value="action.title"
              @select="handleSelect(action.url)"
            >
              <component :is="action.icon" class="h-4 w-4" />
              <span>{{ action.title }}</span>
              <CommandShortcut>⌘{{ action.shortcut }}</CommandShortcut>
            </CommandItem>
          </CommandGroup>
        </CommandList>
      </Command>
    </DialogContent>
  </Dialog>
</template>
