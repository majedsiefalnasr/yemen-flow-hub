import { z } from 'zod'
import { Currency } from '../types/enums'

export const GOODS_TYPES = [
  'مواد غذائية',
  'أدوية ومستلزمات طبية',
  'منتقلات نفطية',
  'قطع غيار',
  'أخرى',
] as const
export const PAYMENT_TERMS = ['LC', 'TT', 'CAD'] as const
export const ARRIVAL_PORTS = ['ميناء عدن', 'ميناء الحديدة', 'ميناء المكلا'] as const

export const CUSTOMS_BY_PORT: Record<string, string> = {
  'ميناء عدن': 'جمارك عدن',
  'ميناء الحديدة': 'جمارك الحديدة',
  'ميناء المكلا': 'جمارك المكلا',
}

function isFutureDate(value: string | null | undefined): boolean {
  if (!value) return true

  const dueDate = new Date(`${value}T00:00:00`)
  if (Number.isNaN(dueDate.getTime())) return false

  const today = new Date()
  today.setHours(0, 0, 0, 0)

  // Must be strictly after today — mirrors the backend rule `after:today`.
  return dueDate.getTime() > today.getTime()
}

export const step1Schema = z.object({
  goods_type: z
    .string({ required_error: 'يرجى اختيار نوع الواردات' })
    .min(1, 'يرجى اختيار نوع الواردات'),

  amount: z
    .number({
      required_error: 'يرجى إدخال مبلغ التمويل',
      invalid_type_error: 'يرجى إدخال رقم صحيح',
    })
    .min(1000, 'يجب أن يكون المبلغ 1,000 على الأقل'),

  currency: z.enum([Currency.USD, Currency.EUR, Currency.SAR, Currency.AED, Currency.CNY], {
    required_error: 'يرجى اختيار العملة',
    invalid_type_error: 'عملة غير صالحة',
  }),

  payment_terms: z.enum(PAYMENT_TERMS, {
    required_error: 'يرجى اختيار شروط الدفع',
    invalid_type_error: 'شروط الدفع غير صالحة',
  }),

  due_date: z
    .string()
    .optional()
    .nullable()
    .refine(isFutureDate, 'يجب أن يكون تاريخ الاستحقاق بعد تاريخ اليوم'),

  merchant_id: z
    .number({
      required_error: 'يرجى اختيار المستورد',
      invalid_type_error: 'يرجى اختيار المستورد',
    })
    .int()
    .positive('يرجى اختيار المستورد'),

  notes: z
    .string()
    .max(500, 'لا يمكن أن تتجاوز الملاحظات 500 حرف')
    .optional()
    .nullable()
    .default(''),
})

export const step2Schema = z.object({
  supplier_name: z
    .string({ required_error: 'يرجى إدخال اسم المورد' })
    .min(1, 'يرجى إدخال اسم المورد')
    .max(255, 'الاسم طويل جداً'),

  invoice_number: z
    .string({ required_error: 'يرجى إدخال رقم الفاتورة' })
    .min(1, 'يرجى إدخال رقم الفاتورة')
    .max(100, 'رقم الفاتورة طويل جداً'),

  origin_country: z
    .string({ required_error: 'يرجى اختيار بلد المنشأ' })
    .min(1, 'يرجى اختيار بلد المنشأ'),

  invoice_date: z
    .string({ required_error: 'يرجى إدخال تاريخ الفاتورة' })
    .min(1, 'يرجى إدخال تاريخ الفاتورة'),

  arrival_port: z
    .string({ required_error: 'يرجى اختيار ميناء الوصول' })
    .min(1, 'يرجى اختيار ميناء الوصول'),

  shipping_port: z.string().max(255).optional().nullable().default(''),

  customs_office: z.string().max(100).optional().nullable().default(''),

  bl_number: z.string().max(100).optional().nullable().default(''),
})

export const step3Schema = z.object({
  proforma_invoice: z.instanceof(File, { message: 'يرجى رفع الفاتورة الأولية' }).nullable(),
  commercial_register: z.instanceof(File, { message: 'يرجى رفع السجل التجاري' }).nullable(),
  tax_card: z.instanceof(File, { message: 'يرجى رفع البطاقة الضريبية' }).nullable(),
  extra_docs: z.instanceof(File).nullable().optional(),
})

export type Step1Values = z.infer<typeof step1Schema>
export type Step2Values = z.infer<typeof step2Schema>
export type Step3Values = z.infer<typeof step3Schema>
