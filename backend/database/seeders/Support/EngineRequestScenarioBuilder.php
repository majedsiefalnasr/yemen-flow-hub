<?php

namespace Database\Seeders\Support;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Workflow\EngineTransitionService;
use App\Support\InvoiceKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Builds engine request anchors and bulk rows from declarative catalog specs.
 *
 * Anchors are direct-insert (request + history + claim state built in one
 * transaction, then checked by EngineRequestAnchorInvariantValidator) — the
 * same pattern the legacy EngineRequestDemoSeeder used, since
 * EngineTransitionService::execute() enforces live field-rule/permission
 * checks that a declarative fixture catalog should not have to satisfy.
 *
 * The one case that goes through a real service call is
 * `abandoned_via_api` bulk rows, which use
 * EngineTransitionService::abandonDraft() inside DemoSeedContext so the
 * ABANDONED runtime status is proven reachable, not just asserted.
 *
 * Spec: backend/docs/superpowers/specs/2026-07-07-engine-demo-seeder-redesign-design.md
 */
final class EngineRequestScenarioBuilder
{
    private WorkflowVersion $workflowVersion;

    /** @var Collection<string, WorkflowStage> keyed by stage code */
    private Collection $stages;

    /** @var array<string, int> field key => field_definition id */
    private array $fieldIds;

    public function __construct(
        private readonly EngineRequestAnchorInvariantValidator $validator,
        private readonly EngineTransitionService $transitionService,
    ) {
        $this->workflowVersion = WorkflowVersion::query()
            ->whereHas('definition', fn ($q) => $q->where('code', 'IMPORT_FINANCING'))
            ->firstOrFail();

        $this->stages = $this->workflowVersion->stages()->get()->keyBy('code');

        $this->fieldIds = $this->workflowVersion->fields()->pluck('field_definitions.id', 'field_definitions.key')->all();
    }

    /**
     * Build (or rebuild) a single anchor from its catalog spec.
     *
     * @param  array<string, mixed>  $spec
     */
    public function buildAnchor(array $spec, Bank $bank, Merchant $merchant, User $creator): EngineRequest
    {
        return DB::transaction(function () use ($spec, $bank, $merchant, $creator) {
            $path = $spec['path'];
            $currentStage = $this->stage($spec['current_stage']);
            $baseTime = Carbon::create(2026, 5, 1, 9, 0, 0);

            $data = $this->enrichRequestData($spec['sample'], $bank, $merchant);

            $request = EngineRequest::updateOrCreate(
                ['reference' => $spec['reference']],
                [
                    'workflow_version_id' => $this->workflowVersion->id,
                    'current_stage_id' => $currentStage->id,
                    'status' => $spec['runtime_status'],
                    'created_by' => $creator->id,
                    'bank_id' => $bank->id,
                    'merchant_id' => $merchant->id,
                    'data' => $data,
                    'version' => 1,
                    'amount' => $data['amount'] ?? null,
                    'currency' => $data['currency'] ?? 'USD',
                    'invoice_number' => $data['invoice_number'] ?? null,
                    'invoice_number_normalized' => isset($data['invoice_number'])
                        ? InvoiceKey::normalize($data['invoice_number'])
                        : null,
                    'request_percentage' => $data['request_percentage'] ?? null,
                    'created_at' => $baseTime,
                ],
            );

            $this->rebuildHistory($request, $path, $creator, $baseTime);
            $this->applyClaimState($request, $spec, $currentStage, $creator);
            $this->applyDocumentScanState($request, $spec, $currentStage, $creator);

            $request->refresh();
            $this->validator->validate($request);

            return $request;
        });
    }

    /**
     * Build a single bulk request from a scenario matrix row.
     */
    public function buildBulk(
        string $reference,
        string $scenarioKey,
        Bank $bank,
        Merchant $merchant,
        User $creator,
        Carbon $at,
    ): EngineRequest {
        return DB::transaction(function () use ($reference, $scenarioKey, $bank, $merchant, $creator, $at) {
            $definition = $this->bulkScenarioDefinition($scenarioKey);

            $data = $this->enrichRequestData([
                'amount' => random_int(50, 2000) * 1000,
                'invoice_number' => sprintf('INV-%s-%s', $bank->code, substr(md5($reference), 0, 8)),
                'request_percentage' => 100,
                'import_type' => 'مواد عامة',
                'importer_name' => $merchant->name,
                'supplier_name' => 'Demo Supplier Co.',
                'origin_country' => 'الصين',
            ], $bank, $merchant);

            $currentStage = $this->stage($definition['current_stage']);

            $request = EngineRequest::updateOrCreate(
                ['reference' => $reference],
                [
                    'workflow_version_id' => $this->workflowVersion->id,
                    'current_stage_id' => $currentStage->id,
                    'status' => $definition['runtime_status'],
                    'created_by' => $creator->id,
                    'bank_id' => $bank->id,
                    'merchant_id' => $merchant->id,
                    'data' => $data,
                    'version' => 1,
                    'amount' => $data['amount'],
                    'currency' => $data['currency'] ?? 'USD',
                    'invoice_number' => $data['invoice_number'],
                    'invoice_number_normalized' => InvoiceKey::normalize($data['invoice_number']),
                    'request_percentage' => $data['request_percentage'],
                    'created_at' => $at,
                ],
            );

            $this->rebuildHistory($request, $definition['path'], $creator, $at);

            if ($scenarioKey === 'abandoned_via_api') {
                $request = DemoSeedContext::run(
                    fn () => $this->transitionService->abandonDraft($request, $request->version, $creator)
                );
            }

            if (($definition['claim_active'] ?? false) === true) {
                $this->applyClaimState($request, ['claim_active' => true], $currentStage, $creator);
            }

            if (($definition['claim_expired'] ?? false) === true) {
                $this->applyClaimState($request, ['claim_expired' => true], $currentStage, $creator);
            }

            if (($definition['scan_status'] ?? null) !== null) {
                $this->applyDocumentScanState(
                    $request,
                    ['scan_status' => $definition['scan_status']],
                    $currentStage,
                    $creator,
                );
            }

            $request->refresh();
            $this->validator->validate($request);

            return $request;
        });
    }

    /**
     * Give two anchors (usually a cross-bank pair) the same normalized invoice
     * key while keeping their raw invoice_number bank-local.
     */
    public function applyDuplicatePair(EngineRequest $a, EngineRequest $b, string $sharedInvoiceNumber): void
    {
        $normalized = InvoiceKey::normalize($sharedInvoiceNumber);

        foreach ([$a, $b] as $request) {
            $data = $request->data ?? [];
            $data['invoice_number'] = $sharedInvoiceNumber;

            $request->forceFill([
                'data' => $data,
                'invoice_number' => $sharedInvoiceNumber,
                'invoice_number_normalized' => $normalized,
            ])->save();
        }
    }

    /**
     * Catalog specs write samples with snake_case keys for the handful of
     * fields whose published v1 key happens to differ from the Lovable name.
     * Everything else in `data` uses the real published field key.
     */
    private const CATALOG_TO_FIELD_KEY = [
        'import_type' => 'importType',
        'importer_name' => 'importerName',
        'supplier_name' => 'supplierName',
        'origin_country' => 'originCountry',
    ];

    /**
     * Merge a raw catalog sample into a full published-field data payload,
     * translating catalog sample keys to real published field keys and
     * dropping anything that isn't a published v1 field.
     *
     * @param  array<string, mixed>  $sample
     * @return array<string, mixed>
     */
    public function enrichRequestData(array $sample, Bank $bank, Merchant $merchant): array
    {
        $defaults = [
            'taxNumber' => $merchant->tax_number,
            'importerName' => $merchant->name,
            'requestType' => 'طلب مصارفة وتحويل خارجي',
            'coverageType' => 'اعتماد مستندي',
            'foreignCurrencySource' => 'حساب العميل',
            'paymentTerms' => 'كلي',
            'requestCurrency' => 'USD',
            'currency' => 'USD',
            'invoiceType' => 'فاتورة تجارية',
            'invoiceDate' => '2026-06-16',
            'quantity' => 1,
            'unit' => 'كرتون',
            'supplierLocation' => 'المدينة / الدولة',
            'shippingDate' => '2026-06-16',
            'arrivalDate' => '2026-06-16',
            'shippingPort' => 'ميناء الشحن',
            'deliveryTerms' => 'CIF',
            'finalDestination' => 'المدينة / المخزن الوجهة',
        ];

        $translated = [];
        foreach ($sample as $key => $value) {
            $translated[self::CATALOG_TO_FIELD_KEY[$key] ?? $key] = $value;
        }

        $data = array_merge($defaults, $translated);

        return array_intersect_key($data, $this->fieldIds);
    }

    /**
     * Sets/clears claim columns per anchor/bulk claim flags.
     *
     * @param  array<string, mixed>  $spec
     */
    public function applyClaimState(EngineRequest $request, array $spec, WorkflowStage $stage, User $creator): void
    {
        if (! $stage->requires_claim) {
            return;
        }

        if (($spec['claim_active'] ?? false) === true) {
            $claimer = $this->userForRole($request->bank_id, UserRole::SUPPORT_COMMITTEE);

            $request->forceFill([
                'claimed_by' => $claimer->id,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addMinutes((int) config('workflow.support_claim_ttl_minutes', 15)),
                'claim_stage_id' => $stage->id,
            ])->save();

            return;
        }

        if (($spec['claim_expired'] ?? false) === true) {
            $claimer = $this->userForRole($request->bank_id, UserRole::SUPPORT_COMMITTEE);

            $request->forceFill([
                'claimed_by' => $claimer->id,
                'claimed_at' => now()->subMinutes(60),
                'claim_expires_at' => now()->subMinutes(45),
                'claim_stage_id' => $stage->id,
            ])->save();

            return;
        }

        if (($spec['scenario_key'] ?? null) === 'claim_released') {
            $request->forceFill([
                'claimed_by' => null,
                'claimed_at' => null,
                'claim_expires_at' => null,
                'claim_stage_id' => null,
            ])->save();
        }
    }

    /**
     * Seeds a commercial-invoice document for the request with the given scan
     * status (defaults to clean). `document_replaced` additionally seeds a
     * superseded prior version so "current evidence" tests can exercise it.
     *
     * @param  array<string, mixed>  $spec
     */
    public function applyDocumentScanState(
        EngineRequest $request,
        array $spec,
        WorkflowStage $stage,
        User $creator,
    ): void {
        $fieldId = $this->fieldIds['docCommercialInvoice'] ?? null;
        if ($fieldId === null) {
            return;
        }

        $scanStatus = $spec['scan_status'] ?? 'clean';

        if (($spec['document_replaced'] ?? false) === true) {
            $v1Path = "demo/{$request->reference}/commercial-invoice-v1.pdf";
            $v2Path = "demo/{$request->reference}/commercial-invoice-v2.pdf";
            $v1Checksum = $this->putPdf($v1Path, $request->reference.'-v1');
            $v2Checksum = $this->putPdf($v2Path, $request->reference.'-v2');

            $superseded = EngineRequestDocument::updateOrCreate(
                [
                    'request_id' => $request->id,
                    'field_id' => $fieldId,
                    'version' => 1,
                ],
                [
                    'uploaded_by' => $creator->id,
                    'stage_id' => $stage->id,
                    'original_name' => 'commercial-invoice-v1.pdf',
                    'path' => $v1Path,
                    'mime' => 'application/pdf',
                    'size' => Storage::disk('private')->size($v1Path),
                    'checksum' => $v1Checksum,
                    'scan_status' => 'clean',
                    'status' => 'superseded',
                ],
            );

            $active = EngineRequestDocument::updateOrCreate(
                [
                    'request_id' => $request->id,
                    'field_id' => $fieldId,
                    'version' => 2,
                ],
                [
                    'uploaded_by' => $creator->id,
                    'stage_id' => $stage->id,
                    'original_name' => 'commercial-invoice-v2.pdf',
                    'path' => $v2Path,
                    'mime' => 'application/pdf',
                    'size' => Storage::disk('private')->size($v2Path),
                    'checksum' => $v2Checksum,
                    'scan_status' => 'clean',
                    'status' => 'active',
                ],
            );

            $superseded->update(['superseded_by' => $active->id]);

            return;
        }

        $path = "demo/{$request->reference}/commercial-invoice.pdf";
        $checksum = $this->putPdf($path, $request->reference);

        EngineRequestDocument::updateOrCreate(
            [
                'request_id' => $request->id,
                'field_id' => $fieldId,
                'version' => 1,
            ],
            [
                'uploaded_by' => $creator->id,
                'stage_id' => $stage->id,
                'original_name' => 'commercial-invoice.pdf',
                'path' => $path,
                'mime' => 'application/pdf',
                'size' => Storage::disk('private')->size($path),
                'checksum' => $checksum,
                'scan_status' => $scanStatus,
                'status' => 'active',
            ],
        );
    }

    /**
     * Writes a fake PDF to the private disk and returns its sha256 checksum,
     * matching what EngineRequestDocumentIntegrityService::assertDownloadAllowed()
     * recomputes from the file on disk at download time.
     */
    private function putPdf(string $path, string $title): string
    {
        $body = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n% {$title}\n%%EOF\n";
        Storage::disk('private')->put($path, $body);

        return hash('sha256', $body);
    }

    /**
     * Rebuild seed-owned history for a request: delete existing rows for this
     * request (idempotent rebuild), then re-insert the ordered path with
     * strictly monotonic timestamps.
     *
     * Catalog convention: the first hop is the creation row, written as
     * `[initialStageCode, null, 'CREATE']` — there is no real CREATE
     * transition/action on the published workflow, so it is stored as
     * from_stage_id=null, to_stage_id=initial stage, action_code=null (which
     * EngineRequestAnchorInvariantValidator treats as the request's origin).
     * Every subsequent hop is a real `[from, to, action]` transition.
     *
     * @param  array<int, array{0: ?string, 1: ?string, 2: string}>  $path
     */
    private function rebuildHistory(EngineRequest $request, array $path, User $performer, Carbon $startAt): void
    {
        WorkflowHistoryEntry::where('request_id', $request->id)->delete();

        $timestamp = $startAt->copy();

        foreach ($path as $index => $hop) {
            [$from, $to, $actionCode] = $hop;

            if ($index === 0 && $to === null) {
                WorkflowHistoryEntry::create([
                    'request_id' => $request->id,
                    'from_stage_id' => null,
                    'to_stage_id' => $this->stage($from)->id,
                    'action_code' => null,
                    'performed_by' => $performer->id,
                    'created_at' => $timestamp,
                ]);

                $timestamp = $timestamp->copy()->addHours(6);

                continue;
            }

            WorkflowHistoryEntry::create([
                'request_id' => $request->id,
                'from_stage_id' => $this->stage($from)->id,
                'to_stage_id' => $this->stage($to)->id,
                'action_code' => $actionCode,
                'performed_by' => $performer->id,
                'created_at' => $timestamp,
            ]);

            $timestamp = $timestamp->copy()->addHours(6);
        }
    }

    private function stage(string $code): WorkflowStage
    {
        return $this->stages[$code] ?? throw new RuntimeException("Unknown workflow stage code: {$code}");
    }

    private function userForRole(int $bankId, UserRole $role): User
    {
        return User::query()
            ->where('bank_id', $bankId)
            ->withUserRole($role)
            ->first()
            ?? User::query()->withUserRole($role)->firstOrFail();
    }

    /**
     * @return array{current_stage: string, runtime_status: string, path: array<int, array{0: ?string, 1: ?string, 2: string}>, claim_active?: bool, claim_expired?: bool, scan_status?: string}
     */
    private function bulkScenarioDefinition(string $scenarioKey): array
    {
        return match ($scenarioKey) {
            'create_active' => ['current_stage' => 'CREATE', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
            ]],
            'internal_active' => ['current_stage' => 'INTERNAL', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
            ]],
            'returned_to_entry' => ['current_stage' => 'CREATE', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
                ['INTERNAL', 'CREATE', 'REJECT'],
            ]],
            'support_active', 'support_claim_active', 'support_claim_expired', 'claim_released' => [
                'current_stage' => 'SUPPORT', 'runtime_status' => 'ACTIVE', 'path' => [
                    ['CREATE', null, 'CREATE'],
                    ['CREATE', 'INTERNAL', 'APPROVE'],
                    ['INTERNAL', 'SUPPORT', 'APPROVE'],
                ],
                'claim_active' => $scenarioKey === 'support_claim_active',
                'claim_expired' => $scenarioKey === 'support_claim_expired',
            ],
            'support_returned' => ['current_stage' => 'CREATE', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
                ['INTERNAL', 'SUPPORT', 'APPROVE'],
                ['SUPPORT', 'CREATE', 'REJECT'],
            ]],
            'exec_active' => ['current_stage' => 'EXEC', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
                ['INTERNAL', 'SUPPORT', 'APPROVE'],
                ['SUPPORT', 'EXEC', 'APPROVE'],
            ]],
            'fx_active' => ['current_stage' => 'FX', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
                ['INTERNAL', 'SUPPORT', 'APPROVE'],
                ['SUPPORT', 'EXEC', 'APPROVE'],
                ['EXEC', 'FX', 'APPROVE'],
            ]],
            'fx_confirm_active' => ['current_stage' => 'FX_CONFIRM', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
                ['INTERNAL', 'SUPPORT', 'APPROVE'],
                ['SUPPORT', 'EXEC', 'APPROVE'],
                ['EXEC', 'FX', 'APPROVE'],
                ['FX', 'FX_CONFIRM', 'APPROVE'],
            ]],
            'final_active' => ['current_stage' => 'FINAL', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
                ['INTERNAL', 'SUPPORT', 'APPROVE'],
                ['SUPPORT', 'EXEC', 'APPROVE'],
                ['EXEC', 'FX', 'APPROVE'],
                ['FX', 'FX_CONFIRM', 'APPROVE'],
                ['FX_CONFIRM', 'FINAL', 'APPROVE'],
            ]],
            'completed_closed' => ['current_stage' => 'CLOSED_COMPLETED', 'runtime_status' => 'CLOSED', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
                ['INTERNAL', 'SUPPORT', 'APPROVE'],
                ['SUPPORT', 'EXEC', 'APPROVE'],
                ['EXEC', 'FX', 'APPROVE'],
                ['FX', 'FX_CONFIRM', 'APPROVE'],
                ['FX_CONFIRM', 'FINAL', 'APPROVE'],
                ['FINAL', 'CLOSED_COMPLETED', 'FINAL_APPROVE'],
            ]],
            'rejected_terminal' => ['current_stage' => 'CLOSED_REJECTED', 'runtime_status' => 'REJECTED', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
                ['INTERNAL', 'SUPPORT', 'APPROVE'],
                ['SUPPORT', 'EXEC', 'APPROVE'],
                ['EXEC', 'CLOSED_REJECTED', 'REJECT_FINAL'],
            ]],
            'document_replaced' => ['current_stage' => 'INTERNAL', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
            ]],
            'abandoned_via_api' => ['current_stage' => 'CREATE', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
            ]],
            'scan_pending' => ['current_stage' => 'INTERNAL', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
            ], 'scan_status' => 'pending'],
            'scan_failed' => ['current_stage' => 'INTERNAL', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
            ], 'scan_status' => 'failed'],
            'scope_cross_bank_mask' => ['current_stage' => 'INTERNAL', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
            ]],
            'analytics_volume' => ['current_stage' => 'SUPPORT', 'runtime_status' => 'ACTIVE', 'path' => [
                ['CREATE', null, 'CREATE'],
                ['CREATE', 'INTERNAL', 'APPROVE'],
                ['INTERNAL', 'SUPPORT', 'APPROVE'],
            ]],
            default => throw new RuntimeException("Unknown bulk scenario key: {$scenarioKey}"),
        };
    }
}
