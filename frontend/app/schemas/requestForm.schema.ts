import { z } from 'zod'
import { Currency } from '../types/enums'

export const requestFormSchema = z.object({
  merchant_id: z.number({
    required_error: 'يرجى اختيار المستورد',
    invalid_type_error: 'يرجى اختيار المستورد',
  }).int().positive('يرجى اختيار المستورد'),

  currency: z.enum([Currency.USD, Currency.EUR, Currency.SAR, Currency.AED, Currency.CNY], {
    required_error: 'يرجى اختيار العملة',
    invalid_type_error: 'عملة غير صالحة',
  }),

  amount: z.number({
    required_error: 'يرجى إدخال المبلغ',
    invalid_type_error: 'يرجى إدخال رقم صحيح',
  }).positive('يجب أن يكون المبلغ أكبر من صفر'),

  goods_type: z.string().max(100, 'الاسم طويل جداً').optional(),

  payment_terms: z.enum(['LC', 'TT', 'CAD']).or(z.literal('')).optional(),

  due_date: z.string()
    .refine((value) => value === '' || !Number.isNaN(Date.parse(value)), 'تاريخ غير صالح')
    .refine((value) => {
      if (value === '') return true
      return new Date(value) > new Date(new Date().toDateString())
    }, 'يجب أن يكون تاريخ الاستحقاق في المستقبل')
    .optional(),

  supplier_name: z.string()
    .min(1, 'يرجى إدخال اسم المورد')
    .max(255, 'الاسم طويل جداً'),

  goods_description: z.string()
    .min(1, 'يرجى وصف البضائع'),

  port_of_entry: z.string()
    .min(1, 'يرجى إدخال ميناء الدخول')
    .max(255, 'الاسم طويل جداً'),

  notes: z.string().optional().default(''),
  invoice_number: z.string().max(100, 'القيمة طويلة جداً').optional(),
  invoice_date: z.string()
    .refine((value) => value === '' || !Number.isNaN(Date.parse(value)), 'تاريخ غير صالح')
    .optional(),
  origin_country: z.string().max(100, 'القيمة طويلة جداً').optional(),
  arrival_port: z.string().max(100, 'القيمة طويلة جداً').optional(),
  shipping_port: z.string().max(255, 'القيمة طويلة جداً').optional(),
  customs_office: z.string().max(100, 'القيمة طويلة جداً').optional(),
  bl_number: z.string().max(100, 'القيمة طويلة جداً').optional(),
})

export type RequestFormValues = z.infer<typeof requestFormSchema>
