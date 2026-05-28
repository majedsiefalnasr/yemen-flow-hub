<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Activity, AlertTriangle, BadgeCheck, Building2, KeyRound, Mail, Phone, Save, Shield } from 'lucide-vue-next'
import { Card, CardContent } from '@/components/ui/card'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { ROLE_LABELS } from '@/constants/workflow'
import { UserRole } from '@/types/enums'
import { useAuthStore } from '@/stores/auth.store'
import { useProfile } from '@/composables/useProfile'
import { useToast } from '@/composables/use-toast'

const authStore = useAuthStore()
const user = computed(() => authStore.user)
const { profile, loading, fetchProfile, updateProfile, toggleMfa: composableToggleMfa, changePassword } = useProfile()
const { notify } = useToast()

const name = ref(user.value?.name ?? '')
const email = ref(user.value?.email ?? '')
const phone = ref(user.value?.phone ?? '')

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})
const passwordSuccess = ref(false)
const mfaEnabled = ref(user.value?.mfa_enabled ?? false)

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
  if (user.value?.role === UserRole.BANK_REVIEWER) {
    return [
      { label: 'مراجعات', value: s.reviews_performed ?? s.total ?? 0 },
      { label: 'اعتمادات', value: s.approvals ?? 0 },
      { label: 'إعادات', value: s.returns ?? 0 },
      { label: 'رفض نهائي', value: s.terminal_rejections ?? 0 },
    ]
  }
  if (user.value?.role === UserRole.BANK_ADMIN) {
    return [
      { label: 'موظفون', value: s.staff_managed ?? s.total ?? 0 },
      { label: 'تجار', value: s.merchants_managed ?? s.in_progress ?? 0 },
      { label: 'مكتمل', value: s.completed ?? 0 },
    ]
  }
  if (user.value?.role === UserRole.COMMITTEE_DIRECTOR) {
    return [
      { label: 'جلسات أُغلقت', value: s.sessions_closed ?? s.total ?? 0 },
      { label: 'قرارات مُصدرة', value: s.decisions_finalized ?? s.completed ?? 0 },
      { label: 'تأكيدات مصارفة', value: s.fx_confirmations_completed ?? 0 },
    ]
  }
  if (user.value?.role === UserRole.EXECUTIVE_MEMBER) {
    return [
      { label: 'جلسات شارك بها', value: s.sessions_participated ?? s.total ?? 0 },
      { label: 'متوسط وقت التصويت', value: s.avg_time_to_vote_hours != null ? `${s.avg_time_to_vote_hours}س` : '—' },
      { label: 'نسبة الاعتماد', value: s.approval_percentage != null ? `${s.approval_percentage}%` : '—' },
    ]
  }
  if (user.value?.role === UserRole.SWIFT_OFFICER) {
    return [
      { label: 'وثائق مرفوعة', value: s.swift_uploads ?? s.total ?? 0 },
      { label: 'متوسط وقت الرفع', value: s.avg_time_to_upload_hours != null ? `${s.avg_time_to_upload_hours}س` : '—' },
      { label: 'مكتمل', value: s.completed ?? 0 },
    ]
  }
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

async function submitPasswordChange() {
  const ok = await changePassword({
    current_password: passwordForm.current_password,
    password: passwordForm.password,
    password_confirmation: passwordForm.password_confirmation,
  })
  if (ok) {
    passwordSuccess.value = true
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
  }
}

async function toggleMfa() {
  if (composableToggleMfa) await composableToggleMfa()
  mfaEnabled.value = !mfaEnabled.value
  notify(mfaEnabled.value ? 'تم تفعيل المصادقة الثنائية' : 'تم إلغاء تفعيل المصادقة الثنائية')
}

// Executive Member: surface MFA enrollment prompt prominently when MFA not yet enabled
const showMfaEnrollmentPrompt = computed(() =>
  user.value?.role === UserRole.EXECUTIVE_MEMBER && !user.value?.mfa_enabled,
)

function requestMfaActivation() {
  navigateTo('/mfa-setup')
}

function userInitials(n?: string) {
  if (!n) return '?'
  return n.split(' ').map(p => p[0]).slice(0, 2).join('')
}
</script>

<template>
  <div v-if="user" class="mx-auto w-full max-w-6xl px-4">
    <h1 class="page-title sr-only">الملف الشخصي</h1>
    <PageHeader
      title="الملف الشخصي"
      subtitle="معلومات الحساب وإعدادات الأمان"
    />

    <div class="grid gap-6 lg:grid-cols-3">
      <Card class="border border-border p-6 text-center">
        <Avatar class="mx-auto h-24 w-24">
          <AvatarFallback data-testid="avatar-initials" class="bg-primary text-3xl font-bold text-primary-foreground">
            {{ userInitials(user.name) }}
          </AvatarFallback>
        </Avatar>
        <div data-testid="profile-name" class="mt-4 flex items-center justify-center gap-1.5 text-lg font-bold">
          {{ user.name }}
          <BadgeCheck class="h-4 w-4 text-primary" />
        </div>
        <Badge
          variant="secondary"
          class="badge-role mt-1"
        >
          {{ ROLE_LABELS[user.role] }}
        </Badge>
        <div class="mt-2 text-xs text-muted-foreground">
          {{ user.bank_name_ar }}
        </div>

        <div
          v-if="stats.length > 0"
          data-testid="stats-strip"
          class="mt-6 grid gap-3 border-t border-border pt-6"
          :class="stats.length === 4 ? 'grid-cols-4' : 'grid-cols-3'"
        >
          <div
            v-for="(stat, idx) in stats"
            :key="stat.label"
            :data-testid="idx === 0 ? 'stats-total' : idx === 1 ? 'stats-in-progress' : 'stats-completed'"
          >
            <div class="font-bold tabular-nums">
              {{ stat.value }}
            </div>
            <div class="text-[10px] text-muted-foreground">
              {{ stat.label }}
            </div>
          </div>
        </div>

        <!-- Quick stat rows -->
        <div class="mt-4 space-y-1.5 border-t border-border pt-4 text-sm">
          <div data-testid="stat-last-login" class="flex items-center justify-between text-xs text-muted-foreground">
            <span>آخر دخول</span>
            <span>—</span>
          </div>
          <div data-testid="stat-total-actions" class="flex items-center justify-between text-xs text-muted-foreground">
            <span>إجمالي الإجراءات</span>
            <span>{{ profile?.stats?.total ?? 0 }}</span>
          </div>
        </div>

        <div class="mt-6 space-y-2 border-t border-border pt-6 text-end">
          <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <Mail class="h-3.5 w-3.5 shrink-0" />
            <span data-testid="profile-email" class="truncate">{{ user.email }}</span>
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

      <Card class="space-y-5 border border-border p-6 lg:col-span-2">
        <div>
          <h3 class="font-semibold">
            المعلومات الأساسية
          </h3>
          <p class="mt-0.5 text-xs text-muted-foreground">
            حدّث بياناتك الشخصية وطرق التواصل
          </p>
        </div>

        <form class="space-y-5" @submit.prevent="saveProfile">
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

        <!-- Executive Member: prominent MFA enrollment prompt when MFA not enabled -->
        <div
          v-if="showMfaEnrollmentPrompt"
          class="flex items-start gap-3 rounded-md border border-[var(--severity-amber)]/50 bg-[var(--severity-amber)]/8 p-4"
          role="alert"
          data-testid="mfa-enrollment-prompt"
        >
          <AlertTriangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-[var(--severity-amber)]" aria-hidden="true" />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-[var(--severity-amber)]">
              المصادقة الثنائية غير مفعّلة
            </p>
            <p class="mt-1 text-xs text-foreground leading-relaxed">
              كعضو في اللجنة التنفيذية، يُلزمك النظام بتفعيل المصادقة الثنائية (MFA) لحماية جلسات التصويت.
              لن تتمكن من التصويت في الجلسات القادمة حتى تُفعّل هذه الميزة.
            </p>
            <Button
              size="sm"
              class="mt-2 bg-[var(--severity-amber)] text-white hover:bg-[var(--severity-amber)]/90"
              @click="requestMfaActivation"
            >
              <Shield class="ms-1 h-4 w-4" />
              تفعيل المصادقة الثنائية الآن
            </Button>
          </div>
        </div>

        <div class="flex flex-wrap gap-2 border-t border-border pt-4">
          <Button type="submit" :disabled="loading">
            <Save class="ms-1 h-4 w-4" />
            حفظ التغييرات
          </Button>
          <Button
            data-testid="mfa-toggle-btn"
            variant="ghost"
            type="button"
            @click="toggleMfa"
          >
            <Shield class="ms-1 h-4 w-4" />
            {{ mfaEnabled ? 'إلغاء المصادقة الثنائية' : 'تفعيل المصادقة الثنائية' }}
          </Button>
        </div>
        </form>

        <!-- Password change form -->
        <div class="border-t border-border pt-4">
          <h3 class="mb-3 font-semibold">تغيير كلمة المرور</h3>
          <div
            v-if="passwordSuccess"
            class="success-banner mb-3 rounded-lg border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/5 p-3 text-sm text-[var(--severity-green)]"
          >
            تم تغيير كلمة المرور بنجاح
          </div>
          <form data-testid="password-form" class="space-y-3" @submit.prevent="submitPasswordChange">
            <div class="space-y-1.5">
              <Label>كلمة المرور الحالية</Label>
              <Input v-model="passwordForm.current_password" type="password" />
            </div>
            <div class="space-y-1.5">
              <Label>كلمة المرور الجديدة</Label>
              <Input v-model="passwordForm.password" type="password" />
            </div>
            <div class="space-y-1.5">
              <Label>تأكيد كلمة المرور</Label>
              <Input v-model="passwordForm.password_confirmation" type="password" />
            </div>
            <Button type="submit" variant="outline" size="sm">
              <KeyRound class="ms-1 h-4 w-4" />
              حفظ كلمة المرور
            </Button>
          </form>
        </div>

        <div data-testid="recent-activity" class="border-t border-border pt-4">
          <h3 class="mb-3 flex items-center gap-2 font-semibold">
            <Activity class="h-4 w-4" />
            آخر نشاطي
          </h3>
          <div
            v-if="loading"
            class="space-y-2"
          >
            <div v-for="i in 3" :key="i" class="flex items-center gap-3 p-2.5">
              <Skeleton class="h-8 w-8 rounded-lg" />
              <div class="flex-1 space-y-1">
                <Skeleton class="h-4 w-32" />
                <Skeleton class="h-3 w-24" />
              </div>
            </div>
          </div>
          <div
            v-else-if="myActivity.length === 0"
            data-testid="activity-empty"
            class="py-6 text-center text-sm text-muted-foreground"
          >
            لا يوجد نشاط مسجل بعد.
          </div>
          <ul
            v-else
            data-testid="recent-activity-list"
            class="space-y-1.5"
          >
            <li
              v-for="entry in myActivity"
              :key="entry.id"
              class="flex items-center gap-3 rounded-lg p-2.5 transition-colors hover:bg-muted/50"
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
            </li>
          </ul>
        </div>
      </Card>
    </div>
  </div>
</template>
