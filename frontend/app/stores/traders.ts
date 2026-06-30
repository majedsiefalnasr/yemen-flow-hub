import { defineStore } from 'pinia'
import type {
  CreateTraderPayload,
  PaginatedTraders,
  PaginationMeta,
  Trader,
  TraderLookupResult,
  TradersFilter,
  UpdateTraderPayload,
} from '../types/trader'
import { useTraders } from '../composables/useTraders'

type SavingStore = {
  saving: boolean
  error: string | null
}

async function runWhileSaving<T>(
  store: SavingStore,
  action: () => Promise<T>,
  options: { logLabel: string; errorMessage: string },
): Promise<T> {
  if (store.saving) throw new Error('حفظ قيد التنفيذ بالفعل')
  store.saving = true
  store.error = null

  try {
    return await action()
  } catch (err) {
    if (import.meta.dev) {
      console.error(`[traders.store] ${options.logLabel} failed:`, err)
    }
    store.error = options.errorMessage
    throw err
  } finally {
    store.saving = false
  }
}

export const useTradersStore = defineStore('traders', {
  state: () => ({
    traders: [] as Trader[],
    currentTrader: null as Trader | null,
    lookupResult: null as TraderLookupResult,
    loading: false,
    saving: false,
    lookingUp: false,
    error: null as string | null,
    meta: null as PaginationMeta | null,
    currentFilter: {} as TradersFilter,
    _loadToken: 0,
  }),

  getters: {
    hasNextPage: (state): boolean =>
      state.meta !== null && state.meta.current_page < state.meta.last_page,
    hasPrevPage: (state): boolean => state.meta !== null && state.meta.current_page > 1,
    currentPage: (state): number => state.meta?.current_page ?? 1,
    totalCount: (state): number => state.meta?.total ?? 0,
  },

  actions: {
    applyPage(result: PaginatedTraders): void {
      this.traders = result.data
      this.meta = result.meta
    },

    async loadTraders(filter: TradersFilter = {}): Promise<void> {
      const token = ++this._loadToken
      this.loading = true
      this.error = null
      this.currentFilter = filter

      try {
        const { list } = useTraders()
        const result = await list(filter)
        if (token !== this._loadToken) return
        this.applyPage(result)
      } catch (err) {
        if (token !== this._loadToken) return
        if (import.meta.dev) {
          console.error('[traders.store] loadTraders failed:', err)
        }
        this.error = 'تعذّر تحميل قائمة التجار.'
        this.traders = []
        this.meta = null
      } finally {
        if (token === this._loadToken) {
          this.loading = false
        }
      }
    },

    async loadTrader(id: number): Promise<void> {
      const token = ++this._loadToken
      this.loading = true
      this.error = null
      this.currentTrader = null

      try {
        const { getById } = useTraders()
        const trader = await getById(id)
        // Drop a stale response if a newer load started (rapid navigation).
        if (token !== this._loadToken) return
        this.currentTrader = trader
      } catch (err) {
        if (token !== this._loadToken) return
        if (import.meta.dev) {
          console.error('[traders.store] loadTrader failed:', err)
        }
        this.error = 'تعذّر تحميل بيانات التاجر.'
      } finally {
        if (token === this._loadToken) {
          this.loading = false
        }
      }
    },

    async createTrader(payload: CreateTraderPayload): Promise<number> {
      return runWhileSaving(
        this,
        async () => {
          const { create } = useTraders()
          const trader = await create(payload)
          this.currentTrader = trader
          return trader.id
        },
        { logLabel: 'createTrader', errorMessage: 'تعذّر إنشاء التاجر.' },
      )
    },

    async updateTrader(id: number, payload: UpdateTraderPayload): Promise<void> {
      await runWhileSaving(
        this,
        async () => {
          const { update } = useTraders()
          this.currentTrader = await update(id, payload)
        },
        { logLabel: 'updateTrader', errorMessage: 'تعذّر تحديث بيانات التاجر.' },
      )
    },

    async lookupByTaxNumber(taxNumber: string): Promise<TraderLookupResult> {
      this.lookingUp = true
      this.error = null

      try {
        const { lookupByTaxNumber } = useTraders()
        this.lookupResult = await lookupByTaxNumber(taxNumber)
        return this.lookupResult
      } catch (err) {
        if (import.meta.dev) {
          console.error('[traders.store] lookupByTaxNumber failed:', err)
        }
        this.lookupResult = null
        this.error = 'تعذّر البحث بالرقم الضريبي.'
        throw err
      } finally {
        this.lookingUp = false
      }
    },

    async nextPage(): Promise<void> {
      if (!this.hasNextPage) return
      await this.loadTraders({ ...this.currentFilter, page: this.currentPage + 1 })
    },

    async prevPage(): Promise<void> {
      if (!this.hasPrevPage) return
      await this.loadTraders({ ...this.currentFilter, page: this.currentPage - 1 })
    },
  },
})
