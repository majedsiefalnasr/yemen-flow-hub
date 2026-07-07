import { describe, expect, it } from 'vitest'
import { needsConfirmation, defaultConfirmMessage } from '@/composables/useTransitionConfirm'
import type { WorkflowGraphEdge } from '@/types/models'

describe('useTransitionConfirm', () => {
  it('needsConfirmation is true when is_destructive', () => {
    expect(
      needsConfirmation({ is_destructive: true, confirmation_message: null } as WorkflowGraphEdge),
    ).toBe(true)
  })

  it('needsConfirmation is true when confirmation_message is set', () => {
    expect(
      needsConfirmation({
        is_destructive: false,
        confirmation_message: 'هل أنت متأكد؟',
      } as WorkflowGraphEdge),
    ).toBe(true)
  })

  it('needsConfirmation is false for forward approve without message', () => {
    expect(
      needsConfirmation({ is_destructive: false, confirmation_message: null } as WorkflowGraphEdge),
    ).toBe(false)
  })

  it('defaultConfirmMessage prefers edge message', () => {
    expect(
      defaultConfirmMessage({
        confirmation_message: 'رسالة مخصصة',
      } as WorkflowGraphEdge),
    ).toBe('رسالة مخصصة')
  })
})
