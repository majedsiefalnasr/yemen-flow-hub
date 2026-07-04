<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import { InputGroup, InputGroupInput, InputGroupAddon } from '@/components/ui/input-group'
import { Skeleton } from '@/components/ui/skeleton'
import { Alert, AlertTitle, AlertDescription, AlertAction } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Empty, EmptyMedia, EmptyTitle, EmptyDescription } from '@/components/ui/empty'
import { AlertCircle, Building2, Search, Users as UsersIcon } from 'lucide-vue-next'
import DemoUserSwitcherCard from '@/components/auth/DemoUserSwitcherCard.vue'
import { useDemoUsers } from '@/composables/useDemoUsers'
import { useAuthStore } from '@/stores/auth.store'
import type { DemoUser } from '~/types/models'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { users, loading, error, fetchDemoUsers } = useDemoUsers()
const authStore = useAuthStore()
const searchQuery = ref('')

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      searchQuery.value = ''
      void fetchDemoUsers()
    }
  },
)

function matchesQuery(user: DemoUser, query: string): boolean {
  const haystack = [
    user.name,
    user.email,
    user.role_label,
    user.organization?.name ?? '',
    user.team?.name ?? '',
  ]
    .join(' ')
    .toLowerCase()
  return haystack.includes(query.toLowerCase())
}

const filteredUsers = computed(() => {
  const query = searchQuery.value.trim()
  const list = query ? users.value.filter((user) => matchesQuery(user, query)) : users.value
  return list
})

const groupedUsers = computed(() => {
  const groups = new Map<string, DemoUser[]>()
  for (const user of filteredUsers.value) {
    const key = user.organization?.name ?? 'أخرى'
    const bucket = groups.get(key) ?? []
    bucket.push(user)
    groups.set(key, bucket)
  }
  return Array.from(groups.entries())
})

async function handleSelect(user: DemoUser): Promise<void> {
  try {
    await authStore.switchDemoUser(user.id)
    emit('update:open', false)
    await navigateTo('/dashboard')
  } catch {
    toast.error(`تعذّر تسجيل الدخول كـ ${user.name}`)
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
    <DialogContent class="flex max-h-[85dvh] w-full flex-col gap-4 sm:max-w-md">
      <DialogHeader>
        <DialogTitle>تبديل المستخدم السريع</DialogTitle>
        <DialogDescription
          >اختر حساباً لتسجيل الدخول به مباشرة دون إعادة المصادقة</DialogDescription
        >
      </DialogHeader>

      <InputGroup>
        <InputGroupAddon align="inline-start">
          <Search class="h-4 w-4" />
        </InputGroupAddon>
        <InputGroupInput v-model="searchQuery" placeholder="ابحث بالاسم أو البريد أو الدور…" />
      </InputGroup>

      <div class="flex-1 space-y-6 overflow-y-auto p-2">
        <template v-if="loading">
          <div class="space-y-3">
            <Skeleton v-for="n in 6" :key="n" class="h-20 w-full rounded-xl" />
          </div>
        </template>

        <Alert v-else-if="error" variant="destructive" role="alert">
          <AlertCircle class="h-4 w-4" />
          <AlertTitle>خطأ في التحميل</AlertTitle>
          <AlertDescription>{{ error }}</AlertDescription>
          <AlertAction>
            <Button variant="outline" size="sm" @click="fetchDemoUsers">إعادة المحاولة</Button>
          </AlertAction>
        </Alert>

        <Empty v-else-if="groupedUsers.length === 0">
          <EmptyMedia variant="icon">
            <UsersIcon />
          </EmptyMedia>
          <EmptyTitle>لا يوجد مستخدمون</EmptyTitle>
          <EmptyDescription>لا توجد نتائج مطابقة لبحثك.</EmptyDescription>
        </Empty>

        <template v-else>
          <div v-for="[groupName, groupUsers] in groupedUsers" :key="groupName" class="space-y-3">
            <div class="flex items-center gap-2">
              <Building2 class="text-muted-foreground h-3.5 w-3.5 shrink-0" aria-hidden="true" />
              <h3 class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                {{ groupName }}
              </h3>
              <Badge variant="secondary" class="h-5 shrink-0 px-1.5 text-[10px] leading-none">
                {{ groupUsers.length }}
              </Badge>
              <Separator class="flex-1" />
            </div>
            <div class="space-y-2">
              <DemoUserSwitcherCard
                v-for="user in groupUsers"
                :key="user.id"
                :user="user"
                @select="handleSelect(user)"
              />
            </div>
          </div>
        </template>
      </div>
    </DialogContent>
  </Dialog>
</template>
