import type { Ref } from 'vue'
import { computed, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import type { ApiResponse, Merchant, MerchantCompany, PaginatedResponse } from '../types/models'
import { useApi } from './useApi'

export interface MerchantAutofillValues {
  merchantId: number | null
  companyId: number | null
  taxCardExpiry: string | null
  commercialRegistrationNumber: string | null
  commercialRegistrationExpiry: string | null
  ownersText: string | null
}

export type MerchantAutofillStatus = 'idle' | 'loading' | 'matched' | 'no_match' | 'error'

export const MERCHANT_LOOKUP_DEBOUNCE_MS = 400

export const MERCHANT_NO_MATCH_MESSAGE =
  'لم يتم العثور على تاجر مسجّل بهذا الرقم الضريبي. يرجى التواصل مع مسؤول النظام لإضافة التاجر عبر شاشة إدارة التجار قبل المتابعة.'

export const MERCHANT_LOOKUP_ERROR_MESSAGE = 'تعذّر البحث عن التاجر. يمكنك إعادة المحاولة.'

/**
 * Debounced, exact-match tax-number → merchant lookup for the request wizard.
 * Reads only via findFieldKeyBySemanticTag-tagged fields at the call site —
 * this composable itself is workflow-agnostic and never creates merchants.
 */
export function useMerchantAutofill(taxNumber: Ref<string | null | undefined>) {
  const { get } = useApi()

  const status = ref<MerchantAutofillStatus>('idle')
  const matchedMerchant = ref<Merchant | null>(null)
  const companies = ref<MerchantCompany[]>([])
  const selectedCompanyId = ref<number | null>(null)

  // Monotonic guard: a slower, earlier lookup resolving after a newer one has
  // already landed must never overwrite the newer result.
  let requestSeq = 0
  let controller: AbortController | null = null

  // Drafts hydrate taxNumber with an already-saved value on mount; that must
  // not be treated as a fresh edit that clears sibling autofilled fields
  // before the first lookup has had a chance to confirm the match.
  let hydrated = false

  function resetMatch(): void {
    matchedMerchant.value = null
    companies.value = []
    selectedCompanyId.value = null
  }

  const runLookup = useDebounceFn(async (value: string) => {
    const seq = ++requestSeq
    controller?.abort()
    controller = new AbortController()

    status.value = 'loading'

    try {
      const response = await get<ApiResponse<Merchant[] | PaginatedResponse<Merchant>>>(
        '/api/v1/merchants',
        { query: { tax_number: value, per_page: 1 }, signal: controller.signal },
      )
      if (seq !== requestSeq) return // superseded by a newer lookup

      const payload = response.data
      const list = Array.isArray(payload) ? payload : (payload.data ?? [])
      const merchant = list.find((m) => m.tax_number === value) ?? null

      if (!merchant) {
        resetMatch()
        status.value = 'no_match'
        return
      }

      matchedMerchant.value = merchant
      companies.value = merchant.companies ?? []
      // Auto-select synchronously with the match itself — a separate watcher
      // reacting to `companies` would land one tick later, letting consumers
      // observe an intermediate state with a matched merchant but no company
      // yet (autofillValues.companyId briefly null for a single-company match).
      selectedCompanyId.value =
        companies.value.length === 1 ? (companies.value[0]?.id ?? null) : null
      status.value = 'matched'
    } catch (err) {
      if (seq !== requestSeq) return
      if (err instanceof DOMException && err.name === 'AbortError') return
      resetMatch()
      status.value = 'error'
    }
  }, MERCHANT_LOOKUP_DEBOUNCE_MS)

  watch(
    taxNumber,
    (value) => {
      const trimmed = value?.trim() ?? ''

      if (!hydrated) {
        hydrated = true
        // Initial draft value: confirm it silently instead of clearing
        // whatever was already saved for the dependent fields.
        if (trimmed) {
          status.value = 'loading'
          void runLookup(trimmed)
        }
        return
      }

      requestSeq += 1 // invalidate any in-flight lookup immediately
      controller?.abort()
      resetMatch()

      if (!trimmed) {
        status.value = 'idle'
        return
      }

      status.value = 'loading'
      void runLookup(trimmed)
    },
    { immediate: true },
  )

  const requiresCompanyChoice = computed(
    () => status.value === 'matched' && companies.value.length > 1,
  )

  // Master-data fields are never free-text: 'matched' locks them to the
  // confirmed merchant record, 'no_match' locks them empty because this
  // wizard must never let a user hand-type a merchant record into existence.
  const isMasterDataReadOnly = computed(
    () => status.value === 'matched' || status.value === 'no_match',
  )

  const blocksContinue = computed(() => status.value === 'no_match')

  const autofillValues = computed<MerchantAutofillValues>(() => {
    const selectedCompany = companies.value.find((c) => c.id === selectedCompanyId.value) ?? null
    const owners = matchedMerchant.value?.owners ?? []
    return {
      merchantId: matchedMerchant.value?.id ?? null,
      companyId: selectedCompanyId.value,
      taxCardExpiry: matchedMerchant.value?.tax_card_expiry ?? null,
      commercialRegistrationNumber: selectedCompany?.commercial_registration_number ?? null,
      commercialRegistrationExpiry: selectedCompany?.commercial_registration_expiry ?? null,
      ownersText:
        owners.length > 0
          ? owners.map((o) => `${o.name} (${o.ownership_percentage}%)`).join('\n')
          : null,
    }
  })

  return {
    status,
    matchedMerchant,
    companies,
    selectedCompanyId,
    requiresCompanyChoice,
    isMasterDataReadOnly,
    blocksContinue,
    autofillValues,
    noMatchMessage: MERCHANT_NO_MATCH_MESSAGE,
    errorMessage: MERCHANT_LOOKUP_ERROR_MESSAGE,
  }
}
