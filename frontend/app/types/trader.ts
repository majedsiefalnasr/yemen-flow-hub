import * as z from 'zod'
import { UserRole } from './enums'

export const TRADER_MANAGEMENT_ROLES = [
  UserRole.DATA_ENTRY,
  UserRole.BANK_REVIEWER,
  UserRole.BANK_ADMIN,
] as const

export type TraderManagementRole = (typeof TRADER_MANAGEMENT_ROLES)[number]

export interface TraderCompany {
  id?: number
  company_name: string
}

export interface TraderOwner {
  id?: number
  full_name: string
  ownership_percentage: number
  nationality?: string | null
  identification_number?: string | null
}

export interface Trader {
  id: number
  tax_number: string
  trader_name: string
  tax_card_expiry: string | null
  commercial_registration_number: string
  commercial_registration_expiry: string | null
  companies_count?: number
  owners_count?: number
  companies: TraderCompany[]
  owners: TraderOwner[]
  created_at?: string | null
  updated_at?: string | null
}

export type CreateTraderPayload = {
  tax_number: string
  trader_name: string
  tax_card_expiry: string
  commercial_registration_number: string
  commercial_registration_expiry: string
  companies: Array<Omit<TraderCompany, 'id'>>
  owners: Array<Omit<TraderOwner, 'id'>>
}

export type UpdateTraderPayload = Partial<
  Omit<CreateTraderPayload, 'companies' | 'owners'> & {
    companies: TraderCompany[]
    owners: TraderOwner[]
  }
>

export type TraderLookupResult = Trader | null

export interface TradersFilter {
  tax_number?: string
  trader_name?: string
  page?: number
  per_page?: number
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginatedTraders {
  data: Trader[]
  meta: PaginationMeta
}

function optionalTraderField(maxLengthMessage: string) {
  return z.string().trim().max(255, maxLengthMessage).optional().nullable().or(z.literal(''))
}

export const traderCompanySchema = z.object({
  id: z.number().int().positive().optional(),
  company_name: z.string().trim().min(1, 'اسم الشركة مطلوب').max(255, 'اسم الشركة طويل جداً'),
})

export const traderOwnerSchema = z.object({
  id: z.number().int().positive().optional(),
  full_name: z.string().trim().min(1, 'اسم المالك مطلوب').max(255, 'اسم المالك طويل جداً'),
  ownership_percentage: z.coerce
    .number()
    .min(0, 'نسبة الملكية لا يمكن أن تكون أقل من 0')
    .max(100, 'نسبة الملكية لا يمكن أن تتجاوز 100'),
  nationality: optionalTraderField('الجنسية طويلة جداً'),
  identification_number: optionalTraderField('رقم الهوية طويل جداً'),
})

export const traderOwnersSchema = z.array(traderOwnerSchema).superRefine((owners, ctx) => {
  owners.forEach((owner, index) => {
    if (!isMajorOwner(owner)) return

    if (!owner.nationality?.trim()) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: 'الجنسية مطلوبة للمالكين بنسبة 25% أو أكثر',
        path: [index, 'nationality'],
      })
    }

    if (!owner.identification_number?.trim()) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: 'رقم الهوية مطلوب للمالكين بنسبة 25% أو أكثر',
        path: [index, 'identification_number'],
      })
    }
  })
})

export const traderFormSchema = z.object({
  tax_number: z.string().trim().min(1, 'الرقم الضريبي مطلوب').max(255, 'الرقم الضريبي طويل جداً'),
  trader_name: z.string().trim().min(1, 'اسم التاجر مطلوب').max(255, 'اسم التاجر طويل جداً'),
  tax_card_expiry: z.string().trim().min(1, 'تاريخ انتهاء البطاقة الضريبية مطلوب'),
  commercial_registration_number: z
    .string()
    .trim()
    .min(1, 'رقم السجل التجاري مطلوب')
    .max(255, 'رقم السجل التجاري طويل جداً'),
  commercial_registration_expiry: z.string().trim().min(1, 'تاريخ انتهاء السجل التجاري مطلوب'),
  companies: z.array(traderCompanySchema).default([]),
  owners: traderOwnersSchema.default([]),
})

export type TraderFormValues = z.infer<typeof traderFormSchema>

export function canManageTraders(role: UserRole | null | undefined): role is TraderManagementRole {
  return !!role && (TRADER_MANAGEMENT_ROLES as readonly UserRole[]).includes(role)
}

export function isMajorOwner(owner: Pick<TraderOwner, 'ownership_percentage'>): boolean {
  return Number(owner.ownership_percentage) >= 25
}

export function addTraderCompanyRow(rows: TraderCompany[]): TraderCompany[] {
  return [...rows, { company_name: '' }]
}

export function removeTraderCompanyRow(rows: TraderCompany[], index: number): TraderCompany[] {
  return rows.filter((_, rowIndex) => rowIndex !== index)
}

export function addTraderOwnerRow(rows: TraderOwner[]): TraderOwner[] {
  return [...rows, { full_name: '', ownership_percentage: 0 }]
}

export function removeTraderOwnerRow(rows: TraderOwner[], index: number): TraderOwner[] {
  return rows.filter((_, rowIndex) => rowIndex !== index)
}

function optionalString(value: string | null | undefined): string | null {
  const trimmed = value?.trim()
  return trimmed ? trimmed : null
}

export function buildTraderPayload(
  values: TraderFormValues,
): CreateTraderPayload | UpdateTraderPayload {
  return {
    tax_number: values.tax_number.trim(),
    trader_name: values.trader_name.trim(),
    tax_card_expiry: values.tax_card_expiry,
    commercial_registration_number: values.commercial_registration_number.trim(),
    commercial_registration_expiry: values.commercial_registration_expiry,
    companies: values.companies.map((company) => ({
      ...(company.id ? { id: company.id } : {}),
      company_name: company.company_name.trim(),
    })),
    owners: values.owners.map((owner) => ({
      ...(owner.id ? { id: owner.id } : {}),
      full_name: owner.full_name.trim(),
      ownership_percentage: Number(owner.ownership_percentage),
      nationality: optionalString(owner.nationality),
      identification_number: optionalString(owner.identification_number),
    })),
  }
}

export function traderToFormValues(trader?: Trader | null): TraderFormValues {
  return {
    tax_number: trader?.tax_number ?? '',
    trader_name: trader?.trader_name ?? '',
    tax_card_expiry: trader?.tax_card_expiry ?? '',
    commercial_registration_number: trader?.commercial_registration_number ?? '',
    commercial_registration_expiry: trader?.commercial_registration_expiry ?? '',
    companies: trader?.companies?.length ? trader.companies : [],
    owners: trader?.owners?.length ? trader.owners : [],
  }
}
