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

  supplier_name: z.string()
    .min(1, 'يرجى إدخال اسم المورد')
    .max(255, 'الاسم طويل جداً'),

  goods_description: z.string()
    .min(1, 'يرجى وصف البضائع'),

  port_of_entry: z.string()
    .min(1, 'يرجى إدخال ميناء الدخول')
    .max(255, 'الاسم طويل جداً'),

  notes: z.string().optional().default(''),
})

export type RequestFormValues = z.infer<typeof requestFormSchema>
