import { describe, expect, it } from 'vitest'
import { SEMANTIC_TAG_GROUPS, SEMANTIC_TAG_LABELS } from '@/constants/semanticTags'
import type { FieldSemanticTag } from '@/types/models'

const ALL_TAGS: FieldSemanticTag[] = [
  'INVOICE_NUMBER',
  'REQUESTED_PERCENTAGE',
  'MERCHANT_TAX_NUMBER',
  'SUPPLIER_NAME',
  'GOODS_DESCRIPTION',
  'PORT_OF_ENTRY',
  'AMOUNT',
  'CURRENCY',
  'MERCHANT_ID',
  'MERCHANT_COMPANY_ID',
  'MERCHANT_TAX_CARD_EXPIRY',
  'MERCHANT_COMMERCIAL_REGISTRATION_NUMBER',
  'MERCHANT_COMMERCIAL_REGISTRATION_EXPIRY',
  'MERCHANT_OWNERS',
]

describe('semanticTags', () => {
  it('groups every FieldSemanticTag value exactly once', () => {
    const flattened = SEMANTIC_TAG_GROUPS.flatMap((group) => group.tags.map((t) => t.value))
    expect(flattened.sort()).toEqual([...ALL_TAGS].sort())
    expect(new Set(flattened).size).toBe(flattened.length)
  })

  it('has three groups: التاجر, التمويل, أخرى', () => {
    expect(SEMANTIC_TAG_GROUPS.map((g) => g.label)).toEqual(['التاجر', 'التمويل', 'أخرى'])
  })

  it('places all seven MERCHANT_* tags in the التاجر group', () => {
    const merchantGroup = SEMANTIC_TAG_GROUPS.find((g) => g.label === 'التاجر')
    const merchantTags = merchantGroup?.tags.map((t) => t.value) ?? []
    expect(merchantTags.sort()).toEqual(
      [
        'MERCHANT_TAX_NUMBER',
        'MERCHANT_ID',
        'MERCHANT_COMPANY_ID',
        'MERCHANT_TAX_CARD_EXPIRY',
        'MERCHANT_COMMERCIAL_REGISTRATION_NUMBER',
        'MERCHANT_COMMERCIAL_REGISTRATION_EXPIRY',
        'MERCHANT_OWNERS',
      ].sort(),
    )
  })

  it('SEMANTIC_TAG_LABELS has a non-empty Arabic label for every tag', () => {
    for (const tag of ALL_TAGS) {
      expect(SEMANTIC_TAG_LABELS[tag]).toBeTruthy()
      expect(SEMANTIC_TAG_LABELS[tag].length).toBeGreaterThan(0)
    }
  })

  it('SEMANTIC_TAG_LABELS values match the labels used in SEMANTIC_TAG_GROUPS', () => {
    for (const group of SEMANTIC_TAG_GROUPS) {
      for (const tag of group.tags) {
        expect(SEMANTIC_TAG_LABELS[tag.value]).toBe(tag.label)
      }
    }
  })
})
