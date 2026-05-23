<script setup lang="ts">
import PageHeader from '@/components/layout/PageHeader.vue'
import { Activity, BadgeCheck, Building2, KeyRound, Mail, Phone, Save, Shield } from 'lucide-vue-next'
import { ROLE_LABELS } from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useProfile } from '@/composables/useProfile'

const authStore = useAuthStore()
const user = computed(() => authStore.user)
const { profile, loading, fetchProfile, updateProfile } = useProfile()
const { notify } = useToast()

const name = ref(user.value?.name ?? '')
const email = ref(user.value?.email ?? '')
const phone = ref(user.value?.phone ?? '')

onMounted(() => fetchProfile())

watch(profile, (p) => {
  if (!p) return
  name.value = p.name
  email.value = p.email
  phone.value = p.phone ?? ''
})

const stats = computed(() => {
  const s = profile.value?.stats
  if (!s) return []
  return [
    { label: 'إجمالي الطلبات', value: s.total },
    { label: 'قيد المعالجة', value: s.in_progress },
    { label: 'مكتمل', value: s.completed },
  ]
})

const myActivity = computed(() => profile.value?.recent_activity?.slice(0, 6) ?? [])

async function saveProfile() {
  const ok = await updateProfile({ name: name.value, email: email.value, phone: phone.value })
  if (ok) notify('تم حفظ التغييرات')
}

function requestPasswordReset() {
  notify('سيتم إرسال رابط إعادة التعيين إلى بريدك')
}

function requestMfaActivation() {
  notify('تم إرسال طلب تفعيل المصادقة الثنائية')
}

function userInitials(n?: string) {
  if (!n) return '?'
  return n.split(' ').map(p => p[0]).slice(0, 2).join('')
}
</script>

<template>
  <div v-if="user">
    <PageHeader
      title="الملف الشخصي"
      subtitle="معلومات الحساب وإعدادات الأمان"
    />

    <div class="grid gap-6 lg:grid-cols-3">
      <Card class="border-0 p-6 text-center shadow-card">
        <Avatar class="mx-auto h-24 w-24">
          <AvatarFallback class="bg-primary text-3xl font-bold text-primary-foreground">
            {{ userInitials(user.name) }}
          </AvatarFallback>
        </Avatar>
        <div class="mt-4 flex items-center justify-center gap-1.5 text-lg font-bold">
          {{ user.name }}
          <BadgeCheck class="h-4 w-4 text-primary" />
        </div>
        <Badge
          variant="secondary"
          class="mt-1"
        >
          {{ ROLE_LABELS[user.role] }}
        </Badge>
        <div class="mt-2 text-xs text-muted-foreground">
          {{ user.bank_name_ar }}
        </div>

        <div class="mt-6 grid grid-cols-3 gap-3 border-t pt-6">
          <div
            v-for="stat in stats"
            :key="stat.label"
          >
            <div class="font-bold tabular-nums">
              {{ stat.value }}
            </div>
            <div class="text-[10px] text-muted-foreground">
              {{ stat.label }}
            </div>
          </div>
        </div>

        <div class="mt-6 space-y-2 border-t pt-6 text-end">
          <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <Mail class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate">{{ user.email }}</span>
          </div>
          <div
            v-if="phone"
            class="flex items-center gap-2 text-xs text-muted-foreground"
          >
            <Phone class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate">{{ phone }}</span>
          </div>
          <div
            v-if="user.bank_name_ar"
            class="flex items-center gap-2 text-xs text-muted-foreground"
          >
            <Building2 class="h-3.5 w-3.5 shrink-0" />
            <span class="truncate">{{ user.bank_name_ar }}</span>
          </div>
        </div>
      </Card>

      <Card class="space-y-5 border-0 p-6 shadow-card lg:col-span-2">
        <div>
          <h3 class="font-semibold">
            المعلومات الأساسية
          </h3>
          <p class="mt-0.5 text-xs text-muted-foreground">
            حدّث بياناتك الشخصية وطرق التواصل
          </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label>الاسم الكامل</Label>
            <Input v-model="name" />
          </div>
          <div class="space-y-2">
            <Label>البريد الإلكتروني</Label>
            <Input
              v-model="email"
              type="email"
            />
          </div>
          <div class="space-y-2">
            <Label>رقم الهاتف</Label>
            <Input
              v-model="phone"
              placeholder="+9677..."
            />
          </div>
          <div class="space-y-2">
            <Label>الجهة</Label>
            <Input
              :model-value="user.bank_name_ar ?? ''"
              disabled
            />
          </div>
          <div class="space-y-2">
            <Label>الدور</Label>
            <Input
              :model-value="ROLE_LABELS[user.role]"
              disabled
            />
          </div>
          <div class="space-y-2">
            <Label>المعرّف</Label>
            <Input
              :model-value="user.id.toString()"
              disabled
              class="font-mono text-xs"
            />
          </div>
        </div>

        <div class="flex flex-wrap gap-2 border-t pt-4">
          <Button
            :disabled="loading"
            @click="saveProfile"
          >
            <Save class="ms-1 h-4 w-4" />
            حفظ التغييرات
          </Button>
          <Button
            variant="outline"
            @click="requestPasswordReset"
          >
            <KeyRound class="ms-1 h-4 w-4" />
            تغيير كلمة المرور
          </Button>
          <Button
            variant="ghost"
            @click="requestMfaActivation"
          >
            <Shield class="ms-1 h-4 w-4" />
            المصادقة الثنائية
          </Button>
        </div>

        <div class="border-t pt-4">
          <h3 class="mb-3 flex items-center gap-2 font-semibold">
            <Activity class="h-4 w-4" />
            آخر نشاطي
          </h3>
          <div
            v-if="myActivity.length === 0"
            class="py-6 text-center text-sm text-muted-foreground"
          >
            لا يوجد نشاط مسجل بعد.
          </div>
          <div
            v-else
            class="space-y-1.5"
          >
            <div
              v-for="entry in myActivity"
              :key="entry.id"
              class="flex items-center gap-3 rounded-lg p-2.5 hover:bg-muted/40"
            >
              <div class="grid h-8 w-8 place-items-center rounded-lg bg-muted">
                <Activity class="h-4 w-4" />
              </div>
              <div class="flex-1 text-sm">
                <div class="font-medium">
                  {{ entry.action }}
                </div>
                <div
                  v-if="entry.ref"
                  class="font-mono text-[11px] text-muted-foreground"
                >
                  {{ entry.ref }}
                </div>
              </div>
              <div class="text-xs text-muted-foreground">
                {{ new Date(entry.ts).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }) }}
              </div>
            </div>
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>
