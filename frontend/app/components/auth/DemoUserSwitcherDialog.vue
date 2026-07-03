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
import { Empty, EmptyMedia, EmptyTitle, EmptyDescription } from '@/components/ui/empty'
import { AlertCircle, Search, Users as UsersIcon } from 'lucide-vue-next'
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
const switchingUserId = ref<number | null>(null)

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
  switchingUserId.value = user.id
  try {
    await authStore.switchDemoUser(user.id)
    emit('update:open', false)
    await navigateTo('/dashboard')
  } catch {
    toast.error(`تعذّر تسجيل الدخول كـ ${user.name}`)
  } finally {
    switchingUserId.value = null
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
    <DialogContent class="flex max-h-[80dvh] w-full max-w-lg flex-col gap-4">
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

      <div class="flex-1 space-y-4 overflow-y-auto">
        <template v-if="loading">
          <Skeleton v-for="n in 4" :key="n" class="h-16 w-full rounded-xl" />
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
          <div v-for="[groupName, groupUsers] in groupedUsers" :key="groupName" class="space-y-2">
            <h3 class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
              {{ groupName }}
            </h3>
            <DemoUserSwitcherCard
              v-for="user in groupUsers"
              :key="user.id"
              :user="user"
              @select="handleSelect(user)"
            />
          </div>
        </template>
      </div>
    </DialogContent>
  </Dialog>
</template>
