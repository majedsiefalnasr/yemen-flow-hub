import { expect, test } from '@playwright/test'
import { execFileSync } from 'node:child_process'

// Minimal, syntactically valid single-page PDF (no external asset needed —
// Playwright's setInputFiles accepts an in-memory buffer directly).
const MINIMAL_PDF = Buffer.from(
  '%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n' +
    '2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n' +
    '3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 100 100]>>endobj\n' +
    'trailer<</Root 1 0 R>>',
  'utf-8',
)

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

const runId = Date.now()
const taxNumber = `E2E${runId}`.slice(0, 20)

type MerchantFixture = { id: number; workflowVersionId: number }

function runBackendTinker(code: string): string {
  return execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], {
    cwd: process.env['WIZARD_SUBMIT_E2E_BACKEND_CWD'] ?? '../backend',
    encoding: 'utf-8',
  })
}

/**
 * Creates a merchant (with exactly one MerchantCompany, so the wizard's
 * autofill auto-selects it instead of prompting the user to choose, and one
 * MerchantOwner so the optional owners field also populates) scoped to
 * entry@tiib.com.ye's own bank (seeded by backend/database/seeders/
 * UserSeeder.php as a stable DATA_ENTRY demo user) under a run-unique
 * tax number, and resolves the published IMPORT_FINANCING
 * workflow version id. Demo-seeded merchant tax numbers are Faker-generated
 * per bank (MerchantSeeder.php) and not safe to hardcode across runs/
 * reseeds, so this test creates its own. commercial_registration_number/
 * _expiry live on MerchantCompany, not Merchant itself (see
 * app/Models/MerchantCompany.php) — Merchant::create() silently drops
 * those keys as non-fillable if passed there directly, which is what left
 * "الشركة المرتبطة" unresolved and the wizard's Next button permanently
 * disabled during initial verification of this fixture.
 */
function createMerchantFixture(): MerchantFixture {
  const output = runBackendTinker(
    `$user = App\\Models\\User::where('email', 'entry@tiib.com.ye')->firstOrFail(); ` +
      `$merchant = App\\Models\\Merchant::create(['bank_id' => $user->bank_id, ` +
      `'name' => 'E2E Wizard Submit ${runId}', 'tax_number' => '${taxNumber}', 'status' => 'ACTIVE', ` +
      `'tax_card_expiry' => now()->addYears(3)]); ` +
      `$merchant->companies()->create(['name' => 'E2E Wizard Submit ${runId} Co.', ` +
      `'commercial_registration_number' => 'CR-E2E-${runId}', ` +
      `'commercial_registration_expiry' => now()->addYears(3), 'is_active' => true]); ` +
      `$merchant->owners()->create(['name' => 'E2E Owner ${runId}', 'ownership_percentage' => 100]); ` +
      `$version = App\\Models\\WorkflowVersion::whereHas('definition', fn($q) => $q->where('code', 'IMPORT_FINANCING'))` +
      `->where('state', 'PUBLISHED')->latest('id')->firstOrFail(); ` +
      `echo json_encode(['merchantId' => $merchant->id, 'workflowVersionId' => $version->id]);`,
  )
  const jsonLine = output
    .split('\n')
    .map((line) => line.trim())
    .find((line) => line.startsWith('{'))
  if (!jsonLine) throw new Error(`Could not parse merchant fixture from tinker output: ${output}`)
  const parsed = JSON.parse(jsonLine) as { merchantId: number; workflowVersionId: number }
  return { id: parsed.merchantId, workflowVersionId: parsed.workflowVersionId }
}

function deleteMerchantFixture(merchantId: number): void {
  runBackendTinker(
    `App\\Models\\Merchant::withTrashed()->where('id', ${merchantId})->forceDelete();`,
  )
}

test.describe('Engine request wizard: submit-to-navigation', () => {
  test.skip(
    process.env['RUN_WIZARD_SUBMIT_E2E'] !== '1',
    'Set RUN_WIZARD_SUBMIT_E2E=1 with the backend and frontend dev servers running to exercise the real ' +
      'picker → wizard → temporary upload → clean scan → atomic submit → request-detail navigation flow.',
  )
  test.describe.configure({ mode: 'serial' })

  let fixture: MerchantFixture

  test.beforeAll(() => {
    fixture = createMerchantFixture()
  })

  test.afterAll(() => {
    if (fixture) deleteMerchantFixture(fixture.id)
  })

  test('submits a full import-financing request and lands on the created request detail page', async ({
    page,
  }) => {
    test.setTimeout(120_000)

    await page.goto('/login')
    await page.getByRole('button', { name: 'تبديل المستخدم السريع' }).click()
    await page.getByRole('button', { name: 'تسجيل الدخول كـ رامي القدسي' }).click()
    await expect(page).toHaveURL(/\/dashboard/)

    await page.goto(`/workflows/new-request/${fixture.workflowVersionId}`)

    // ── Step 1: basic info — tax number resolves the merchant via autofill ──
    await page.getByRole('textbox', { name: 'الرقم الضريبي' }).fill(taxNumber)
    await expect(page.getByRole('combobox', { name: 'اسم التاجر' })).toContainText(
      `E2E Wizard Submit ${runId}`,
      { timeout: 10_000 },
    )
    await page.getByRole('button', { name: 'التالي' }).click()

    // ── Step 2: invoice data ──
    await page.getByRole('combobox', { name: 'نوع الطلب' }).click()
    await page.getByRole('option', { name: 'طلب تمويل واردات' }).click()
    await page.getByRole('combobox', { name: 'نوع التغطية' }).click()
    await page.getByRole('option', { name: 'تحويل مباشر' }).click()
    await page.getByRole('combobox', { name: 'مصادر العملة الأجنبية' }).click()
    await page.getByRole('option', { name: 'حساب العميل' }).click()
    await page.getByRole('combobox', { name: 'شروط الدفع' }).click()
    await page.getByRole('option', { name: 'كلي' }).click()
    await page.getByRole('combobox', { name: 'عملة الطلب' }).click()
    await page.getByRole('option', { name: 'دولار أمريكي' }).click()
    await page.getByRole('spinbutton', { name: 'نسبة الطلب %' }).fill('100')
    await page.getByRole('combobox', { name: 'نوع الفاتورة' }).click()
    await page.getByRole('option', { name: 'فاتورة تجارية' }).click()
    await page.getByRole('spinbutton', { name: 'إجمالي الطلب' }).fill('50000')
    await page.getByRole('combobox', { name: 'عملة الفاتورة' }).click()
    await page.getByRole('option', { name: 'دولار أمريكي' }).click()
    await page.getByRole('textbox', { name: 'رقم الفاتورة' }).fill(`INV-E2E-${runId}`)
    await page.getByRole('textbox', { name: 'تاريخ الفاتورة' }).fill('2026-07-01')
    await page.getByRole('spinbutton', { name: 'الكمية' }).fill('1000')
    await page.getByRole('textbox', { name: 'وحدة القياس' }).fill('قطعة')
    await page.getByRole('spinbutton', { name: 'إجمالي الفاتورة' }).fill('50000')
    await page.getByRole('combobox', { name: 'السلعة' }).click()
    await page.getByRole('option', { name: 'الأغذية والمشروبات' }).click()
    await page.getByRole('textbox', { name: 'اسم الشركة المصدرة' }).fill('E2E Exporter Co.')
    await page.getByRole('textbox', { name: 'موقع الشركة المصدرة' }).fill('Dubai, UAE')
    await page.getByRole('combobox', { name: 'بلد المنشأ' }).click()
    await page.getByRole('option', { name: 'الإمارات العربية المتحدة' }).click()
    await page.getByRole('button', { name: 'التالي' }).click()

    // ── Step 3: shipping data ──
    await page.getByRole('textbox', { name: 'تاريخ الشحن' }).fill('2026-07-05')
    await page.getByRole('textbox', { name: 'تاريخ الوصول' }).fill('2026-07-20')
    await page.getByRole('textbox', { name: 'ميناء الشحن' }).fill('Jebel Ali Port')
    await page.getByRole('combobox', { name: 'ميناء الوصول' }).click()
    await page.getByRole('option', { name: 'ميناء عدن' }).click()
    await page.getByRole('combobox', { name: 'شروط التسليم' }).click()
    await page.getByRole('option', { name: 'FOB' }).click()
    await page.getByRole('textbox', { name: 'الوجهة النهائية' }).fill("Sana'a, Yemen")
    await page.getByRole('button', { name: 'التالي' }).click()

    // ── Step 4: required documents — real upload → real clean-scan wait ──
    const requiredFileLabels = [
      'كشف حساب بالريال اليمني (مناطق الشرعية)',
      'كشف حساب بالريال السعودي (مناطق الشرعية)',
      'كشف حساب بالدولار الأمريكي (مناطق الشرعية)',
      'البطاقة الضريبية والسجل التجاري',
      'الفاتورة',
    ]
    const cleanScanText = page.getByText('evidence.pdf — تم الفحص بنجاح')
    for (const [index, label] of requiredFileLabels.entries()) {
      // DynamicForm.vue pairs <FieldLabel :for="field.key"> with
      // <Input :id="field.key" type="file">, so getByLabel resolves the
      // real file input the same way it resolves every text field above.
      // Every one of these fields is required, and its <label> text node
      // includes the required-marker span's " *" even though that span is
      // aria-hidden (confirmed via DOM inspection: label.textContent ends
      // in "  *"), so an exact label match must anchor on the base text
      // and tolerate an optional trailing marker rather than matching the
      // plain string — which would either miss (exact) or over-match
      // (substring: 'الفاتورة' also matches the step-2 stepper item
      // 'بيانات الفاتورة').
      await page
        .getByLabel(new RegExp(`^${escapeRegExp(label)}\\s*\\*?$`))
        .setInputFiles({ name: 'evidence.pdf', mimeType: 'application/pdf', buffer: MINIMAL_PDF })
      // Every field's filename is identical (evidence.pdf), so the clean-scan
      // text appears once per successfully scanned upload — waiting for the
      // count to reach index+1 confirms THIS field's scan, not an earlier one.
      await expect(cleanScanText).toHaveCount(index + 1, { timeout: 30_000 })
    }
    await page.getByRole('button', { name: 'مراجعة الطلب' }).click()

    // ── Step 5: review and submit — real atomic 201, no mocked API, single
    // click: EngineRequestWizard.vue sets submissionCompleted before emitting
    // 'submitted', so the leave-confirmation dialog no longer has a window to
    // intercept a just-succeeded submission's navigation. ──
    const submitButton = page.getByRole('button', { name: 'إرسال الطلب' })
    await submitButton.click()

    await expect(page).toHaveURL(/\/workflows\/instances\/\d+/, { timeout: 30_000 })
    await expect(page.getByRole('heading', { name: /^ENG-\d{4}-\d+$/ })).toBeVisible()

    const requestId = Number(page.url().match(/\/workflows\/instances\/(\d+)/)?.[1])
    expect(requestId).toBeGreaterThan(0)
  })
})
