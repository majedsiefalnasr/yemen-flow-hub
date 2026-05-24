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
import Label from '@/components/ui/label/Label.vue'
import { Alert, AlertDescription } from '@/components/ui/alert'

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
  errors,
  defineField,
  resetForm,
  meta,
} = useForm({
  validationSchema: extendedSchema,
  validateOnMount: true,
})

const [name, nameAttrs] = defineField('name')
const [email, emailAttrs] = defineField('email')
const [role, roleAttrs] = defineField('role')
const [department, departmentAttrs] = defineField('department')
const [password, passwordAttrs] = defineField('password')

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

        <p class="text-xs text-gray-600 -mt-2">
          الفصل بين الإدخال والمراجعة الداخلية مفروض تلقائياً على نفس الطلب.
        </p>

        <Alert v-if="props.serverError" class="border-l-4 border-l-red-600 bg-red-700/10 border-0" role="alert">
          <AlertCircle class="h-4 w-4 text-red-700" aria-hidden="true" />
          <AlertDescription class="text-red-700 text-sm">{{ props.serverError }}</AlertDescription>
        </Alert>

        <form class="flex flex-col gap-5" @submit.prevent="onSubmit">
          <div class="grid grid-cols-2 gap-4">
            <!-- Name -->
            <div class="col-span-2 flex flex-col gap-2">
              <Label for="staff-name" class="text-xs text-gray-600 font-medium">
                الاسم الكامل <span class="text-red-700">*</span>
              </Label>
              <Input
                id="staff-name"
                v-model="name"
                v-bind="nameAttrs"
                type="text"
                placeholder="الاسم الكامل للموظف"
                :class="{ 'border-destructive': errors.name }"
              />
              <span v-if="errors.name" class="text-xs text-red-700" role="alert">{{ errors.name }}</span>
            </div>

            <!-- Email -->
            <div class="col-span-2 flex flex-col gap-2">
              <Label for="staff-email" class="text-xs text-gray-600 font-medium">
                البريد الإلكتروني <span class="text-red-700">*</span>
              </Label>
              <Input
                id="staff-email"
                v-model="email"
                v-bind="emailAttrs"
                type="email"
                placeholder="email@bank.ye"
                dir="ltr"
                :class="{ 'border-destructive': errors.email }"
              />
              <span v-if="errors.email" class="text-xs text-red-700" role="alert">{{ errors.email }}</span>
            </div>

            <!-- Role -->
            <div class="flex flex-col gap-2">
              <Label for="staff-role" class="text-xs text-gray-600 font-medium">
                الدور الوظيفي <span class="text-red-700">*</span>
              </Label>
              <select
                id="staff-role"
                v-model="role"
                v-bind="roleAttrs"
                class="h-9 px-3 border border-gray-200 rounded-md bg-white text-sm text-gray-900 outline-none focus-visible:ring-1 focus-visible:ring-blue-600"
                :class="{ 'border-destructive': errors.role }"
              >
                <option :value="undefined" disabled>اختر الدور</option>
                <option v-for="r in BANK_ADMIN_MANAGED_ROLES" :key="r" :value="r">
                  {{ ROLE_LABELS[r] }}
                </option>
              </select>
              <span v-if="errors.role" class="text-xs text-red-700" role="alert">{{ errors.role }}</span>
            </div>

            <!-- Department -->
            <div class="flex flex-col gap-2">
              <Label for="staff-department" class="text-xs text-gray-600 font-medium">
                القسم
              </Label>
              <Input
                id="staff-department"
                v-model="department"
                v-bind="departmentAttrs"
                type="text"
                placeholder="القسم أو الإدارة (اختياري)"
              />
            </div>

            <!-- Password -->
            <div class="col-span-2 flex flex-col gap-2">
              <Label for="staff-password" class="text-xs text-gray-600 font-medium">
                {{ isEdit ? 'كلمة المرور (اتركها فارغة للإبقاء على الحالية)' : 'كلمة المرور الأولية *' }}
              </Label>
              <Input
                id="staff-password"
                v-model="password"
                v-bind="passwordAttrs"
                type="password"
                placeholder="8 أحرف على الأقل"
                :class="{ 'border-destructive': errors.password }"
              />
              <span v-if="errors.password" class="text-xs text-red-700" role="alert">{{ errors.password }}</span>
            </div>
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

