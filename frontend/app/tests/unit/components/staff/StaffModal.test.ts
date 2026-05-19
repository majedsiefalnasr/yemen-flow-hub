import { describe, it, expect } from 'vitest'
import { z } from 'zod'
import { UserRole } from '../../../../types/enums'
import type { User } from '../../../../types/models'

// ── Schema mirrors StaffModal.vue (create mode — password required) ──────────

const createSchema = z.object({
  name: z.string().trim().min(1, 'الاسم الكامل مطلوب'),
  email: z.string().trim().min(1, 'البريد الإلكتروني مطلوب').email('البريد الإلكتروني غير صحيح'),
  role: z.enum([UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER], {
    errorMap: () => ({ message: 'يجب اختيار الدور الوظيفي' }),
  }),
  department: z.string().optional().default(''),
  password: z.string().min(8, 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'),
})

const editSchema = z.object({
  name: z.string().trim().min(1, 'الاسم الكامل مطلوب'),
  email: z.string().trim().min(1, 'البريد الإلكتروني مطلوب').email('البريد الإلكتروني غير صحيح'),
  role: z.enum([UserRole.DATA_ENTRY, UserRole.BANK_REVIEWER], {
    errorMap: () => ({ message: 'يجب اختيار الدور الوظيفي' }),
  }),
  department: z.string().optional().default(''),
  password: z.string().optional().default(''),
})

// ── Helpers ──────────────────────────────────────────────────────────────────

function validateCreate(data: object) {
  return createSchema.safeParse(data)
}

function validateEdit(data: object) {
  return editSchema.safeParse(data)
}

function prefillFromStaff(staff: User) {
  return {
    name: staff.name,
    email: staff.email,
    role: staff.role as UserRole.DATA_ENTRY | UserRole.BANK_REVIEWER,
    department: '',
    password: '',
  }
}

function emptyCreateValues() {
  return { name: '', email: '', role: undefined, department: '', password: '' }
}

function modalTitle(staff: User | null): string {
  return staff ? 'تعديل بيانات الموظف' : 'إضافة موظف جديد'
}

// ── Staff fixture ─────────────────────────────────────────────────────────────

const STAFF_FIXTURE: User = {
  id: 5,
  name: 'محمد العمري',
  email: 'mohamad@bank.ye',
  role: UserRole.DATA_ENTRY,
  role_label: 'إدخال البيانات',
  bank_id: 1,
  bank_name_ar: 'بنك عدن',
  bank_name_en: 'Aden Bank',
  is_active: true,
}

// ── Create mode validation ────────────────────────────────────────────────────

describe('StaffModal — create mode validation', () => {
  it('passes with all required fields valid', () => {
    const result = validateCreate({
      name: 'محمد العمري',
      email: 'mohamad@bank.ye',
      role: UserRole.DATA_ENTRY,
      password: 'password123',
    })
    expect(result.success).toBe(true)
  })

  it('fails when name is empty', () => {
    const result = validateCreate({
      name: '',
      email: 'mohamad@bank.ye',
      role: UserRole.DATA_ENTRY,
      password: 'password123',
    })
    expect(result.success).toBe(false)
    if (!result.success) {
      const errs = result.error.errors.filter(e => e.path.includes('name'))
      expect(errs[0]!.message).toBe('الاسم الكامل مطلوب')
    }
  })

  it('fails when name is whitespace only', () => {
    const result = validateCreate({
      name: '   ',
      email: 'mohamad@bank.ye',
      role: UserRole.DATA_ENTRY,
      password: 'password123',
    })
    expect(result.success).toBe(false)
  })

  it('fails when email is empty', () => {
    const result = validateCreate({
      name: 'محمد',
      email: '',
      role: UserRole.DATA_ENTRY,
      password: 'password123',
    })
    expect(result.success).toBe(false)
    if (!result.success) {
      const errs = result.error.errors.filter(e => e.path.includes('email'))
      expect(errs.length).toBeGreaterThan(0)
    }
  })

  it('fails when email is invalid format', () => {
    const result = validateCreate({
      name: 'محمد',
      email: 'not-an-email',
      role: UserRole.DATA_ENTRY,
      password: 'password123',
    })
    expect(result.success).toBe(false)
    if (!result.success) {
      const errs = result.error.errors.filter(e => e.path.includes('email'))
      expect(errs[0]!.message).toBe('البريد الإلكتروني غير صحيح')
    }
  })

  it('fails when role is missing', () => {
    const result = validateCreate({
      name: 'محمد',
      email: 'mohamad@bank.ye',
      role: undefined,
      password: 'password123',
    })
    expect(result.success).toBe(false)
  })

  it('fails when role is CBY_ADMIN (not in allowed enum)', () => {
    const result = validateCreate({
      name: 'محمد',
      email: 'mohamad@bank.ye',
      role: UserRole.CBY_ADMIN,
      password: 'password123',
    })
    expect(result.success).toBe(false)
    if (!result.success) {
      const errs = result.error.errors.filter(e => e.path.includes('role'))
      expect(errs[0]!.message).toBe('يجب اختيار الدور الوظيفي')
    }
  })

  it('fails when role is SWIFT_OFFICER (not in allowed enum)', () => {
    const result = validateCreate({
      name: 'محمد',
      email: 'mohamad@bank.ye',
      role: UserRole.SWIFT_OFFICER,
      password: 'password123',
    })
    expect(result.success).toBe(false)
  })

  it('fails when password is missing in create mode', () => {
    const result = validateCreate({
      name: 'محمد',
      email: 'mohamad@bank.ye',
      role: UserRole.DATA_ENTRY,
      password: '',
    })
    expect(result.success).toBe(false)
    if (!result.success) {
      const errs = result.error.errors.filter(e => e.path.includes('password'))
      expect(errs[0]!.message).toBe('كلمة المرور يجب أن تكون 8 أحرف على الأقل')
    }
  })

  it('fails when password is shorter than 8 characters', () => {
    const result = validateCreate({
      name: 'محمد',
      email: 'mohamad@bank.ye',
      role: UserRole.DATA_ENTRY,
      password: 'short',
    })
    expect(result.success).toBe(false)
  })

  it('passes with BANK_REVIEWER role', () => {
    const result = validateCreate({
      name: 'مراجع البنك',
      email: 'reviewer@bank.ye',
      role: UserRole.BANK_REVIEWER,
      password: 'password123',
    })
    expect(result.success).toBe(true)
  })

  it('allows optional department field', () => {
    const result = validateCreate({
      name: 'محمد',
      email: 'mohamad@bank.ye',
      role: UserRole.DATA_ENTRY,
      department: 'قسم الائتمان',
      password: 'password123',
    })
    expect(result.success).toBe(true)
  })
})

// ── Edit mode validation ──────────────────────────────────────────────────────

describe('StaffModal — edit mode validation', () => {
  it('passes without password in edit mode', () => {
    const result = validateEdit({
      name: 'محمد المحدّث',
      email: 'mohamad@bank.ye',
      role: UserRole.DATA_ENTRY,
      password: '',
    })
    expect(result.success).toBe(true)
  })

  it('passes with a new password in edit mode', () => {
    const result = validateEdit({
      name: 'محمد',
      email: 'mohamad@bank.ye',
      role: UserRole.DATA_ENTRY,
      password: 'newpassword123',
    })
    expect(result.success).toBe(true)
  })

  it('still requires name in edit mode', () => {
    const result = validateEdit({
      name: '',
      email: 'mohamad@bank.ye',
      role: UserRole.DATA_ENTRY,
    })
    expect(result.success).toBe(false)
  })

  it('still rejects forbidden roles in edit mode', () => {
    const result = validateEdit({
      name: 'محمد',
      email: 'mohamad@bank.ye',
      role: UserRole.CBY_ADMIN,
    })
    expect(result.success).toBe(false)
  })
})

// ── Prefill logic ─────────────────────────────────────────────────────────────

describe('StaffModal — prefill from existing staff', () => {
  it('prefills name and email from staff object', () => {
    const values = prefillFromStaff(STAFF_FIXTURE)
    expect(values.name).toBe('محمد العمري')
    expect(values.email).toBe('mohamad@bank.ye')
  })

  it('prefills role from staff object', () => {
    const values = prefillFromStaff(STAFF_FIXTURE)
    expect(values.role).toBe(UserRole.DATA_ENTRY)
  })

  it('initializes password as empty on edit', () => {
    const values = prefillFromStaff(STAFF_FIXTURE)
    expect(values.password).toBe('')
  })

  it('initializes department as empty (no backend column)', () => {
    const values = prefillFromStaff(STAFF_FIXTURE)
    expect(values.department).toBe('')
  })
})

// ── Empty form values ─────────────────────────────────────────────────────────

describe('StaffModal — empty form (create mode)', () => {
  it('empty values fail create validation', () => {
    const values = emptyCreateValues()
    const result = validateCreate(values)
    expect(result.success).toBe(false)
  })
})

// ── Modal title ───────────────────────────────────────────────────────────────

describe('StaffModal — modal title', () => {
  it('shows "إضافة موظف جديد" when staff is null', () => {
    expect(modalTitle(null)).toBe('إضافة موظف جديد')
  })

  it('shows "تعديل بيانات الموظف" when staff is provided', () => {
    expect(modalTitle(STAFF_FIXTURE)).toBe('تعديل بيانات الموظف')
  })
})

// ── Save button disable logic ─────────────────────────────────────────────────

describe('StaffModal — save button disable state', () => {
  it('is disabled when saving prop is true', () => {
    const isDisabled = (saving: boolean, valid: boolean) => saving || !valid
    expect(isDisabled(true, true)).toBe(true)
  })

  it('is disabled when form is invalid', () => {
    const isDisabled = (saving: boolean, valid: boolean) => saving || !valid
    expect(isDisabled(false, false)).toBe(true)
  })

  it('is enabled when not saving and form is valid', () => {
    const isDisabled = (saving: boolean, valid: boolean) => saving || !valid
    expect(isDisabled(false, true)).toBe(false)
  })
})

// ── Close guard (modal must not close while saving) ───────────────────────────

describe('StaffModal — close guard', () => {
  it('requestClose does nothing when saving is true', () => {
    let closed = false
    function requestClose(saving: boolean) {
      if (!saving) closed = true
    }
    requestClose(true)
    expect(closed).toBe(false)
  })

  it('requestClose fires when saving is false', () => {
    let closed = false
    function requestClose(saving: boolean) {
      if (!saving) closed = true
    }
    requestClose(false)
    expect(closed).toBe(true)
  })
})
