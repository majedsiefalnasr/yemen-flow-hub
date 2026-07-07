import { describe, it, expect } from 'vitest'
import {
  extractApiErrorCode,
  extractApiErrorMessage,
  extractApiFieldErrors,
  extractRequestId,
} from '@/utils/apiErrors'

describe('apiErrors', () => {
  it('extracts message from rich envelope', () => {
    const err = { data: { error: { code: 'VALIDATION_FAILED', message: 'خطأ' } } }
    expect(extractApiErrorMessage(err, 'fallback')).toBe('خطأ')
    expect(extractApiErrorCode(err)).toBe('VALIDATION_FAILED')
  })

  it('extracts message and code from legacy envelope', () => {
    const err = { data: { message: 'قديم', error_code: 'OLD' } }
    expect(extractApiErrorMessage(err, 'fallback')).toBe('قديم')
    expect(extractApiErrorCode(err)).toBe('OLD')
  })

  it('extracts request_id from rich and legacy envelopes', () => {
    expect(extractRequestId({ data: { error: { request_id: 'req-rich' } } })).toBe('req-rich')
    expect(extractRequestId({ data: { request_id: 'req-legacy' } })).toBe('req-legacy')
  })

  it('extracts field errors from rich envelope fields', () => {
    const err = {
      data: {
        error: {
          code: 'VALIDATION_FAILED',
          message: 'Validation failed.',
          fields: { name: ['Name is required.'], email: 'Invalid email.' },
        },
      },
    }

    expect(extractApiFieldErrors(err)).toEqual({
      name: 'Name is required.',
      email: 'Invalid email.',
    })
  })

  it('extracts field errors from legacy errors map', () => {
    const err = {
      data: {
        success: false,
        message: 'Validation failed.',
        errors: { email: ['Invalid email.'] },
      },
    }

    expect(extractApiFieldErrors(err)).toEqual({ email: 'Invalid email.' })
  })

  it('falls back when payload is missing', () => {
    expect(extractApiErrorMessage({}, 'fallback')).toBe('fallback')
    expect(extractApiErrorCode({})).toBeNull()
    expect(extractRequestId({})).toBeNull()
    expect(extractApiFieldErrors({})).toEqual({})
  })
})
