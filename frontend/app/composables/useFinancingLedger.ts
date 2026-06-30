import type { Ref } from 'vue'
import { ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import type { FinancingUtilization } from '../types/financing'
import type { ApiResponse } from '../types/models'
import { useApi } from './useApi'

type LedgerKeyInput = {
  taxNumber: Ref<string | null | undefined>
  invoiceNumber: Ref<string | null | undefined>
  excludeRequestId?: Ref<number | null | undefined>
}

export const LOW_REMAINING_THRESHOLD = 20

export const FINANCING_ADVISORY_MESSAGE =
  'النسبة المتبقية من السقف التمويلي العالمي لا تكفي لتغطية النسبة المطلوبة'

export function useFinancingLedger(keys: LedgerKeyInput) {
  const { get } = useApi()

  const usedPercent = ref<number | null>(null)
  const remainingPercent = ref<number | null>(null)
  const blocked = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)

  function resetState(): void {
    usedPercent.value = null
    remainingPercent.value = null
    blocked.value = false
    error.value = null
    loading.value = false
  }

  const fetchUtilization = useDebounceFn(async () => {
    const taxNumber = keys.taxNumber.value?.trim() ?? ''
    const invoiceNumber = keys.invoiceNumber.value?.trim() ?? ''

    if (!taxNumber || !invoiceNumber) {
      resetState()
      return
    }

    loading.value = true
    error.value = null

    try {
      const params = new URLSearchParams({
        tax_number: taxNumber,
        invoice_number: invoiceNumber,
      })

      const excludeId = keys.excludeRequestId?.value
      if (excludeId) {
        params.set('exclude_request_id', String(excludeId))
      }

      const response = await get<ApiResponse<FinancingUtilization>>(
        `/api/financing/utilization?${params.toString()}`,
      )

      usedPercent.value = response.data.used_percent
      remainingPercent.value = response.data.remaining_percent
      blocked.value = response.data.blocked
    } catch {
      usedPercent.value = null
      remainingPercent.value = null
      blocked.value = false
      error.value = 'تعذّر تحميل مؤشر التمويل. يمكنك متابعة تعبئة النموذج.'
    } finally {
      loading.value = false
    }
  }, 400)

  watch(
    () => [keys.taxNumber.value, keys.invoiceNumber.value, keys.excludeRequestId?.value ?? null],
    () => {
      void fetchUtilization()
    },
    { immediate: true },
  )

  return {
    usedPercent,
    remainingPercent,
    blocked,
    loading,
    error,
    refresh: fetchUtilization,
  }
}
