<script setup lang="ts">
import PageHeader from '@/components/layout/PageHeader.vue'
import {
  Bell,
  Check,
  CheckCheck,
  CheckCircle2,
  FileText,
  Inbox,
  Search,
  Undo2,
  Vote,
  XCircle,
} from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { useNotificationsStore } from '@/stores/notifications.store'
import { useNotifications } from '@/composables/useNotifications'
import type { Notification } from '@/types/models'

type FilterMode = 'all' | 'unread' | 'read'
type Severity = 'critical' | 'warning' | 'success' | 'voting' | 'info'

const notificationsStore = useNotificationsStore()
const { fetchNotifications, markRead, markAllRead } = useNotifications()

const filter = ref<FilterMode>('all')
const query = ref('')

onMounted(() => fetchNotifications())

function severityFor(notification: Notification): Severity {
  const text = notification.data?.message?.toLowerCase() ?? ''
  if (text.includes('رفض') || text.includes('تنبيه')) return 'critical'
  if (text.includes('إعادة') || text.includes('معاد') || text.includes('نقص')) return 'warning'
  if (text.includes('اعتماد') || text.includes('صدر') || text.includes('مكتمل')) return 'success'
  if (text.includes('تصويت') || text.includes('اللجنة')) return 'voting'
  return 'info'
}

const SEVERITY_STYLES: Record<Severity, {
  icon: unknown
  iconWrap: string
  border: string
  unreadBg: string
  dot: string
  label: string
}> = {
  critical: { icon: XCircle, iconWrap: 'text-rose-50 bg-rose-600 ring-2 ring-rose-200', border: 'border-e-4 border-e-rose-600', unreadBg: 'bg-rose-50/70', dot: 'bg-rose-600', label: 'عاجل' },
  warning: { icon: FileText, iconWrap: 'text-amber-50 bg-amber-500 ring-2 ring-amber-200', border: 'border-e-4 border-e-amber-500', unreadBg: 'bg-amber-50/60', dot: 'bg-amber-500', label: 'مهم' },
  success: { icon: CheckCircle2, iconWrap: 'text-emerald-50 bg-emerald-600 ring-2 ring-emerald-200', border: 'border-e-4 border-e-emerald-600', unreadBg: 'bg-emerald-50/60', dot: 'bg-emerald-600', label: 'إنجاز' },
  voting: { icon: Vote, iconWrap: 'text-violet-50 bg-violet-600 ring-2 ring-violet-200', border: 'border-e-4 border-e-violet-600', unreadBg: 'bg-violet-50/60', dot: 'bg-violet-600', label: 'تصويت' },
  info: { icon: Bell, iconWrap: 'text-sky-50 bg-sky-600 ring-2 ring-sky-200', border: 'border-e-4 border-e-sky-500', unreadBg: 'bg-sky-50/50', dot: 'bg-sky-500', label: 'إشعار' },
}

function bucketLabel(createdAt: string) {
  const diff = Date.now() - new Date(createdAt).getTime()
  const hours = diff / 3_600_000
  if (hours < 24) return 'اليوم'
  if (hours < 48) return 'أمس'
  if (hours < 168) return 'هذا الأسبوع'
  return 'أقدم'
}

const notifications = computed(() => notificationsStore.items)
const unreadCount = computed(() => notificationsStore.unreadCount)

const filtered = computed(() => notifications.value.filter((n) => {
  if (filter.value === 'unread' && n.read_at) return false
  if (filter.value === 'read' && !n.read_at) return false
  if (query.value.trim()) {
    return (n.data?.message ?? '').toLowerCase().includes(query.value.trim().toLowerCase())
  }
  return true
}))

const groups = computed(() => {
  const map = new Map<string, Notification[]>()
  for (const n of filtered.value) {
    const key = bucketLabel(n.created_at)
    if (!map.has(key)) map.set(key, [])
    map.get(key)!.push(n)
  }
  return [...map.entries()]
})

async function openNotification(notification: Notification) {
  if (!notification.read_at) await markRead(notification.id)
  if (notification.data?.request_id) {
    navigateTo(`/requests/${notification.data.request_id}`)
  }
}

async function handleMarkAllRead() {
  await markAllRead()
  notificationsStore.markAllRead()
}
</script>

<template>
  <div>
    <PageHeader
      title="مركز الإشعارات"
      :subtitle="`${unreadCount} غير مقروء من ${notifications.length} إجمالاً`"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'الإشعارات' }]"
    >
      <template #actions>
        <Button
          variant="outline"
          size="sm"
          :disabled="unreadCount === 0"
          @click="handleMarkAllRead"
        >
          <CheckCheck class="ms-1 h-4 w-4" />
          تحديد الكل كمقروء
        </Button>
      </template>
    </PageHeader>

    <Card class="mb-4 border-0 p-3 shadow-card">
      <div class="flex flex-col gap-3 md:flex-row md:items-center">
        <div class="relative flex-1">
          <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            v-model="query"
            placeholder="ابحث في الإشعارات..."
            class="pe-9"
          />
        </div>

        <Tabs
          v-model="filter"
          class="w-full md:w-auto"
        >
          <TabsList>
            <TabsTrigger value="all">
              الكل ({{ notifications.length }})
            </TabsTrigger>
            <TabsTrigger value="unread">
              غير مقروء ({{ unreadCount }})
            </TabsTrigger>
            <TabsTrigger value="read">
              مقروء ({{ notifications.length - unreadCount }})
            </TabsTrigger>
          </TabsList>
        </Tabs>
      </div>
    </Card>

    <Card
      v-if="filtered.length === 0"
      class="border-0 shadow-card"
    >
      <div class="p-12 text-center">
        <Inbox class="mx-auto h-10 w-10 text-muted-foreground/60" />
        <div class="mt-3 text-sm text-muted-foreground">
          {{ notifications.length === 0 ? 'لا توجد إشعارات بعد.' : 'لا توجد إشعارات تطابق هذا الفلتر.' }}
        </div>
      </div>
    </Card>

    <div
      v-else
      class="space-y-4"
    >
      <div
        v-for="[label, items] in groups"
        :key="label"
      >
        <div class="mb-2 px-1 text-xs font-semibold text-muted-foreground">
          {{ label }}
        </div>
        <Card class="overflow-hidden border-0 shadow-card">
          <div
            v-for="notification in items"
            :key="notification.id"
            :class="cn(
              'flex cursor-pointer gap-3 border-b p-4 transition-colors last:border-b-0 hover:bg-muted/40',
              SEVERITY_STYLES[severityFor(notification)].border,
              !notification.read_at && SEVERITY_STYLES[severityFor(notification)].unreadBg,
            )"
            @click="openNotification(notification)"
          >
            <div :class="cn('grid h-10 w-10 shrink-0 place-items-center rounded-full shadow-sm', SEVERITY_STYLES[severityFor(notification)].iconWrap)">
              <component
                :is="SEVERITY_STYLES[severityFor(notification)].icon"
                class="h-5 w-5"
              />
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                <span class="truncate">{{ notification.data?.message ?? 'إشعار' }}</span>
                <span :class="cn('rounded px-1.5 py-0.5 text-[9px] font-bold text-white', SEVERITY_STYLES[severityFor(notification)].dot)">
                  {{ SEVERITY_STYLES[severityFor(notification)].label }}
                </span>
                <span
                  v-if="!notification.read_at"
                  :class="cn('inline-block h-2 w-2 rounded-full', SEVERITY_STYLES[severityFor(notification)].dot)"
                />
              </div>
              <div
                v-if="notification.data?.reference_number"
                class="mt-0.5 text-xs text-muted-foreground"
              >
                طلب رقم: {{ notification.data.reference_number }}
              </div>
              <div class="mt-1 text-[10px] text-muted-foreground">
                {{ new Date(notification.created_at).toLocaleString('ar-EG') }}
              </div>
            </div>

            <div
              class="flex shrink-0 items-start gap-1"
              @click.stop
            >
              <Button
                variant="ghost"
                size="icon"
                class="h-7 w-7"
                @click="markRead(notification.id)"
              >
                <Check
                  v-if="!notification.read_at"
                  class="h-3.5 w-3.5"
                />
                <Undo2
                  v-else
                  class="h-3.5 w-3.5"
                />
              </Button>
            </div>
          </div>
        </Card>
      </div>
    </div>
  </div>
</template>
