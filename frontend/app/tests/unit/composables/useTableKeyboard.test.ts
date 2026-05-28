// @vitest-environment jsdom
import { ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useTableKeyboard } from '../../../composables/useTableKeyboard'

let keydownHandler: ((event: KeyboardEvent) => void) | null = null

vi.mock('@vueuse/core', () => ({
  useEventListener: (_event: string, handler: (event: KeyboardEvent) => void) => {
    keydownHandler = handler
  },
}))

function dispatchKeyboard(handlerEvent: Partial<KeyboardEvent> & { key: string }) {
  if (!keydownHandler) throw new Error('keydown handler is not registered')
  keydownHandler(handlerEvent as KeyboardEvent)
}

describe('useTableKeyboard', () => {
  beforeEach(() => {
    keydownHandler = null
  })

  it('focuses search input on "/" when user is not typing', () => {
    const input = document.createElement('input')
    const focusSpy = vi.spyOn(input, 'focus')
    const preventDefault = vi.fn()

    useTableKeyboard(ref(input))

    dispatchKeyboard({
      key: '/',
      target: document.body,
      ctrlKey: false,
      metaKey: false,
      altKey: false,
      isComposing: false,
      preventDefault,
    })

    expect(preventDefault).toHaveBeenCalledTimes(1)
    expect(focusSpy).toHaveBeenCalledTimes(1)
  })

  it('does not hijack "/" while typing in input fields', () => {
    const input = document.createElement('input')
    const focusSpy = vi.spyOn(input, 'focus')
    const preventDefault = vi.fn()
    const typingTarget = document.createElement('input')

    useTableKeyboard(ref(input))

    dispatchKeyboard({
      key: '/',
      target: typingTarget,
      ctrlKey: false,
      metaKey: false,
      altKey: false,
      isComposing: false,
      preventDefault,
    })

    expect(preventDefault).not.toHaveBeenCalled()
    expect(focusSpy).not.toHaveBeenCalled()
  })

  it('clears and blurs search input on Escape when focused', () => {
    const input = document.createElement('input')
    const blurSpy = vi.spyOn(input, 'blur')
    const onEscape = vi.fn()

    useTableKeyboard(ref(input), { onEscape })

    dispatchKeyboard({
      key: 'Escape',
      target: input,
      isComposing: false,
    })

    expect(onEscape).toHaveBeenCalledTimes(1)
    expect(blurSpy).toHaveBeenCalledTimes(1)
  })

  it('ignores key events during IME composition', () => {
    const input = document.createElement('input')
    const focusSpy = vi.spyOn(input, 'focus')
    const preventDefault = vi.fn()

    useTableKeyboard(ref(input))

    dispatchKeyboard({
      key: '/',
      target: document.body,
      ctrlKey: false,
      metaKey: false,
      altKey: false,
      isComposing: true,
      preventDefault,
    })

    expect(preventDefault).not.toHaveBeenCalled()
    expect(focusSpy).not.toHaveBeenCalled()
  })
})
