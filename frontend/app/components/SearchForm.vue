<script setup lang="ts">
import { ref } from 'vue'
import { Search } from 'lucide-vue-next'
import { useMagicKeys, whenever } from '@vueuse/core'
import { useAuthStore } from '@/stores/auth.store'
import { NAV_ITEMS } from '@/constants/workflow'
import { ICONS } from '@/utils/icon-map'
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
  if (!user) return []
  const shortcutByRoute: Record<string, string> = {
    '/requests': 'R',
    '/requests/new': 'N',
    '/merchants': 'M',
    '/admin/entities': 'B',
    '/admin/cby-staff': 'U',
    '/reports': 'T',
    '/notifications': 'I',
    '/settings': 'S',
    '/customs': 'X',
  }

  return NAV_ITEMS
    .filter(item => item.roles.includes(user.role))
    .filter(item => shortcutByRoute[item.route])
    .map(item => ({
      title: item.label,
      url: item.route,
      icon: ICONS[item.icon] ?? ICONS.search,
      shortcut: shortcutByRoute[item.route],
    }))
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
