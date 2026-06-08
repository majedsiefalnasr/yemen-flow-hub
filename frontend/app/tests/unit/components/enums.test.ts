import { describe, expect, it } from 'vitest'
import {
  CoverageType,
  CurrencySource,
  Incoterm,
  InvoiceType,
  PaymentTermsMode,
  PortOfArrival,
  RequestType,
} from '../../../types/enums'

describe('new request enum value sets', () => {
  it('matches backend request, coverage, currency-source, payment-terms, and invoice cases', () => {
    expect(Object.values(RequestType)).toEqual([
      'GOODS_IMPORT',
      'RAW_MATERIAL_IMPORT',
      'EQUIPMENT_IMPORT',
    ])
    expect(Object.values(CoverageType)).toEqual(['FULL', 'PARTIAL'])
    expect(Object.values(CurrencySource)).toEqual([
      'OWN_FUNDS',
      'BANK_FINANCING',
      'EXTERNAL_FINANCING',
    ])
    expect(Object.values(PaymentTermsMode)).toEqual([
      'ADVANCE_PAYMENT',
      'LETTER_OF_CREDIT',
      'DOCUMENTARY_COLLECTION',
      'DEFERRED_PAYMENT',
    ])
    expect(Object.values(InvoiceType)).toEqual(['PROFORMA', 'COMMERCIAL', 'FINAL'])
  })

  it('matches backend port and incoterm cases', () => {
    expect(Object.values(PortOfArrival)).toEqual([
      'ADEN',
      'HODEIDAH',
      'MUKALLA',
      'MOKHA',
      'NISHTUN',
    ])
    expect(Object.values(Incoterm)).toEqual([
      'EXW',
      'FCA',
      'CPT',
      'CIP',
      'DAP',
      'DPU',
      'DDP',
      'FAS',
      'FOB',
      'CFR',
      'CIF',
    ])
  })
})
