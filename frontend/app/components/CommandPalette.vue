<script setup lang="ts">
import type { Component } from 'vue'
import {
  FileCheck,
  FileText,
  Globe,
  Home,
  Landmark,
  PlusCircle,
  Search,
  ShieldCheck,
  Stamp,
  Users,
} from 'lucide-vue-next'
import { useMagicKeys, whenever } from '@vueuse/core'
import { NAV_ITEMS } from '@/constants/workflow'
import { ICONS } from '@/utils/icon-map'
import { useAuthStore } from '@/stores/auth.store'
import { UserRole } from '@/types/enums'
import { Button } from '@/components/ui/button'
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
  CommandShortcut,
} from '@/components/ui/command'
import { Kbd } from '@/components/ui/kbd'

// ── Types ─────────────────────────────────────────────────────────────

interface PaletteAction {
  id: string
  /** Arabic display label */
  title: string
  /** English alias keywords included in cmdk value for cross-language search */
  aliases?: string
  url: string
  icon: Component
  shortcut?: string
  isQuickAction?: boolean
}

interface PaletteGroup {
  heading: string
  actions: PaletteAction[]
}

// ── State ─────────────────────────────────────────────────────────────

const open = ref(false)
const authStore = useAuthStore()
const router = useRouter()

// Cmd/Ctrl + K → open
const { meta_k, ctrl_k } = useMagicKeys()
whenever(
  computed(() => Boolean(meta_k?.value || ctrl_k?.value)),
  () => {
    open.value = true
  },
)

// ── Keyboard shortcuts per route ──────────────────────────────────────

const shortcutByRoute: Record<string, string> = {
  '/dashboard': 'D',
  '/requests': 'R',
  '/requests/new': 'N',
  '/workflows': 'W',
  '/traders': 'G',
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

// ── English aliases per route (for cross-language search matching) ────

const aliasesByRoute: Record<string, string> = {
  '/dashboard': 'dashboard home overview',
  '/requests': 'requests list queue',
  '/requests/new': 'new request create submit',
  '/workflows': 'engine workflow dynamic requests دوري',
  '/traders': 'traders tax owners companies',
  '/merchants': 'merchants companies banks',
  '/staff': 'staff employees users',
  '/customs': 'fx confirmation external',
  '/reports': 'reports analytics charts',
  '/audit': 'audit logs compliance',
  '/notifications': 'notifications alerts',
  '/admin/banks': 'banks بنوك organizations institutions',
  '/admin/cby-staff': 'cby staff admin users',
  '/admin/workflows': 'workflow designer مصمم سير العمل stages transitions',
  '/admin/reference-data': 'reference data البيانات الأساسية lookup tables',
  '/admin/teams': 'teams فرق groups',
  '/admin/roles': 'roles permissions access',
  '/settings': 'settings preferences configuration',
}

// ── Quick actions per role ─────────────────────────────────────────────

const QUICK_ACTIONS: Partial<Record<UserRole, PaletteAction[]>> = {
  [UserRole.DATA_ENTRY]: [
    {
      id: 'qa-new-request',
      title: 'تقديم طلب جديد',
      aliases: 'new request create submit',
      url: '/requests/new',
      icon: PlusCircle,
      shortcut: 'N',
      isQuickAction: true,
    },
  ],
  [UserRole.BANK_REVIEWER]: [
    {
      id: 'qa-review-queue',
      title: 'طلبات تنتظر المراجعة',
      aliases: 'review queue pending requests',
      url: '/requests',
      icon: FileCheck,
      isQuickAction: true,
    },
  ],
  [UserRole.BANK_ADMIN]: [
    {
      id: 'qa-bank-overview',
      title: 'لوحة الإشراف البنكي',
      aliases: 'bank dashboard overview admin',
      url: '/dashboard',
      icon: Home,
      isQuickAction: true,
    },
  ],
  [UserRole.SUPPORT_COMMITTEE]: [
    {
      id: 'qa-support-queue',
      title: 'قائمة انتظار لجنة المساندة',
      aliases: 'support committee queue claim',
      url: '/requests',
      icon: Users,
      isQuickAction: true,
    },
  ],
  [UserRole.SWIFT_OFFICER]: [
    {
      id: 'qa-swift-queue',
      title: 'رفع مستندات SWIFT',
      aliases: 'swift upload documents queue',
      url: '/requests',
      icon: FileText,
      isQuickAction: true,
    },
  ],
  [UserRole.EXECUTIVE_MEMBER]: [
    {
      id: 'qa-voting',
      title: 'جلسات التصويت النشطة',
      aliases: 'voting sessions active executive',
      url: '/dashboard',
      icon: ShieldCheck,
      isQuickAction: true,
    },
  ],
  [UserRole.COMMITTEE_DIRECTOR]: [
    {
      id: 'qa-voting-control',
      title: 'التحكم في جلسات التصويت',
      aliases: 'voting control open close director',
      url: '/dashboard',
      icon: ShieldCheck,
      isQuickAction: true,
    },
    {
      id: 'qa-fx-confirm',
      title: 'تأكيد المصارفة الخارجية',
      aliases: 'fx confirmation external customs director',
      url: '/customs',
      icon: Stamp,
      isQuickAction: true,
    },
  ],
  [UserRole.CBY_ADMIN]: [
    {
      id: 'qa-governance',
      title: 'لوحة الحوكمة والإشراف',
      aliases: 'governance oversight cby admin dashboard',
      url: '/dashboard',
      icon: Landmark,
      isQuickAction: true,
    },
    {
      id: 'qa-audit',
      title: 'سجلات التدقيق والامتثال',
      aliases: 'audit logs compliance cby',
      url: '/audit',
      icon: ShieldCheck,
      isQuickAction: true,
    },
  ],
}

// ── Group definition (route → group heading) ──────────────────────────

const GROUP_DEFS: Array<{ heading: string; routes: string[] }> = [
  { heading: 'الطلبات', routes: ['/requests', '/requests/new', '/workflows', '/workflows/new'] },
  { heading: 'الطوابير', routes: ['/dashboard', '/customs'] },
  {
    heading: 'الجهات والمستخدمون',
    routes: ['/traders', '/merchants', '/staff', '/admin/banks', '/admin/cby-staff'],
  },
  { heading: 'التدقيق والتقارير', routes: ['/audit', '/reports'] },
  {
    heading: 'الإعدادات والإدارة',
    routes: [
      '/settings',
      '/admin/workflows',
      '/admin/reference-data',
      '/admin/teams',
      '/admin/roles',
      '/admin/screen-permissions',
    ],
  },
  { heading: 'المساعدة والإشعارات', routes: ['/notifications'] },
]

// ── Computed groups ────────────────────────────────────────────────────

const allowedNavActions = computed<PaletteAction[]>(() => {
  const user = authStore.user
  if (!user) return []

  return NAV_ITEMS.filter((item) => item.roles.includes(user.role)).map((item) => ({
    id: `nav-${item.route}`,
    title: item.label,
    aliases: aliasesByRoute[item.route] ?? '',
    url: item.route,
    icon: ICONS[item.icon] ?? Globe,
    shortcut: shortcutByRoute[item.route],
  }))
})

const quickActions = computed<PaletteAction[]>(() => {
  const role = authStore.user?.role
  if (!role) return []
  return QUICK_ACTIONS[role] ?? []
})

const paletteGroups = computed<PaletteGroup[]>(() => {
  const groups: PaletteGroup[] = []

  // Quick actions first (role-specific, no heading duplication)
  if (quickActions.value.length > 0) {
    groups.push({
      heading: 'إجراءات سريعة',
      actions: quickActions.value,
    })
  }

  // Navigation groups — filter to role-allowed routes
  for (const def of GROUP_DEFS) {
    const actions = allowedNavActions.value.filter((a) => def.routes.includes(a.url))
    if (actions.length > 0) {
      groups.push({ heading: def.heading, actions })
    }
  }

  return groups
})

// ── Navigation ─────────────────────────────────────────────────────────

function navigate(url: string) {
  router.push(url)
  open.value = false
}

// ── Platform detection for shortcut display ───────────────────────────

const isMac = computed(() => {
  if (typeof navigator === 'undefined') return false
  return /mac/i.test(navigator.platform)
})
</script>

<template>
  <!-- Search trigger button in topbar -->
  <Button
    variant="outline"
    size="sm"
    class="border-input bg-background/80 text-muted-foreground hover:bg-muted/40 hover:text-foreground h-9 w-full justify-between gap-2 rounded-lg px-3"
    aria-label="البحث وإجراءات سريعة"
    @click="open = true"
  >
    <span class="flex min-w-0 items-center gap-2">
      <Search class="h-4 w-4 shrink-0" />
      <span class="truncate text-sm">ابحث أو اختر إجراء...</span>
    </span>
    <div class="hidden items-center gap-0.5 sm:flex" dir="ltr">
      <Kbd class="h-5 px-1 text-[10px]">{{ isMac ? '⌘' : 'Ctrl' }} K</Kbd>
    </div>
  </Button>

  <!-- Command dialog -->
  <CommandDialog
    v-model:open="open"
    title="البحث وتصفح المنصة"
    description="ابحث عن أي صفحة أو إجراء متاح لدورك"
    command-class="[&_[data-slot=command-input-wrapper]]:px-3 [&_[data-slot=command-input-wrapper]]:pt-3 [&_[data-slot=command-input-wrapper]]:pb-2 [&_[data-slot=command-list]]:max-h-80 [&_[data-slot=command-list]]:px-2 [&_[data-slot=command-list]]:pb-2 [&_[cmdk-group-heading]]:px-3 [&_[cmdk-group-heading]]:py-2 [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:text-muted-foreground [&_[cmdk-group-heading]]:text-xs [&_[cmdk-input-wrapper]_svg]:h-5 [&_[cmdk-input-wrapper]_svg]:w-5 [&_[cmdk-input]]:h-12 [&_[cmdk-item]]:cursor-pointer [&_[cmdk-item]]:rounded-lg [&_[cmdk-item]]:px-3 [&_[cmdk-item]]:py-3.5 [&_[cmdk-item]]:transition-colors [&_[cmdk-item]:hover]:bg-sidebar-accent [&_[cmdk-item]:hover]:text-sidebar-accent-foreground [&_[cmdk-item][data-highlighted]]:bg-sidebar-accent [&_[cmdk-item][data-highlighted]]:text-sidebar-accent-foreground [&_[cmdk-item]_svg]:h-5 [&_[cmdk-item]_svg]:w-5"
  >
    <CommandInput placeholder="ابحث أو اختر إجراء..." />
    <CommandList>
      <CommandEmpty>لم يتم العثور على نتائج.</CommandEmpty>
      <template v-for="(group, index) in paletteGroups" :key="group.heading">
        <CommandSeparator v-if="index > 0" />
        <CommandGroup :heading="group.heading">
          <CommandItem
            v-for="action in group.actions"
            :key="action.id"
            :value="`${group.heading} ${action.title} ${action.aliases ?? ''}`"
            @select="navigate(action.url)"
          >
            <component
              :is="action.icon"
              :class="[
                'h-4 w-4 shrink-0',
                action.isQuickAction ? 'text-primary' : 'text-muted-foreground',
              ]"
            />
            <span>{{ action.title }}</span>
            <CommandShortcut v-if="action.shortcut">
              <span class="flex items-center gap-0.5" dir="ltr">
                <Kbd class="h-4 px-1 text-[9px]"
                  >{{ isMac ? '⌘' : 'Ctrl' }}{{ action.shortcut }}</Kbd
                >
              </span>
            </CommandShortcut>
          </CommandItem>
        </CommandGroup>
      </template>
    </CommandList>
  </CommandDialog>
</template>
