// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { ResolvedFieldGroup } from '@/types/models'
import EngineRequestWizard from '@/components/workflow/EngineRequestWizard.vue'

const mockPostWithMeta = vi.fn()
const mockUploadTemporary = vi.fn()
const mockUploadStatus = vi.fn()

vi.mock('@/composables/useApi', () => ({
  useApi: () => ({
    get: vi.fn(),
    post: vi.fn(),
    postWithMeta: mockPostWithMeta,
    put: vi.fn(),
    patch: vi.fn(),
    del: vi.fn(),
  }),
}))

vi.mock('@/composables/useTemporaryUploads', () => ({
  useTemporaryUploads: () => ({
    upload: mockUploadTemporary,
    status: mockUploadStatus,
    release: vi.fn(),
  }),
}))

function headers(entries: Record<string, string> = {}): Headers {
  return new Headers(entries)
}

function fieldGroups(): ResolvedFieldGroup[] {
  return [
    {
      id: 1,
      name: 'basic',
      label: 'المعلومات الأساسية',
      sort_order: 0,
      fields: [
        {
          id: 1,
          key: 'importer_name',
          semantic_tag: null,
          label: 'اسم المستورد',
          type: 'TEXT',
          placeholder: null,
          help_text: null,
          default_value: null,
          min_value: null,
          max_value: null,
          min_length: null,
          max_length: null,
          regex_pattern: null,
          options: null,
          dynamic_source: null,
          allowed_file_types: null,
          max_file_size: null,
          multiple: false,
          is_visible: true,
          is_editable: true,
          is_required: true,
          dynamic_options: null,
        },
      ],
    },
    {
      id: 2,
      name: 'documents',
      label: 'المستندات',
      sort_order: 1,
      fields: [
        {
          id: 2,
          key: 'supporting_doc',
          semantic_tag: null,
          label: 'مستند داعم',
          type: 'FILE',
          placeholder: null,
          help_text: null,
          default_value: null,
          min_value: null,
          max_value: null,
          min_length: null,
          max_length: null,
          regex_pattern: null,
          options: null,
          dynamic_source: null,
          allowed_file_types: null,
          max_file_size: null,
          multiple: false,
          is_visible: true,
          is_editable: true,
          is_required: false,
          dynamic_options: null,
        },
      ],
    },
  ]
}

// A single flushPromises() (one macrotask tick via setImmediate) isn't
// enough to settle EngineRequestWizard's async @click handlers:
// formRef.validate() chains through vee-validate's async Zod resolution,
// which needs a few elapsed macrotask ticks to fully resolve in this
// harness. Under vi.useFakeTimers(), a real setTimeout would never fire on
// its own, so this advances fake timers when active and otherwise waits on
// a real timer — either way draining the same number of ticks.
async function settle() {
  for (let i = 0; i < 5; i++) {
    await flushPromises()
    if (vi.isFakeTimers()) {
      await vi.advanceTimersByTimeAsync(20)
    } else {
      await new Promise((resolve) => setTimeout(resolve, 10))
    }
  }
}

async function mountWizard() {
  const wrapper = mount(EngineRequestWizard, {
    props: { workflowVersionId: 10, merchantId: null, fieldGroups: fieldGroups() },
  })
  await settle()
  return wrapper
}

// Advances from step 0 (basic info, required text field) through step 1
// (optional file field, left empty) into the review step.
async function advanceToReview(wrapper: Awaited<ReturnType<typeof mountWizard>>) {
  const input = wrapper.find('input#importer_name')
  await input.setValue('شركة الاختبار')
  await settle()
  const nextButton = wrapper.findAll('button').find((b) => b.text() === 'التالي')
  await nextButton!.trigger('click')
  await settle()

  const reviewButton = wrapper.findAll('button').find((b) => b.text() === 'مراجعة الطلب')
  await reviewButton!.trigger('click')
  await settle()
}

// Advances from step 0 (basic info) to step 1 (the FILE field step) only,
// without going all the way to review — used by the upload-lifecycle tests,
// which need to be ON the documents step to attach a file.
async function advanceToDocumentsStep(wrapper: Awaited<ReturnType<typeof mountWizard>>) {
  await wrapper.find('input#importer_name').setValue('شركة الاختبار')
  await settle()
  const nextButton = wrapper.findAll('button').find((b) => b.text() === 'التالي')
  await nextButton!.trigger('click')
  await settle()
}

describe('EngineRequestWizard', () => {
  beforeEach(() => {
    mockPostWithMeta.mockReset()
    mockUploadTemporary.mockReset()
    mockUploadStatus.mockReset()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  describe('submission completion and 202 retry handling', () => {
    it('completes on a plain 201 and emits submitted with the created request id', async () => {
      mockPostWithMeta.mockResolvedValue({
        data: { success: true, data: { id: 77 }, warnings: [] },
        status: 201,
        headers: headers(),
      })

      const wrapper = await mountWizard()
      await advanceToReview(wrapper)

      const submitButton = wrapper.findAll('button').find((b) => b.text() === 'إرسال الطلب')
      await submitButton!.trigger('click')
      await settle()

      expect(wrapper.emitted('submitted')).toEqual([[77]])
      expect(mockPostWithMeta).toHaveBeenCalledTimes(1)
    })

    it('202 then 201: retries with the same frozen payload and key, then completes', async () => {
      let call = 0
      mockPostWithMeta.mockImplementation(async () => {
        call += 1
        if (call === 1) {
          return {
            data: { status: 'processing' },
            status: 202,
            headers: headers({ 'Retry-After': '2' }),
          }
        }
        return {
          data: { success: true, data: { id: 88 }, warnings: [] },
          status: 201,
          headers: headers(),
        }
      })

      const wrapper = await mountWizard()
      await advanceToReview(wrapper)
      // Fake timers only from here: the 202 retry delay is the only thing
      // this test needs to fast-forward. Starting fake timers earlier (e.g.
      // before mount) leaves vee-validate's async Zod validation chain
      // unable to settle via flushPromises() during advanceToReview.
      vi.useFakeTimers()

      const submitButton = wrapper.findAll('button').find((b) => b.text() === 'إرسال الطلب')
      await submitButton!.trigger('click')
      await flushPromises()

      // Still retrying: emitted nothing yet, and the retry message shows.
      expect(wrapper.emitted('submitted')).toBeUndefined()
      expect(wrapper.text()).toContain('يتم إعادة محاولة إرسال الطلب تلقائياً')

      await vi.advanceTimersByTimeAsync(2000)
      await flushPromises()

      expect(wrapper.emitted('submitted')).toEqual([[88]])
      expect(mockPostWithMeta).toHaveBeenCalledTimes(2)

      // Same Idempotency-Key header on both calls.
      const firstKey = mockPostWithMeta.mock.calls[0]?.[2]?.headers?.['Idempotency-Key']
      const secondKey = mockPostWithMeta.mock.calls[1]?.[2]?.headers?.['Idempotency-Key']
      expect(firstKey).toBeTruthy()
      expect(firstKey).toBe(secondKey)

      // Same payload body on both calls (frozen, not re-read from live state).
      expect(mockPostWithMeta.mock.calls[0]?.[1]).toEqual(mockPostWithMeta.mock.calls[1]?.[1])
    })

    it('repeated 202s: keeps retrying without erroring or duplicating the click', async () => {
      let call = 0
      mockPostWithMeta.mockImplementation(async () => {
        call += 1
        if (call <= 3) {
          return {
            data: { status: 'processing' },
            status: 202,
            headers: headers({ 'Retry-After': '1' }),
          }
        }
        return {
          data: { success: true, data: { id: 99 }, warnings: [] },
          status: 201,
          headers: headers(),
        }
      })

      const wrapper = await mountWizard()
      await advanceToReview(wrapper)
      vi.useFakeTimers()

      const submitButton = wrapper.findAll('button').find((b) => b.text() === 'إرسال الطلب')
      await submitButton!.trigger('click')
      await flushPromises()

      // Submit stays disabled throughout — no duplicate click can fire.
      expect(submitButton!.attributes('disabled')).toBeDefined()

      await vi.advanceTimersByTimeAsync(1000)
      await settle()
      expect(wrapper.emitted('submitted')).toBeUndefined()

      await vi.advanceTimersByTimeAsync(1000)
      await settle()
      expect(wrapper.emitted('submitted')).toBeUndefined()

      await vi.advanceTimersByTimeAsync(1000)
      await settle()

      expect(wrapper.emitted('submitted')).toEqual([[99]])
      expect(mockPostWithMeta).toHaveBeenCalledTimes(4)
    })

    it('exposes submissionCompleted only after a real completed response, never on a 202', async () => {
      mockPostWithMeta.mockResolvedValueOnce({
        data: { status: 'processing' },
        status: 202,
        headers: headers({ 'Retry-After': '1' }),
      })
      mockPostWithMeta.mockResolvedValueOnce({
        data: { success: true, data: { id: 5 }, warnings: [] },
        status: 201,
        headers: headers(),
      })

      const wrapper = await mountWizard()
      await advanceToReview(wrapper)
      vi.useFakeTimers()

      const submitButton = wrapper.findAll('button').find((b) => b.text() === 'إرسال الطلب')
      await submitButton!.trigger('click')
      await flushPromises()

      expect((wrapper.vm as unknown as { submissionCompleted: boolean }).submissionCompleted).toBe(
        false,
      )

      await vi.advanceTimersByTimeAsync(1000)
      await settle()

      expect((wrapper.vm as unknown as { submissionCompleted: boolean }).submissionCompleted).toBe(
        true,
      )
      expect((wrapper.vm as unknown as { hasUnsavedChanges: boolean }).hasUnsavedChanges).toBe(
        false,
      )
    })
  })

  describe('temporary upload lifecycle', () => {
    it('blocks Next while an upload is uploading, then unblocks once the scan resolves clean', async () => {
      let resolveUpload!: (value: { token: string; expires_at: string }) => void
      mockUploadTemporary.mockReturnValue(
        new Promise((resolve) => {
          resolveUpload = resolve
        }),
      )
      mockUploadStatus.mockResolvedValue({
        token: 'tok-1',
        scan_status: 'clean',
        original_name: 'file.pdf',
        size: 10,
        expires_at: '2026-01-01T00:00:00Z',
      })

      const wrapper = await mountWizard()
      await advanceToDocumentsStep(wrapper)
      vi.useFakeTimers()

      // Now on the documents step (the last field-group step, so its
      // advance button is "مراجعة الطلب" — attach a file.
      const fileInput = wrapper.find('input[type="file"]')
      const file = new File(['x'], 'file.pdf', { type: 'application/pdf' })
      Object.defineProperty(fileInput.element, 'files', { value: [file] })
      await fileInput.trigger('change')
      await settle()

      const reviewButton = wrapper.findAll('button').find((b) => b.text() === 'مراجعة الطلب')
      expect(reviewButton!.attributes('disabled')).toBeDefined()
      expect(wrapper.text()).toContain('جارٍ رفع')

      resolveUpload({ token: 'tok-1', expires_at: '2026-01-01T00:00:00Z' })
      await settle()
      expect(wrapper.text()).toContain('جارٍ فحص')
      expect(reviewButton!.attributes('disabled')).toBeDefined()

      await vi.advanceTimersByTimeAsync(2000)
      await settle()

      expect(wrapper.text()).toContain('تم الفحص بنجاح')
      const reviewButtonAfter = wrapper.findAll('button').find((b) => b.text() === 'مراجعة الطلب')
      expect(reviewButtonAfter!.attributes('disabled')).toBeUndefined()
    })

    it('shows an Arabic error and keeps Next blocked when the scan reports infected', async () => {
      mockUploadTemporary.mockResolvedValue({ token: 'tok-2', expires_at: '2026-01-01T00:00:00Z' })
      mockUploadStatus.mockResolvedValue({
        token: 'tok-2',
        scan_status: 'infected',
        original_name: 'bad.pdf',
        size: 10,
        expires_at: '2026-01-01T00:00:00Z',
      })

      const wrapper = await mountWizard()
      await advanceToDocumentsStep(wrapper)
      vi.useFakeTimers()

      const fileInput = wrapper.find('input[type="file"]')
      const file = new File(['x'], 'bad.pdf', { type: 'application/pdf' })
      Object.defineProperty(fileInput.element, 'files', { value: [file] })
      await fileInput.trigger('change')
      await settle()

      await vi.advanceTimersByTimeAsync(2000)
      await settle()

      expect(wrapper.text()).toContain('مرفوض')
      const reviewButton = wrapper.findAll('button').find((b) => b.text() === 'مراجعة الطلب')
      expect(reviewButton!.attributes('disabled')).toBeDefined()
    })

    it('shows an Arabic error when the upload POST itself fails', async () => {
      mockUploadTemporary.mockRejectedValue({ data: { message: 'فشل الرفع' } })

      const wrapper = await mountWizard()
      await advanceToDocumentsStep(wrapper)

      const fileInput = wrapper.find('input[type="file"]')
      const file = new File(['x'], 'file.pdf', { type: 'application/pdf' })
      Object.defineProperty(fileInput.element, 'files', { value: [file] })
      await fileInput.trigger('change')
      await settle()

      expect(wrapper.text()).toContain('فشل الرفع')
    })

    it('never includes a non-clean token in the final submission payload', async () => {
      mockUploadTemporary.mockResolvedValue({ token: 'tok-3', expires_at: '2026-01-01T00:00:00Z' })
      mockUploadStatus.mockResolvedValue({
        token: 'tok-3',
        scan_status: 'clean',
        original_name: 'file.pdf',
        size: 10,
        expires_at: '2026-01-01T00:00:00Z',
      })
      mockPostWithMeta.mockResolvedValue({
        data: { success: true, data: { id: 1 }, warnings: [] },
        status: 201,
        headers: headers(),
      })

      const wrapper = await mountWizard()
      await advanceToDocumentsStep(wrapper)
      vi.useFakeTimers()

      const fileInput = wrapper.find('input[type="file"]')
      const file = new File(['x'], 'file.pdf', { type: 'application/pdf' })
      Object.defineProperty(fileInput.element, 'files', { value: [file] })
      await fileInput.trigger('change')
      await settle()
      await vi.advanceTimersByTimeAsync(2000)
      await settle()

      expect(wrapper.text()).toContain('تم الفحص بنجاح')

      expect(
        wrapper
          .findAll('button')
          .find((b) => b.text() === 'مراجعة الطلب')
          ?.attributes('disabled'),
      ).toBeUndefined()
      await wrapper
        .findAll('button')
        .find((b) => b.text() === 'مراجعة الطلب')!
        .trigger('click')
      await settle()

      const submitButton = wrapper.findAll('button').find((b) => b.text() === 'إرسال الطلب')
      await submitButton!.trigger('click')
      await settle()

      const payload = mockPostWithMeta.mock.calls[0]?.[1] as { upload_tokens: string[] }
      expect(payload.upload_tokens).toEqual(['tok-3'])
    })
  })

  describe('forward navigation gating', () => {
    it('blocks a stepper jump onto an unvalidated later step', async () => {
      const wrapper = await mountWizard()
      // Never fill/validate step 0 — attempt to jump straight to the review step.
      const items = wrapper.findAllComponents({ name: 'StepperItem' })
      expect(items.length).toBeGreaterThanOrEqual(3)
      await items[2]!.trigger('click')
      await settle()

      // Still on step 0 — the jump must have been rejected.
      expect(wrapper.text()).toContain('خطوة 1 من 3')
    })

    it('allows a stepper jump backward at any time', async () => {
      const wrapper = await mountWizard()
      await advanceToReview(wrapper)
      // advanceToReview goes all the way to the review step (step 3 of 3).
      expect(wrapper.text()).toContain('خطوة 3 من 3')

      const items = wrapper.findAllComponents({ name: 'StepperItem' })
      await items[0]!.trigger('click')
      await settle()

      expect(wrapper.text()).toContain('خطوة 1 من 3')
    })
  })
})
