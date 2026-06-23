import type { PaginatedResponse } from '../types/models'
import { useApi } from './useApi'

export interface DuplicateInvoiceGroup {
  invoice_number: string
  count: number
  requests: Array<{
    id: number
    reference: string
    bank: string | null
    merchant: string | null
    amount: number | string
    currency: string | null
    status: string
    stage: string | null
    created_at: string
  }>
}

export interface ExpiredDocument {
  merchant_id: number
  merchant_name: string
  bank: string | null
  expired_documents: Array<{
    type: string
    expired_at: string
  }>
}

export interface SlaBreachEntry {
  id: number
  reference: string
  bank: string | null
  stage: string | null
  stage_code: string | null
  sla_minutes: number | null
  stage_entered_at: string | null
  sla_status: string | null
  amount: number | string
  currency: string | null
  created_at: string
}

export interface ComplianceFilters {
  bank_id?: number
  page?: number
  per_page?: number
}

export function useCompliance() {
  const { get } = useApi()

  function buildParams(filters: ComplianceFilters): string {
    const params = new URLSearchParams()
    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== null && value !== '') {
        params.set(key, String(value))
      }
    }
    return params.toString()
  }

  async function fetchDuplicateInvoices(
    filters: ComplianceFilters = {},
  ): Promise<PaginatedResponse<DuplicateInvoiceGroup>> {
    const query = buildParams(filters)
    const path = query
      ? `/api/v1/compliance/duplicate-invoices?${query}`
      : '/api/v1/compliance/duplicate-invoices'
    return get<PaginatedResponse<DuplicateInvoiceGroup>>(path)
  }

  async function fetchExpiredDocuments(
    filters: ComplianceFilters = {},
  ): Promise<PaginatedResponse<ExpiredDocument>> {
    const query = buildParams(filters)
    const path = query
      ? `/api/v1/compliance/expired-documents?${query}`
      : '/api/v1/compliance/expired-documents'
    return get<PaginatedResponse<ExpiredDocument>>(path)
  }

  async function fetchSlaBreaches(
    filters: ComplianceFilters = {},
  ): Promise<PaginatedResponse<SlaBreachEntry>> {
    const query = buildParams(filters)
    const path = query
      ? `/api/v1/compliance/sla-breaches?${query}`
      : '/api/v1/compliance/sla-breaches'
    return get<PaginatedResponse<SlaBreachEntry>>(path)
  }

  return { fetchDuplicateInvoices, fetchExpiredDocuments, fetchSlaBreaches }
}
