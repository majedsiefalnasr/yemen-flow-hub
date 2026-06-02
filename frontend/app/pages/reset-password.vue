<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import PasswordRequirements from '@/components/shared/PasswordRequirements.vue'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

definePageMeta({
  layout: false,
  middleware: ['guest'],
})

const route = useRoute()
const isSubmitting = ref(false)
const isDone = ref(false)
const serverError = ref<string | null>(null)

const schema = toTypedSchema(z.object({
  email: z.string().min(1, 'البريد الإلكتروني مطلوب').email('صيغة البريد الإلكتروني غير صحيحة'),
  otp: z.string().regex(/^\d{6}$/, 'أدخل رمز التحقق المكوّن من 6 أرقام'),
  password: z.string()
    .min(8, 'كلمة المرور يجب أن تكون 8 أحرف على الأقل')
    .regex(/[A-Z]/, 'يجب أن تحتوي كلمة المرور على حرف كبير')
    .regex(/[a-z]/, 'يجب أن تحتوي كلمة المرور على حرف صغير')
    .regex(/\d/, 'يجب أن تحتوي كلمة المرور على رقم')
    .regex(/[^A-Z0-9]/i, 'يجب أن تحتوي كلمة المرور على رمز خاص'),
  password_confirmation: z.string().min(1, 'تأكيد كلمة المرور مطلوب'),
}).refine(values => values.password === values.password_confirmation, {
  path: ['password_confirmation'],
  message: 'تأكيد كلمة المرور غير مطابق',
}))

const { handleSubmit, defineField, errors, values } = useForm({
  validationSchema: schema,
  initialValues: {
    email: typeof route.query.email === 'string' ? route.query.email : '',
  },
})

const [email, emailAttrs] = defineField('email')
const [otp, otpAttrs] = defineField('otp')
const [password, passwordAttrs] = defineField('password')
const [passwordConfirmation, passwordConfirmationAttrs] = defineField('password_confirmation')

const onSubmit = handleSubmit(async () => {
  isSubmitting.value = true
  serverError.value = null
  try {
    // Backend endpoint will be wired in a dedicated story.
    await new Promise(resolve => setTimeout(resolve, 500))
    isDone.value = true
  } catch {
    serverError.value = 'تعذر تحديث كلمة المرور الآن. تحقق من رمز التحقق ثم أعد المحاولة.'
  } finally {
    isSubmitting.value = false
  }
})
</script>

<template>
  <main class="min-h-screen bg-background px-4 py-10" >
    <div class="mx-auto w-full max-w-md">
      <Card>
        <CardHeader>
          <CardTitle>إعادة تعيين كلمة المرور</CardTitle>
          <CardDescription>أدخل رمز التحقق وكلمة المرور الجديدة للمتابعة.</CardDescription>
        </CardHeader>
        <CardContent>
          <Alert v-if="serverError" variant="destructive" class="mb-4">
            <AlertDescription>{{ serverError }}</AlertDescription>
          </Alert>

          <Alert v-if="isDone" class="mb-4">
            <AlertDescription>تم تحديث كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.</AlertDescription>
          </Alert>

          <form class="space-y-4" novalidate @submit.prevent="onSubmit">
            <div class="space-y-2">
              <Label for="reset-email">البريد الإلكتروني</Label>
              <Input
                id="reset-email"
                v-model="email"
                v-bind="emailAttrs"
                type="email"
                
                :aria-invalid="errors.email ? 'true' : undefined"
              />
              <p v-if="errors.email" class="text-xs text-destructive">{{ errors.email }}</p>
            </div>

            <div class="space-y-2">
              <Label for="reset-otp">رمز التحقق</Label>
              <Input
                id="reset-otp"
                v-model="otp"
                v-bind="otpAttrs"
                inputmode="numeric"
                maxlength="6"
                
                placeholder="123456"
                :aria-invalid="errors.otp ? 'true' : undefined"
              />
              <p v-if="errors.otp" class="text-xs text-destructive">{{ errors.otp }}</p>
            </div>

            <div class="space-y-2">
              <Label for="reset-password">كلمة المرور الجديدة</Label>
              <Input
                id="reset-password"
                v-model="password"
                v-bind="passwordAttrs"
                type="password"
                :aria-invalid="errors.password ? 'true' : undefined"
              />
              <PasswordRequirements :password="values.password ?? ''" />
              <p v-if="errors.password" class="text-xs text-destructive">{{ errors.password }}</p>
            </div>

            <div class="space-y-2">
              <Label for="reset-password-confirmation">تأكيد كلمة المرور</Label>
              <Input
                id="reset-password-confirmation"
                v-model="passwordConfirmation"
                v-bind="passwordConfirmationAttrs"
                type="password"
                :aria-invalid="errors.password_confirmation ? 'true' : undefined"
              />
              <p v-if="errors.password_confirmation" class="text-xs text-destructive">{{ errors.password_confirmation }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-2">
              <Button type="submit" :disabled="isSubmitting || isDone" class="h-10">
                <Loader2 v-if="isSubmitting" class="me-2 size-4 animate-spin" />
                {{ isSubmitting ? 'جارٍ الحفظ...' : 'حفظ كلمة المرور' }}
              </Button>
              <Button type="button" variant="outline" class="h-10" @click="navigateTo('/login')">
                العودة لتسجيل الدخول
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </main>
</template>
