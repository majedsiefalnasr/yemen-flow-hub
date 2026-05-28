<script setup lang="ts">
import { computed, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { UserRole } from '../../types/enums'
import { ROLE_LABELS, BANK_ADMIN_MANAGED_ROLES } from '../../constants/workflow'
import type { User } from '../../types/models'
import { AlertCircle } from 'lucide-vue-next'
import Dialog from '@/components/ui/dialog/Dialog.vue'
import DialogContent from '@/components/ui/dialog/DialogContent.vue'
import DialogFooter from '@/components/ui/dialog/DialogFooter.vue'
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue'
import DialogOverlay from '@/components/ui/dialog/DialogOverlay.vue'
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { FormField, FormItem, FormLabel, FormControl, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const props = defineProps<{
  staff: User | null
  saving: boolean
  serverError: string | null
}>()

const emit = defineEmits<{
  save: [data: {
    name: string
    email: string
    role: UserRole
    department: string
    password?: string
  }]
  close: []
}>()

const isEdit = computed(() => props.staff !== null)

const extendedSchema = computed(() => {
  if (isEdit.value) {
    return toTypedSchema(z.object({
      name: z.string().trim().min(1, 'الاسم الكامل مطلوب'),
      email: z.string().trim().min(1, 'البريد الإلكتروني مطلوب').email('البريد الإلكتروني غير صحيح'),
      role: z.enum([UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER], {
        errorMap: () => ({ message: 'يجب اختيار الدور الوظيفي' }),
      }),
      department: z.string().optional().default(''),
      password: z.string().default('').refine(
        value => value.length === 0 || value.length >= 8,
        'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
      ),
    }))
  }
  return toTypedSchema(z.object({
    name: z.string().trim().min(1, 'الاسم الكامل مطلوب'),
    email: z.string().trim().min(1, 'البريد الإلكتروني مطلوب').email('البريد الإلكتروني غير صحيح'),
    role: z.enum([UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER], {
      errorMap: () => ({ message: 'يجب اختيار الدور الوظيفي' }),
    }),
    department: z.string().optional().default(''),
    password: z.string().min(8, 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'),
  }))
})

const {
  handleSubmit,
  resetForm,
  meta,
} = useForm({
  validationSchema: extendedSchema,
  validateOnMount: true,
})

const isSaveDisabled = computed(() => props.saving || !meta.value.valid)

watch(() => props.staff, (staff) => {
  if (staff) {
    resetForm({
      values: {
        name: staff.name,
        email: staff.email,
        role: staff.role as UserRole.DATA_ENTRY | UserRole.BANK_REVIEWER,
        department: '',
        password: '',
      },
    })
  }
  else {
    resetForm({
      values: {
        name: '',
        email: '',
        role: undefined,
        department: '',
        password: '',
      },
    })
  }
}, { immediate: true })

function requestClose() {
  if (!props.saving) {
    emit('close')
  }
}

function onDialogOpenChange(open: boolean) {
  if (!open) requestClose()
}

const onSubmit = handleSubmit((values) => {
  const data: {
    name: string
    email: string
    role: UserRole
    department: string
    password?: string
  } = {
    name: values.name.trim(),
    email: values.email.trim(),
    role: values.role as UserRole,
    department: values.department?.trim() ?? '',
  }
  if (values.password) data.password = values.password
  emit('save', data)
})
</script>

<template>
  <Dialog :open="true" @update:open="onDialogOpenChange">
    <div class="modal-layer">
      <DialogOverlay class="modal-backdrop" @click="requestClose" />
      <DialogContent
        class="modal"
        dir="rtl"
        :aria-label="isEdit ? 'تعديل بيانات الموظف' : 'إضافة موظف جديد'"
      >
        <DialogHeader class="modal-header">
          <DialogTitle class="modal-title">
            {{ isEdit ? 'تعديل بيانات الموظف' : 'إضافة موظف جديد' }}
          </DialogTitle>
          <button class="close-btn" aria-label="إغلاق" :disabled="props.saving" @click="requestClose">
            ✕
          </button>
        </DialogHeader>

        <p class="modal-description text-xs text-muted-foreground -mt-2">
          الفصل بين الإدخال والمراجعة الداخلية مفروض تلقائياً على نفس الطلب.
        </p>

        <Alert v-if="props.serverError" variant="destructive" role="alert">
          <AlertCircle class="h-4 w-4" aria-hidden="true" />
          <AlertDescription>{{ props.serverError }}</AlertDescription>
        </Alert>

        <form class="flex flex-col gap-5" @submit.prevent="onSubmit">
          <div class="grid grid-cols-2 gap-4">
            <!-- Name -->
            <FormField name="name" v-slot="{ componentField }">
              <FormItem class="col-span-2">
                <FormLabel class="text-xs">الاسم الكامل <span class="text-destructive">*</span></FormLabel>
                <FormControl>
                  <Input v-bind="componentField" type="text" placeholder="الاسم الكامل للموظف" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <!-- Email -->
            <FormField name="email" v-slot="{ componentField }">
              <FormItem class="col-span-2">
                <FormLabel class="text-xs">البريد الإلكتروني <span class="text-destructive">*</span></FormLabel>
                <FormControl>
                  <Input v-bind="componentField" type="email" dir="ltr" placeholder="email@bank.ye" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <!-- Role -->
            <FormField name="role" v-slot="{ componentField }">
              <FormItem>
                <FormLabel class="text-xs">الدور الوظيفي <span class="text-destructive">*</span></FormLabel>
                <Select v-bind="componentField">
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="اختر الدور" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem v-for="r in BANK_ADMIN_MANAGED_ROLES" :key="r" :value="r">
                      {{ ROLE_LABELS[r] }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            </FormField>

            <!-- Department -->
            <FormField name="department" v-slot="{ componentField }">
              <FormItem>
                <FormLabel class="text-xs">القسم</FormLabel>
                <FormControl>
                  <Input v-bind="componentField" type="text" placeholder="القسم أو الإدارة (اختياري)" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <!-- Password -->
            <FormField name="password" v-slot="{ componentField }">
              <FormItem class="col-span-2">
                <FormLabel class="text-xs">
                  {{ isEdit ? 'كلمة المرور (اتركها فارغة للإبقاء على الحالية)' : 'كلمة المرور الأولية *' }}
                </FormLabel>
                <FormControl>
                  <Input v-bind="componentField" type="password" placeholder="8 أحرف على الأقل" />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>
          </div>

          <DialogFooter class="flex justify-end gap-3 pt-2">
            <Button type="button" variant="outline" :disabled="props.saving" @click="requestClose">
              إلغاء
            </Button>
            <Button type="submit" :disabled="isSaveDisabled">
              {{ props.saving ? 'جارٍ الحفظ…' : 'حفظ' }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </div>
  </Dialog>
</template>
