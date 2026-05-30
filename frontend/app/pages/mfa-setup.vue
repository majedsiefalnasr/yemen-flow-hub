<script setup lang="ts">
import { Loader2, ShieldCheck } from 'lucide-vue-next'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

definePageMeta({
  middleware: ['auth'],
})

const isSubmitting = ref(false)
const isDone = ref(false)
const method = ref<'sms' | 'authenticator'>('sms')

const schema = toTypedSchema(z.object({
  phone: z.string().min(8, 'أدخل رقم هاتف صالح'),
  code: z.string().regex(/^\d{6}$/, 'أدخل رمز تحقق مكوّن من 6 أرقام'),
}))

const { handleSubmit, defineField, errors } = useForm({
  validationSchema: schema,
})

const [phone, phoneAttrs] = defineField('phone')
const [code, codeAttrs] = defineField('code')

const onSubmit = handleSubmit(async () => {
  isSubmitting.value = true
  try {
    // Backend enablement flow will be wired in a dedicated story.
    await new Promise(resolve => setTimeout(resolve, 500))
    isDone.value = true
  } finally {
    isSubmitting.value = false
  }
})
</script>

<template>
  <div class="mx-auto w-full max-w-4xl px-4 py-6" >
    <div class="mb-4 flex items-center gap-2">
      <ShieldCheck class="size-5 text-primary" />
      <h1 class="text-xl font-semibold">إعداد المصادقة متعددة العوامل</h1>
      <Badge variant="secondary" class="rounded-full">مستقبلية</Badge>
    </div>

    <Card>
      <CardHeader>
        <CardTitle>تأمين الحساب بخطوة تحقق إضافية</CardTitle>
        <CardDescription>اتبع نفس نمط إدخال النماذج المعتمد داخل المنصة لإكمال تفعيل MFA.</CardDescription>
      </CardHeader>
      <CardContent>
        <Alert v-if="isDone" class="mb-4">
          <AlertDescription>تم حفظ إعداد المصادقة الثنائية بنجاح.</AlertDescription>
        </Alert>

        <form class="grid gap-4 md:grid-cols-2" novalidate @submit.prevent="onSubmit">
          <div class="space-y-2">
            <Label>طريقة التحقق</Label>
            <Select v-model="method">
              <SelectTrigger>
                <SelectValue placeholder="اختر طريقة التحقق" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="sms">رسالة نصية (SMS)</SelectItem>
                <SelectItem value="authenticator">تطبيق مصادقة</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2">
            <Label for="mfa-phone">رقم الهاتف</Label>
            <Input
              id="mfa-phone"
              v-model="phone"
              v-bind="phoneAttrs"
              
              placeholder="+9677..."
              :aria-invalid="errors.phone ? 'true' : undefined"
            />
            <p v-if="errors.phone" class="text-xs text-destructive">{{ errors.phone }}</p>
          </div>

          <div class="space-y-2 md:col-span-2">
            <Label for="mfa-code">رمز التحقق</Label>
            <Input
              id="mfa-code"
              v-model="code"
              v-bind="codeAttrs"
              inputmode="numeric"
              maxlength="6"
              
              placeholder="123456"
              :aria-invalid="errors.code ? 'true' : undefined"
            />
            <p v-if="errors.code" class="text-xs text-destructive">{{ errors.code }}</p>
          </div>

          <div class="md:col-span-2 flex flex-wrap gap-2 pt-2">
            <Button type="submit" class="h-10" :disabled="isSubmitting || isDone">
              <Loader2 v-if="isSubmitting" class="me-2 size-4 animate-spin" />
              {{ isSubmitting ? 'جارٍ الحفظ...' : 'تفعيل المصادقة الثنائية' }}
            </Button>
            <Button type="button" variant="outline" class="h-10" @click="navigateTo('/settings?tab=profile')">
              العودة للملف الشخصي
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
