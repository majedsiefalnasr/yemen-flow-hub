<script setup lang="ts">
import { computed, watch } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { UserRole } from '../../types/enums'
import { ROLE_LABELS, BANK_ADMIN_MANAGED_ROLES } from '../../constants/workflow'
import type { User } from '../../types/models'
import Dialog from '../ui/dialog/Dialog.vue'
import DialogContent from '../ui/dialog/DialogContent.vue'
import DialogFooter from '../ui/dialog/DialogFooter.vue'
import DialogHeader from '../ui/dialog/DialogHeader.vue'
import DialogOverlay from '../ui/dialog/DialogOverlay.vue'
import DialogTitle from '../ui/dialog/DialogTitle.vue'

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

        <p class="modal-description">
          الفصل بين الإدخال والمراجعة الداخلية مفروض تلقائياً على نفس الطلب.
        </p>

        <div v-if="props.serverError" class="server-error-banner" role="alert">
          {{ props.serverError }}
        </div>

        <form class="modal-form" @submit.prevent="onSubmit">
          <div class="form-grid">
            <div class="form-field form-field-full">
              <label class="form-label" for="staff-name">الاسم الكامل <span class="required">*</span></label>
              <input
                id="staff-name"
                v-model="name"
                v-bind="nameAttrs"
                type="text"
                class="form-input"
                :class="{ 'input-error': errors.name }"
                placeholder="الاسم الكامل للموظف"
              >
              <span v-if="errors.name" class="field-error" role="alert">{{ errors.name }}</span>
            </div>

            <div class="form-field form-field-full">
              <label class="form-label" for="staff-email">البريد الإلكتروني <span class="required">*</span></label>
              <input
                id="staff-email"
                v-model="email"
                v-bind="emailAttrs"
                type="email"
                class="form-input"
                :class="{ 'input-error': errors.email }"
                placeholder="email@bank.ye"
                dir="ltr"
              >
              <span v-if="errors.email" class="field-error" role="alert">{{ errors.email }}</span>
            </div>

            <div class="form-field">
              <label class="form-label" for="staff-role">الدور الوظيفي <span class="required">*</span></label>
              <select
                id="staff-role"
                v-model="role"
                v-bind="roleAttrs"
                class="form-input"
                :class="{ 'input-error': errors.role }"
              >
                <option :value="undefined" disabled>اختر الدور</option>
                <option v-for="r in BANK_ADMIN_MANAGED_ROLES" :key="r" :value="r">
                  {{ ROLE_LABELS[r] }}
                </option>
              </select>
              <span v-if="errors.role" class="field-error" role="alert">{{ errors.role }}</span>
            </div>

            <div class="form-field">
              <label class="form-label" for="staff-department">القسم</label>
              <input
                id="staff-department"
                v-model="department"
                v-bind="departmentAttrs"
                type="text"
                class="form-input"
                placeholder="القسم أو الإدارة (اختياري)"
              >
            </div>

            <div class="form-field form-field-full">
              <label class="form-label" for="staff-password">
                {{ isEdit ? 'كلمة المرور (اتركها فارغة للإبقاء على الحالية)' : 'كلمة المرور الأولية *' }}
              </label>
              <input
                id="staff-password"
                v-model="password"
                v-bind="passwordAttrs"
                type="password"
                class="form-input"
                :class="{ 'input-error': errors.password }"
                placeholder="8 أحرف على الأقل"
              >
              <span v-if="errors.password" class="field-error" role="alert">{{ errors.password }}</span>
            </div>
          </div>

          <DialogFooter class="modal-actions">
            <button type="button" class="btn-secondary" :disabled="props.saving" @click="requestClose">
              إلغاء
            </button>
            <button type="submit" class="btn-primary" :disabled="isSaveDisabled">
              {{ props.saving ? 'جارٍ الحفظ…' : 'حفظ' }}
            </button>
          </DialogFooter>
        </form>
      </DialogContent>
    </div>
  </Dialog>
</template>

<style scoped>
.modal-layer {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
}

.modal {
  position: relative;
  z-index: 1;
  background: #ffffff;
  border-radius: 24px;
  padding: 32px;
  width: 560px;
  max-width: 90vw;
  max-height: 90vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-description {
  font-size: 13px;
  color: #6c757d;
  margin: -8px 0 0 0;
  line-height: 1.5;
}

.modal-title {
  font-size: 20px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  font-size: 18px;
  color: #6c757d;
  cursor: pointer;
  line-height: 1;
  padding: 4px;
}

.close-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.server-error-banner {
  background: #fff0ef;
  border: 1px solid #c62828;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: #c62828;
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-field-full {
  grid-column: 1 / -1;
}

.form-label {
  font-size: 13px;
  color: #6c757d;
  font-weight: 500;
}

.required {
  color: #c62828;
}

.form-input {
  height: 40px;
  padding: 0 12px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  font-size: 14px;
  color: #1c222b;
  background: #ffffff;
  outline: none;
  width: 100%;
  box-sizing: border-box;
  font-family: inherit;
}

.form-input:focus {
  border-color: #0066cc;
}

.input-error {
  border-color: #c62828;
}

.field-error {
  font-size: 12px;
  color: #c62828;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn-primary {
  height: 44px;
  padding: 0 24px;
  background: #0066cc;
  color: #ffffff;
  border: none;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-secondary {
  height: 44px;
  padding: 0 24px;
  background: transparent;
  color: #1c222b;
  border: 1px solid #cccccc;
  border-radius: 16px;
  font-size: 14px;
  cursor: pointer;
}

.btn-secondary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
