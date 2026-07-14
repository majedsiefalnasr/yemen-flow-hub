import type { FieldSemanticTag } from '@/types/models'

export interface SemanticTagOption {
  value: FieldSemanticTag
  label: string
}

export interface SemanticTagGroup {
  label: string
  tags: SemanticTagOption[]
}

export const SEMANTIC_TAG_GROUPS: SemanticTagGroup[] = [
  {
    label: 'التاجر',
    tags: [
      { value: 'MERCHANT_TAX_NUMBER', label: 'الرقم الضريبي للتاجر' },
      { value: 'MERCHANT_ID', label: 'التاجر' },
      { value: 'MERCHANT_COMPANY_ID', label: 'الشركة المرتبطة بالتاجر' },
      { value: 'MERCHANT_TAX_CARD_EXPIRY', label: 'تاريخ انتهاء البطاقة الضريبية' },
      { value: 'MERCHANT_COMMERCIAL_REGISTRATION_NUMBER', label: 'رقم السجل التجاري' },
      { value: 'MERCHANT_COMMERCIAL_REGISTRATION_EXPIRY', label: 'تاريخ انتهاء السجل التجاري' },
      { value: 'MERCHANT_OWNERS', label: 'الملاك والمساهمون' },
    ],
  },
  {
    label: 'التمويل',
    tags: [
      { value: 'INVOICE_NUMBER', label: 'رقم الفاتورة' },
      { value: 'REQUESTED_PERCENTAGE', label: 'نسبة الطلب' },
      { value: 'AMOUNT', label: 'المبلغ' },
      { value: 'CURRENCY', label: 'العملة' },
    ],
  },
  {
    label: 'أخرى',
    tags: [
      { value: 'SUPPLIER_NAME', label: 'اسم المورّد' },
      { value: 'GOODS_DESCRIPTION', label: 'وصف السلعة' },
      { value: 'PORT_OF_ENTRY', label: 'ميناء الدخول' },
    ],
  },
]

export const SEMANTIC_TAG_LABELS: Record<FieldSemanticTag, string> = Object.fromEntries(
  SEMANTIC_TAG_GROUPS.flatMap((group) => group.tags.map((tag) => [tag.value, tag.label])),
) as Record<FieldSemanticTag, string>
