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
import { useAuthStore } from '@/stores/auth.store'

definePageMeta({
  middleware: ['auth'],
})

const auth = useAuthStore()
const router = useRouter()
const isSubmitting = ref(false)
const serverError = ref<string | null>(null)

const schema = toTypedSchema(
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

const { handleSubmit, defineField, errors } = useForm({ validationSchema: schema })
const [password, passwordAttrs] = defineField('password')
const [passwordConfirmation, passwordConfirmationAttrs] = defineField('password_confirmation')

const onSubmit = handleSubmit(async (values) => {
  isSubmitting.value = true
  serverError.value = null
  try {
    await auth.changeTemporaryPassword(values.password, values.password_confirmation)
    await router.push('/dashboard')
  } catch {
    serverError.value = 'تعذّر تغيير كلمة المرور المؤقتة. اختر كلمة مرور مختلفة ثم أعد المحاولة.'
  } finally {
    isSubmitting.value = false
  }
})
</script>

<template>
  <main class="bg-background min-h-screen px-4 py-10">
    <div class="mx-auto w-full max-w-md">
      <Card>
        <CardHeader>
          <CardTitle>تغيير كلمة المرور المؤقتة</CardTitle>
          <CardDescription>
            تم تعيين كلمة مرور مؤقتة لحسابك. يجب اختيار كلمة مرور جديدة قبل استخدام النظام.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Alert v-if="serverError" variant="destructive" class="mb-4">
            <AlertDescription>{{ serverError }}</AlertDescription>
          </Alert>

          <form class="space-y-4" novalidate @submit.prevent="onSubmit">
            <div class="space-y-2">
              <Label for="temporary-password">كلمة المرور الجديدة</Label>
              <Input
                id="temporary-password"
                v-model="password"
                v-bind="passwordAttrs"
                type="password"
                autocomplete="new-password"
                :aria-invalid="errors.password ? 'true' : undefined"
              />
              <PasswordRequirements :password="password ?? ''" />
              <p v-if="errors.password" class="text-destructive text-xs">{{ errors.password }}</p>
            </div>

            <div class="space-y-2">
              <Label for="temporary-password-confirmation">إعادة إدخال كلمة المرور</Label>
              <Input
                id="temporary-password-confirmation"
                v-model="passwordConfirmation"
                v-bind="passwordConfirmationAttrs"
                type="password"
                autocomplete="new-password"
                :aria-invalid="errors.password_confirmation ? 'true' : undefined"
              />
              <p v-if="errors.password_confirmation" class="text-destructive text-xs">
                {{ errors.password_confirmation }}
              </p>
            </div>

            <Button type="submit" class="h-10 w-full" :disabled="isSubmitting">
              <Loader2 v-if="isSubmitting" class="me-2 size-4 animate-spin" />
              {{ isSubmitting ? 'جارٍ الحفظ...' : 'حفظ كلمة المرور الجديدة' }}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  </main>
</template>
