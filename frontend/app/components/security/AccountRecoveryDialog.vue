<script setup lang="ts">
import { ArrowRight, KeyRound, Loader2, ShieldCheck, Smartphone } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import type { User } from '@/types/models'
import { useUsers } from '@/composables/useUsers'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const props = defineProps<{
  target: User | null
}>()

const emit = defineEmits<{
  close: []
  updated: [user: User]
}>()

const { resetUserPassword, resetUserMfa, resetUserPin } = useUsers()
const step = ref<'menu' | 'password' | 'mfa' | 'pin'>('menu')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref<string | null>(null)
const operationError = ref<string | null>(null)
const saving = ref(false)

function resetStep() {
  step.value = 'menu'
  password.value = ''
  passwordConfirmation.value = ''
  error.value = null
  operationError.value = null
}

function closeAll() {
  resetStep()
  emit('close')
}

function openStep(nextStep: 'password' | 'mfa' | 'pin') {
  error.value = null
  operationError.value = null
  step.value = nextStep
}

async function submitPasswordReset() {
  if (!props.target) return
  if (password.value.length < 8 || password.value !== passwordConfirmation.value) {
    error.value = 'أدخل كلمة مرور مؤقتة من 8 أحرف على الأقل وتأكد من تطابقها.'
    return
  }

  saving.value = true
  error.value = null
  try {
    const updated = await resetUserPassword(props.target.id, {
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    emit('updated', updated)
    toast.success('تم تعيين كلمة المرور المؤقتة بنجاح.')
    resetStep()
  } catch {
    error.value = 'تعذّر إعادة تعيين كلمة المرور. تحقق من الصلاحية ثم أعد المحاولة.'
    toast.error(error.value)
  } finally {
    saving.value = false
  }
}

async function confirmSecurityReset() {
  if (!props.target || (step.value !== 'mfa' && step.value !== 'pin')) return
  saving.value = true
  operationError.value = null
  try {
    const updated =
      step.value === 'mfa'
        ? await resetUserMfa(props.target.id)
        : await resetUserPin(props.target.id)
    emit('updated', updated)
    toast.success(
      step.value === 'mfa' ? 'تمت إعادة ضبط تطبيق المصادقة بنجاح.' : 'تمت إعادة ضبط رمز PIN بنجاح.',
    )
    resetStep()
  } catch {
    operationError.value = 'تعذّر تنفيذ إعادة الضبط. تحقق من الصلاحية ثم أعد المحاولة.'
    toast.error(operationError.value)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog :open="Boolean(target)" @update:open="(open) => !open && closeAll()">
    <DialogContent v-if="target" data-testid="account-recovery-dialog" class="sm:max-w-lg">
      <DialogHeader v-if="step === 'menu'">
        <DialogTitle>استعادة الوصول للحساب</DialogTitle>
        <DialogDescription>
          اختر إجراءً مستقلاً لحساب {{ target.name }}. إعادة تعيين كلمة المرور لا تعيد ضبط المصادقة
          أو PIN.
        </DialogDescription>
      </DialogHeader>

      <template v-if="step === 'menu'">
        <div class="divide-y rounded-lg border">
          <div class="flex items-center gap-3 p-3">
            <KeyRound class="text-primary size-5 shrink-0" />
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium">كلمة المرور</p>
              <p class="text-muted-foreground text-xs">تعيين كلمة مرور مؤقتة تُغيّر عند الدخول.</p>
            </div>
            <Button size="sm" variant="outline" @click="openStep('password')">إعادة تعيين</Button>
          </div>

          <div class="flex items-center gap-3 p-3">
            <ShieldCheck class="text-primary size-5 shrink-0" />
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium">تطبيق المصادقة</p>
              <p class="text-muted-foreground text-xs">إلغاء الربط الحالي ليتم إعداده من جديد.</p>
            </div>
            <Button size="sm" variant="outline" @click="openStep('mfa')">إعادة ضبط</Button>
          </div>

          <div class="flex items-center gap-3 p-3">
            <Smartphone class="text-primary size-5 shrink-0" />
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium">رمز PIN</p>
              <p class="text-muted-foreground text-xs">إلغاء رمز PIN الحالي ليتم إنشاؤه من جديد.</p>
            </div>
            <Button size="sm" variant="outline" @click="openStep('pin')">إعادة ضبط</Button>
          </div>
        </div>
      </template>

      <template v-else-if="step === 'password'">
        <DialogHeader>
          <DialogTitle>تعيين كلمة مرور مؤقتة</DialogTitle>
          <DialogDescription>
            سيُطلب من المستخدم تغيير كلمة المرور المؤقتة قبل استخدام النظام.
          </DialogDescription>
        </DialogHeader>
        <Alert v-if="error" variant="destructive">
          <AlertDescription>{{ error }}</AlertDescription>
        </Alert>
        <div class="space-y-3">
          <div class="space-y-1.5">
            <Label>كلمة المرور المؤقتة</Label>
            <Input v-model="password" type="password" autocomplete="new-password" />
          </div>
          <div class="space-y-1.5">
            <Label>إعادة إدخال كلمة المرور</Label>
            <Input v-model="passwordConfirmation" type="password" autocomplete="new-password" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" :disabled="saving" @click="resetStep">
            <ArrowRight class="me-2 size-4" />
            رجوع
          </Button>
          <Button :disabled="saving" @click="submitPasswordReset">
            <Loader2 v-if="saving" class="me-2 size-4 animate-spin" />
            تعيين كلمة المرور
          </Button>
        </DialogFooter>
      </template>

      <template v-else>
        <DialogHeader>
          <DialogTitle>
            {{ step === 'mfa' ? 'إعادة ضبط تطبيق المصادقة' : 'إعادة ضبط رمز PIN' }}
          </DialogTitle>
          <DialogDescription>
            هذا إجراء مستقل وسيتم تسجيله في سجل التدقيق. سيحتاج المستخدم إلى إعداد وسيلة الدخول من
            جديد.
          </DialogDescription>
        </DialogHeader>
        <Alert v-if="operationError" variant="destructive">
          <AlertDescription>{{ operationError }}</AlertDescription>
        </Alert>
        <DialogFooter>
          <Button variant="outline" :disabled="saving" @click="resetStep">
            <ArrowRight class="me-2 size-4" />
            رجوع
          </Button>
          <Button :disabled="saving" @click="confirmSecurityReset">
            <Loader2 v-if="saving" class="me-2 size-4 animate-spin" />
            تأكيد إعادة الضبط
          </Button>
        </DialogFooter>
      </template>
    </DialogContent>
  </Dialog>
</template>
