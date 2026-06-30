<script setup lang="ts">
import { ref } from 'vue'
import { Search } from 'lucide-vue-next'
import { useMagicKeys, whenever } from '@vueuse/core'
import { useAuthStore } from '@/stores/auth.store'
import { NAV_ITEMS } from '@/constants/workflow'
import { ICONS } from '@/utils/icon-map'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent } from '@/components/ui/dialog'
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

const { meta_k, ctrl_k } = useMagicKeys()
const commandShortcutPressed = computed(() => Boolean(meta_k?.value || ctrl_k?.value))

whenever(commandShortcutPressed, () => {
  open.value = true
})

const shortcutByRoute: Record<string, string> = {
  '/dashboard': 'D',
  '/requests': 'R',
  '/requests/new': 'N',
  '/workflows': 'W',
  '/merchants': 'M',
  '/staff': 'F',
  '/customs': 'X',
  '/reports': 'T',
  '/audit': 'A',
  '/notifications': 'I',
  '/admin/banks': 'B',
  '/admin/cby-staff': 'U',
  '/admin/workflows': 'Y',
  '/admin/reference-data': 'J',
  '/admin/teams': 'Q',
  '/admin/roles': 'P',
  '/settings': 'S',
}

const commandGroupByRoute: Array<{ heading: string; routes: string[] }> = [
  {
    heading: 'الطلبات والطوابير',
    routes: ['/dashboard', '/requests', '/requests/new', '/workflows', '/workflows/new', '/customs'],
  },
  {
    heading: 'الإدارة',
    routes: [
      '/merchants',
      '/staff',
      '/admin/banks',
      '/admin/cby-staff',
      '/admin/workflows',
      '/admin/reference-data',
      '/admin/teams',
      '/admin/roles',
    ],
  },
  { heading: 'التدقيق والتقارير', routes: ['/reports', '/audit'] },
  { heading: 'المساعدة والإعدادات', routes: ['/notifications', '/settings'] },
]

const allowedActions = computed(() => {
  const user = authStore.user
  if (!user) return []

  return NAV_ITEMS.filter((item) => item.roles.includes(user.role)).map((item) => ({
    title: item.label,
    url: item.route,
    icon: ICONS[item.icon] ?? ICONS.search,
    shortcut: shortcutByRoute[item.route] ?? '',
  }))
})

const commandGroups = computed(() =>
  commandGroupByRoute
    .map((group) => ({
      heading: group.heading,
      actions: allowedActions.value.filter((action) => group.routes.includes(action.url)),
    }))
    .filter((group) => group.actions.length > 0),
)

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
    class="text-muted-foreground hover:text-foreground h-7 gap-1.5 rounded-md px-2"
    aria-label="بحث ⌘K"
    @click="open = true"
  >
    <Search class="h-4 w-4" />
    <span class="hidden text-sm sm:inline">بحث سريع</span>
    <div class="hidden items-center gap-0.5 sm:flex">
      <Kbd class="h-5 px-1 text-[10px]">⌘</Kbd>
      <Kbd class="h-5 px-1 text-[10px]">K</Kbd>
    </div>
  </Button>

  <Dialog v-model:open="open">
    <DialogContent class="overflow-hidden p-0 shadow-lg">
      <Command
        class="[&_[cmdk-group-heading]]:text-muted-foreground [&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:text-xs [&_[cmdk-group-heading]]:font-medium [&_[cmdk-input-wrapper]_svg]:h-5 [&_[cmdk-input-wrapper]_svg]:w-5 [&_[cmdk-input]]:h-12 [&_[cmdk-item]]:px-2 [&_[cmdk-item]]:py-3 [&_[cmdk-item]_svg]:h-5 [&_[cmdk-item]_svg]:w-5"
      >
        <CommandInput placeholder="ابحث عن صفحة أو إجراء" />
        <CommandList>
          <CommandEmpty>لم يتم العثور على نتائج.</CommandEmpty>
          <CommandGroup
            v-for="group in commandGroups"
            :key="group.heading"
            :heading="group.heading"
          >
            <CommandItem
              v-for="action in group.actions"
              :key="action.url"
              :value="`${group.heading} ${action.title}`"
              @select="handleSelect(action.url)"
            >
              <component :is="action.icon" class="h-4 w-4" />
              <span>{{ action.title }}</span>
              <CommandShortcut v-if="action.shortcut">⌘{{ action.shortcut }}</CommandShortcut>
            </CommandItem>
          </CommandGroup>
        </CommandList>
      </Command>
    </DialogContent>
  </Dialog>
</template>
