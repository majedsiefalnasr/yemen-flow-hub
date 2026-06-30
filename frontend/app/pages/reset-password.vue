<script setup lang="ts">
import { onBeforeUnmount } from 'vue'
import { Building2, ChevronRight, Clock3, Loader2, Lock, MailCheck } from 'lucide-vue-next'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { toast } from 'vue-sonner'
import PasswordRequirements from '@/components/shared/PasswordRequirements.vue'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useAuthStore } from '@/stores/auth.store'
import { useOrgStore } from '@/stores/org.store'

definePageMeta({
  layout: false,
  middleware: ['guest'],
})

const route = useRoute()
const auth = useAuthStore()
const orgStore = useOrgStore()
orgStore.loadSettings()

const step = ref<'email' | 'otp' | 'password' | 'done'>('email')
const isSubmitting = ref(false)
const serverError = ref<string | null>(null)
const notice = ref<string | null>(null)
const pageDir = computed<'rtl' | 'ltr'>(() => (auth.preferredLanguage === 'en' ? 'ltr' : 'rtl'))

const genericNotice = 'إذا كان البريد موجوداً، فسيتم إرسال رمز الاستعادة.'
const resetCodeTtlSeconds = 10 * 60
const remainingSeconds = ref(resetCodeTtlSeconds)
const countdownTimer = ref<ReturnType<typeof setInterval> | null>(null)
const isRecoveryCodeExpired = computed(() => step.value === 'otp' && remainingSeconds.value <= 0)
const formattedRemainingTime = computed(() => {
  const minutes = Math.floor(Math.max(remainingSeconds.value, 0) / 60)
  const seconds = Math.max(remainingSeconds.value, 0) % 60

  return `${minutes}:${seconds.toString().padStart(2, '0')}`
})

const emailSchema = toTypedSchema(
  z.object({
    email: z
      .string()
      .min(1, 'أدخل البريد الإلكتروني المؤسسي.')
      .email('أدخل بريدا إلكترونيا صحيحا.'),
  }),
)

const otpSchema = toTypedSchema(
  z.object({
    otp: z.string().regex(/^\d{6}$/, 'أدخل رمز التحقق المكوّن من 6 أرقام.'),
  }),
)

const passwordSchema = toTypedSchema(
  z
    .object({
      password: z
        .string()
        .min(8, 'استخدم 8 أحرف على الأقل.')
        .regex(/[A-Z]/, 'أضف حرفا إنجليزيا كبيرا واحدا على الأقل.')
        .regex(/[a-z]/, 'أضف حرفا إنجليزيا صغيرا واحدا على الأقل.')
        .regex(/\d/, 'أضف رقما واحدا على الأقل.'),
      password_confirmation: z.string().min(1, 'أعد إدخال كلمة المرور الجديدة.'),
    })
    .refine((values) => values.password === values.password_confirmation, {
      path: ['password_confirmation'],
      message: 'كلمتا المرور غير متطابقتين.',
    }),
)

const emailForm = useForm({
  validationSchema: emailSchema,
  initialValues: {
    email: typeof route.query.email === 'string' ? route.query.email : '',
  },
})
const otpForm = useForm({ validationSchema: otpSchema })
const passwordForm = useForm({ validationSchema: passwordSchema })

const [email, emailAttrs] = emailForm.defineField('email')
const [otp, otpAttrs] = otpForm.defineField('otp')
const [password, passwordAttrs] = passwordForm.defineField('password')
const [passwordConfirmation, passwordConfirmationAttrs] =
  passwordForm.defineField('password_confirmation')

const goToLogin = () => navigateTo('/login')

const stopCountdown = () => {
  if (!countdownTimer.value) return
  clearInterval(countdownTimer.value)
  countdownTimer.value = null
}

const startCountdown = () => {
  stopCountdown()
  remainingSeconds.value = resetCodeTtlSeconds
  countdownTimer.value = setInterval(() => {
    remainingSeconds.value = Math.max(remainingSeconds.value - 1, 0)
    if (remainingSeconds.value === 0) {
      stopCountdown()
    }
  }, 1000)
}

const goBackFromRecoveryStep = () => {
  notice.value = null
  serverError.value = null
  stopCountdown()
  step.value = step.value === 'otp' ? 'email' : 'otp'
}

const submitEmail = emailForm.handleSubmit(async (values) => {
  isSubmitting.value = true
  serverError.value = null
  notice.value = null
  try {
    await auth.requestPasswordRecovery(values.email)
    notice.value = genericNotice
    step.value = 'otp'
    startCountdown()
  } catch {
    notice.value = genericNotice
    step.value = 'otp'
    startCountdown()
  } finally {
    isSubmitting.value = false
  }
})

const submitOtp = otpForm.handleSubmit(async (values) => {
  if (isRecoveryCodeExpired.value) {
    serverError.value = 'انتهت صلاحية رمز الاستعادة. اطلب رمزاً جديداً للمتابعة.'
    return
  }

  isSubmitting.value = true
  serverError.value = null
  try {
    await auth.verifyPasswordRecoveryCode(email.value ?? '', values.otp)
    stopCountdown()
    step.value = 'password'
  } catch {
    serverError.value = 'رمز الاستعادة غير صحيح أو منتهي الصلاحية.'
  } finally {
    isSubmitting.value = false
  }
})

const submitPassword = passwordForm.handleSubmit(async (values) => {
  isSubmitting.value = true
  serverError.value = null
  try {
    await auth.resetPasswordWithOtp(
      email.value ?? '',
      otp.value ?? '',
      values.password,
      values.password_confirmation,
    )
    toast.success('تم تحديث كلمة المرور. استخدم كلمة المرور الجديدة لتسجيل الدخول.')
    await navigateTo('/login')
  } catch {
    serverError.value = 'تعذّر تحديث كلمة المرور. تحقق من الرمز ثم أعد المحاولة.'
  } finally {
    isSubmitting.value = false
  }
})

onBeforeUnmount(stopCountdown)

defineExpose({
  email,
  otp,
  password,
  passwordConfirmation,
  step,
  submitEmail,
  submitOtp,
  submitPassword,
  goToLogin,
  goBackFromRecoveryStep,
  remainingSeconds,
  formattedRemainingTime,
  isRecoveryCodeExpired,
})
</script>

<template>
  <div class="login-page" :dir="pageDir">
    <div class="login-form-col">
      <div class="login-form-wrap">
        <Button
          v-if="step !== 'email'"
          type="button"
          variant="ghost"
          size="sm"
          class="text-muted-foreground -ms-2 mb-5 gap-1"
          @click="goBackFromRecoveryStep"
        >
          <ChevronRight class="size-4" />
          رجوع
        </Button>

        <div class="step-header">
          <h2 class="step-title">استعادة كلمة المرور</h2>
          <p class="step-desc">
            استخدم بريدك المؤسسي لاستلام رمز قصير المدة ثم اختر كلمة مرور جديدة.
          </p>
        </div>

        <Alert v-if="serverError" variant="destructive" role="alert" class="mb-4">
          <AlertDescription>{{ serverError }}</AlertDescription>
        </Alert>

        <Alert v-if="notice" role="status" class="mb-4">
          <MailCheck class="h-4 w-4" />
          <AlertDescription>{{ notice }}</AlertDescription>
        </Alert>

        <form v-if="step === 'email'" class="space-y-4" novalidate @submit.prevent="submitEmail">
          <div class="space-y-2">
            <Label for="reset-email">البريد الإلكتروني المؤسسي</Label>
            <Input
              id="reset-email"
              v-model="email"
              v-bind="emailAttrs"
              type="email"
              autocomplete="email"
              class="bg-muted/30 h-11"
              :aria-invalid="emailForm.errors.value.email ? 'true' : undefined"
            />
            <p v-if="emailForm.errors.value.email" class="text-destructive text-sm">
              {{ emailForm.errors.value.email }}
            </p>
          </div>

          <Button type="submit" size="lg" :disabled="isSubmitting" class="w-full">
            <Loader2 v-if="isSubmitting" class="me-2 size-4 animate-spin" />
            {{ isSubmitting ? 'جارٍ الإرسال...' : 'إرسال رمز الاستعادة' }}
          </Button>
        </form>

        <form v-else-if="step === 'otp'" class="space-y-4" novalidate @submit.prevent="submitOtp">
          <div class="email-chip">
            <span class="text-muted-foreground text-xs">البريد الإلكتروني</span>
            <span class="text-sm font-medium">{{ email }}</span>
          </div>

          <Alert :variant="isRecoveryCodeExpired ? 'destructive' : 'default'" role="timer">
            <Clock3 class="h-4 w-4" />
            <AlertDescription>
              <template v-if="isRecoveryCodeExpired">
                انتهت صلاحية رمز الاستعادة. ارجع واطلب رمزاً جديداً.
              </template>
              <template v-else>
                ينتهي رمز الاستعادة خلال
                <span class="font-mono" dir="ltr">{{ formattedRemainingTime }}</span>
              </template>
            </AlertDescription>
          </Alert>

          <div class="space-y-2">
            <Label for="reset-otp">رمز الاستعادة</Label>
            <Input
              id="reset-otp"
              v-model="otp"
              v-bind="otpAttrs"
              inputmode="numeric"
              maxlength="6"
              placeholder="123456"
              class="bg-muted/30 h-11"
              :aria-invalid="otpForm.errors.value.otp ? 'true' : undefined"
            />
            <p v-if="otpForm.errors.value.otp" class="text-destructive text-sm">
              {{ otpForm.errors.value.otp }}
            </p>
          </div>

          <div class="flex flex-col gap-3">
            <Button
              type="submit"
              size="lg"
              :disabled="isSubmitting || isRecoveryCodeExpired"
              class="w-full"
            >
              <Loader2 v-if="isSubmitting" class="me-2 size-4 animate-spin" />
              {{ isSubmitting ? 'جارٍ التحقق...' : 'تحقق من الرمز' }}
            </Button>
          </div>
        </form>

        <form
          v-else-if="step === 'password'"
          class="space-y-4"
          novalidate
          @submit.prevent="submitPassword"
        >
          <div class="space-y-2">
            <Label for="reset-password">كلمة المرور الجديدة</Label>
            <Input
              id="reset-password"
              v-model="password"
              v-bind="passwordAttrs"
              type="password"
              autocomplete="new-password"
              class="bg-muted/30 h-11"
              :aria-invalid="passwordForm.errors.value.password ? 'true' : undefined"
            />
            <PasswordRequirements :password="password ?? ''" />
            <p v-if="passwordForm.errors.value.password" class="text-destructive text-sm">
              {{ passwordForm.errors.value.password }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="reset-password-confirmation">إعادة إدخال كلمة المرور</Label>
            <Input
              id="reset-password-confirmation"
              v-model="passwordConfirmation"
              v-bind="passwordConfirmationAttrs"
              type="password"
              autocomplete="new-password"
              class="bg-muted/30 h-11"
              :aria-invalid="passwordForm.errors.value.password_confirmation ? 'true' : undefined"
            />
            <p
              v-if="passwordForm.errors.value.password_confirmation"
              class="text-destructive text-sm"
            >
              {{ passwordForm.errors.value.password_confirmation }}
            </p>
          </div>

          <Button type="submit" size="lg" :disabled="isSubmitting" class="w-full">
            <Loader2 v-if="isSubmitting" class="me-2 size-4 animate-spin" />
            {{ isSubmitting ? 'جارٍ تحديث كلمة المرور...' : 'تحديث كلمة المرور' }}
          </Button>
        </form>

        <div v-else class="space-y-4">
          <Alert>
            <AlertDescription>
              تم تحديث كلمة المرور. استخدم كلمة المرور الجديدة لتسجيل الدخول.
            </AlertDescription>
          </Alert>
        </div>

        <div class="mt-4 flex flex-col items-center gap-1">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="text-muted-foreground hover:text-foreground text-xs"
            @click="goToLogin"
          >
            <Lock class="me-1.5 size-3.5" />
            تسجيل الدخول بدلا من استعادة كلمة المرور
          </Button>
        </div>

        <div class="login-footer">
          <Building2 class="size-3.5 shrink-0" aria-hidden="true" />
          {{ orgStore.authority }}، {{ orgStore.platformName }} v3.0
        </div>
      </div>
    </div>

    <div class="login-hero" aria-hidden="true">
      <div>
        <div class="hero-brand">
          <div v-if="orgStore.brandLogoDataUrl" class="hero-monogram-logo">
            <img :src="orgStore.brandLogoDataUrl" alt="Logo" class="h-full w-full object-contain" />
          </div>
          <div v-else class="hero-monogram">ل.و</div>
          <div>
            <p class="hero-brand-ar">{{ orgStore.authority }}</p>
            <p class="hero-brand-en">The National Committee for Regulating & Financing Imports</p>
          </div>
        </div>
      </div>
      <div class="hero-main">
        <h1>منصة إدارة ومراجعة طلبات تمويل الواردات</h1>
        <p>
          نظام موحّد لإدارة التقديم، المراجعة البنكية، مراجعة الدعم، رفع SWIFT، التصويت التنفيذي،
          وإصدار تأكيد الصرف الخارجي.
        </p>
      </div>
      <p class="hero-iso">ISO 27001 compliant | Secure institutional workflow</p>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  display: grid;
  grid-template-columns: 1fr 1fr;
  min-height: 100dvh;
  background-color: var(--background);
}

.login-hero {
  background-color: var(--primary);
  color: var(--primary-foreground);
  padding: 40px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: sticky;
  top: 0;
  align-self: start;
  height: 100dvh;
}

.hero-brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hero-monogram {
  height: 56px;
  width: 56px;
  border-radius: 16px;
  background: rgb(255 255 255 / 10%);
  border: 1px solid rgb(255 255 255 / 20%);
  display: grid;
  place-items: center;
  font-size: 24px;
  font-weight: 700;
  flex-shrink: 0;
}

.hero-monogram-logo {
  height: 56px;
  width: 56px;
  overflow: hidden;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.hero-brand-ar {
  margin: 0;
  font-weight: 600;
  color: var(--primary-foreground);
}

.hero-brand-en {
  margin: 0;
  color: rgb(255 255 255 / 80%);
  font-size: 12px;
}

.hero-main {
  max-width: 460px;
}

.hero-main h1 {
  margin: 0 0 12px;
  color: var(--primary-foreground);
  font-size: 32px;
  font-weight: 800;
  line-height: 1.35;
}

.hero-main p {
  margin: 0;
  color: rgb(255 255 255 / 80%);
  line-height: 1.8;
  font-size: 15px;
}

.hero-iso {
  margin: 0;
  color: rgb(255 255 255 / 50%);
  font-size: 11px;
}

.login-form-col {
  background: var(--background);
  display: flex;
  flex-direction: column;
  padding: 40px 24px;
}

.login-form-wrap {
  width: 100%;
  max-width: 420px;
  margin: auto;
}

.step-header {
  margin-bottom: 24px;
}

.step-title {
  margin: 0 0 6px;
  font-size: 26px;
  font-weight: 700;
  color: var(--foreground);
}

.step-desc {
  margin: 0;
  font-size: 14px;
  color: var(--muted-foreground);
  line-height: 1.5;
}

.email-chip {
  display: flex;
  flex-direction: column;
  gap: 2px;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 10px 14px;
  background: color-mix(in srgb, var(--muted) 25%, var(--background));
}

.login-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 32px;
  font-size: 11px;
  color: var(--muted-foreground);
  text-align: center;
}

@media (max-width: 1023px) {
  .login-page {
    grid-template-columns: 1fr;
  }

  .login-hero {
    display: none;
    position: static;
    height: auto;
  }
}

@media (max-width: 480px) {
  .login-form-col {
    padding: 20px 12px;
  }

  .step-title {
    font-size: 22px;
  }
}
</style>
