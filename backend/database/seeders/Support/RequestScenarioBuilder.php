<?php

namespace Database\Seeders\Support;

use App\Enums\AuditAction;
use App\Enums\Currency;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\VoteType;
use App\Enums\VotingSessionStatus;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\DocumentType;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\RequestDocument;
use App\Models\RequestStageHistory;
use App\Models\RequestVote;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RequestScenarioBuilder
{
    private int $customsSequence;

    private ?Collection $documentTypes = null;

    public function __construct()
    {
        // Start after any already-seeded declarations so the unique number never collides.
        $year = now()->format('Y');
        $prefix = "CD-{$year}-";
        $max = CustomsDeclaration::query()
            ->where('declaration_number', 'like', $prefix.'%')
            ->max('declaration_number');

        $this->customsSequence = $max
            ? ((int) substr($max, strlen($prefix))) + 1
            : 1;
    }

    public function build(string $scenario, Bank $bank, ?Carbon $createdAt = null): ImportRequest
    {
        $data = $this->scenarioConfig($scenario);
        $status = $data['status'];
        $timeline = $this->timelineForStatus($status, $createdAt);

        $entries = User::query()->where('bank_id', $bank->id)->where('role', UserRole::DATA_ENTRY->value)->get();
        $entry = $entries->random();
        $reviewer = User::query()->where('bank_id', $bank->id)->where('role', UserRole::BANK_REVIEWER->value)->firstOrFail();
        $swift = User::query()->where('bank_id', $bank->id)->where('role', UserRole::SWIFT_OFFICER->value)->firstOrFail();
        $supportUsers = User::query()->where('role', UserRole::SUPPORT_COMMITTEE->value)->orderBy('id')->get();
        $support = $supportUsers->firstOrFail();
        $director = User::query()->where('role', UserRole::COMMITTEE_DIRECTOR->value)->firstOrFail();
        $execs = User::query()->where('role', UserRole::EXECUTIVE_MEMBER->value)->orderBy('id')->get();
        $merchant = Merchant::query()->where('bank_id', $bank->id)->inRandomOrder()->firstOrFail();

        [$claimedBy, $claimedAt, $claimExpiresAt] = $this->resolveClaim($data, $supportUsers);
        $owner = $this->ownerForStatus($status);

        // Milestone flags — used for both field population and actor tracking
        $submitted = $this->reached(RequestStatus::SUBMITTED, $status);
        $bankReviewStarted = $this->reached(RequestStatus::BANK_REVIEW, $status);
        $bankApproved = $this->reached(RequestStatus::BANK_APPROVED, $status);
        $supportReviewStarted = $bankApproved && ! in_array($scenario, ['bank_approved'], true);
        $supportDecided = $this->reached(RequestStatus::SUPPORT_APPROVED, $status)
            || in_array($scenario, ['support_rejected', 'support_returned'], true);
        $supportApproved = $this->reached(RequestStatus::SUPPORT_APPROVED, $status);
        $swiftUploaded = $this->reached(RequestStatus::SWIFT_UPLOADED, $status);
        $votingOpened = $this->reached(RequestStatus::EXECUTIVE_VOTING_OPEN, $status);
        $votingClosed = $this->reached(RequestStatus::EXECUTIVE_VOTING_CLOSED, $status);
        $executiveDecided = $this->reached(RequestStatus::EXECUTIVE_APPROVED, $status)
            || $status === RequestStatus::EXECUTIVE_REJECTED;
        $isTerminalBankAction = in_array($scenario, ['bank_returned', 'bank_rejected', 'draft_rejected_internal'], true);

        $originCountry = Arr::random(['China', 'United Arab Emirates', 'India', 'Turkey', 'Saudi Arabia', 'Germany', 'Egypt', 'Malaysia']);
        $arrivalPort = Arr::random(['Aden Port', 'Hodeidah Port', 'Mukalla Port']);
        $shippingPort = Arr::random(['Port of Shanghai', 'Port of Dubai', 'Port of Mumbai', 'Port of Jeddah', 'Port of Istanbul', 'Port of Hamburg']);

        App::instance('workflow.transition.active', true);
        try {
            $request = ImportRequest::query()->create([
                // Core
                'bank_id' => $bank->id,
                'merchant_id' => $merchant->id,
                'created_by' => $entry->id,
                'last_updated_by' => $entry->id,
                'currency' => Arr::random(Currency::cases())->value,
                'amount' => fake()->randomFloat(2, 5000, 500000),
                'supplier_name' => Arr::random([
                    'Al-Hadi Trading LLC', 'Shanghai Medical Supplies Co.', 'Global Grain & Food Imports',
                    'Aden Industrial Sourcing Ltd.', 'Gulf Trading Partners', 'Eastern Pharma Ltd.',
                    'Nile Valley Exports', 'Arabian Textiles Corp.', 'Far East Electronics Ltd.',
                ]),
                'goods_description' => Arr::random([
                    'Medical Equipment', 'Food Supplies', 'Telecom Devices', 'Industrial Spare Parts',
                    'Pharmaceutical Products', 'Agricultural Goods', 'Construction Materials', 'Textiles & Garments',
                    'Electronic Components', 'Heavy Machinery', 'Chemical Raw Materials',
                ]),
                'port_of_entry' => $arrivalPort,
                'notes' => fake()->boolean(35)
                    ? Arr::random(['Additional document under review', 'Need minor clarification', 'Awaiting supplier confirmation', 'Partial shipment expected'])
                    : null,
                // Wizard fields
                'goods_type' => Arr::random(['Medical', 'Food & Beverages', 'Electronics', 'Machinery', 'Textiles', 'Chemicals', 'Raw Materials', 'Pharmaceutical']),
                'payment_terms' => Arr::random(['LC', 'TT', 'DA', 'DP', 'OA']),
                'due_date' => $timeline['created_at']->copy()->addDays(rand(30, 120)),
                'invoice_number' => 'INV-'.strtoupper(Str::random(4)).'-'.rand(1000, 9999),
                'invoice_date' => $timeline['created_at']->copy()->subDays(rand(5, 30)),
                'origin_country' => $originCountry,
                'arrival_port' => $arrivalPort,
                'shipping_port' => $shippingPort,
                'customs_office' => Arr::random(['جمارك عدن', 'جمارك الحديدة', 'جمارك المكلا', 'جمارك صنعاء']),
                'bl_number' => $swiftUploaded ? ('BL-'.strtoupper(Str::random(3)).rand(100000, 999999)) : null,
                // Status
                'status' => $status,
                'current_owner_role' => $owner,
                'voting_session_status' => $this->resolveVotingSessionStatus($scenario),
                'eligible_voter_ids' => $votingOpened ? $execs->pluck('id')->push($director->id)->toArray() : null,
                // Claim
                'claimed_by' => $claimedBy,
                'claimed_at' => $claimedAt,
                'claim_expires_at' => $claimExpiresAt,
                // Milestone timestamps
                'submitted_at' => $timeline['submitted_at'],
                'bank_approved_at' => $timeline['bank_approved_at'],
                'support_approved_at' => $timeline['support_approved_at'],
                'swift_uploaded_at' => $timeline['swift_uploaded_at'],
                'voting_opened_at' => $timeline['voting_opened_at'],
                'voting_closed_at' => $timeline['voting_closed_at'],
                'executive_decided_at' => $timeline['executive_decided_at'],
                'final_decision_at' => $timeline['final_decision_at'],
                'customs_issued_at' => $timeline['customs_issued_at'],
                // Actor tracking
                'submitted_by' => $submitted ? $entry->id : null,
                'reviewed_by' => $bankReviewStarted ? $reviewer->id : null,
                'approved_by' => $bankApproved ? $reviewer->id : null,
                'rejected_by' => $isTerminalBankAction ? $reviewer->id : null,
                'resubmitted_by' => ($data['revision_count'] > 0 && $submitted) ? $entry->id : null,
                'support_reviewed_by' => $supportDecided ? $support->id : null,
                'swift_uploaded_by' => $swiftUploaded ? $swift->id : null,
                'voting_opened_by' => $votingOpened ? $director->id : null,
                'voting_closed_by' => $votingClosed ? $director->id : null,
                // Misc
                'revision_count' => $data['revision_count'],
                'created_at' => $timeline['created_at'],
                'updated_at' => $timeline['updated_at'],
            ]);
        } finally {
            App::offsetUnset('workflow.transition.active');
        }

        $this->seedRequestDocs($request, $entry, $timeline['created_at']);

        if ($this->reached(RequestStatus::SWIFT_UPLOADED, $status)) {
            $this->seedSwiftDoc($request, $swift, $timeline['swift_uploaded_at'] ?? now());
        }

        if ($scenario === 'executive_approved') {
            $this->seedFxRequestDoc($request, $director, $timeline['executive_decided_at'] ?? now());
        }

        $this->seedHistory($scenario, $request, $entry, $reviewer, $support, $swift, $director, $timeline);
        $this->seedVotes($scenario, $request, $execs, $timeline);

        if ($this->reached(RequestStatus::CUSTOMS_DECLARATION_ISSUED, $status)) {
            $declaration = $this->seedCustomsDeclaration($request, $director, $timeline['customs_issued_at'] ?? now());
            $this->seedCustomsDoc($request, $director, $declaration->declaration_number, $timeline['customs_issued_at'] ?? now());
        }

        return $request;
    }

    // -------------------------------------------------------------------------
    // Configuration helpers
    // -------------------------------------------------------------------------

    private function scenarioConfig(string $scenario): array
    {
        return match ($scenario) {
            'draft' => ['status' => RequestStatus::DRAFT,                    'revision_count' => 0],
            'draft_rejected_internal' => ['status' => RequestStatus::DRAFT_REJECTED_INTERNAL,  'revision_count' => 1],
            'submitted' => ['status' => RequestStatus::SUBMITTED,                'revision_count' => 0],
            'bank_review' => ['status' => RequestStatus::BANK_REVIEW,              'revision_count' => 0],
            'bank_returned' => ['status' => RequestStatus::BANK_RETURNED,            'revision_count' => 1],
            'bank_rejected' => ['status' => RequestStatus::BANK_REJECTED,            'revision_count' => 0],
            'bank_approved' => ['status' => RequestStatus::BANK_APPROVED,            'revision_count' => 0],
            'support_review_pending' => ['status' => RequestStatus::SUPPORT_REVIEW_PENDING,   'revision_count' => 0],
            'support_review_in_progress_claimed' => ['status' => RequestStatus::SUPPORT_REVIEW_IN_PROGRESS, 'revision_count' => 0, 'claim_state' => 'active'],
            'support_review_in_progress_expired' => ['status' => RequestStatus::SUPPORT_REVIEW_IN_PROGRESS, 'revision_count' => 0, 'claim_state' => 'expired'],
            'support_approved' => ['status' => RequestStatus::SUPPORT_APPROVED,         'revision_count' => 0],
            'support_rejected' => ['status' => RequestStatus::SUPPORT_REJECTED,         'revision_count' => 0],
            'support_returned' => ['status' => RequestStatus::SUPPORT_RETURNED,         'revision_count' => 1],
            'waiting_for_swift' => ['status' => RequestStatus::WAITING_FOR_SWIFT,        'revision_count' => 0],
            'swift_uploaded' => ['status' => RequestStatus::SWIFT_UPLOADED,           'revision_count' => 0],
            'waiting_for_voting_open' => ['status' => RequestStatus::WAITING_FOR_VOTING_OPEN,  'revision_count' => 0],
            'executive_voting_open' => ['status' => RequestStatus::EXECUTIVE_VOTING_OPEN,    'revision_count' => 0],
            'executive_voting_open_tie' => ['status' => RequestStatus::EXECUTIVE_VOTING_OPEN,    'revision_count' => 0],
            'executive_voting_closed' => ['status' => RequestStatus::EXECUTIVE_VOTING_CLOSED,  'revision_count' => 0],
            'executive_approved' => ['status' => RequestStatus::EXECUTIVE_APPROVED,       'revision_count' => 0],
            'executive_rejected' => ['status' => RequestStatus::EXECUTIVE_REJECTED,       'revision_count' => 0],
            'customs_declaration_issued' => ['status' => RequestStatus::CUSTOMS_DECLARATION_ISSUED, 'revision_count' => 0],
            'completed' => ['status' => RequestStatus::COMPLETED,                'revision_count' => 0],
            'completed_with_revision' => ['status' => RequestStatus::COMPLETED,                'revision_count' => 2],
            default => throw new \InvalidArgumentException("Unknown scenario {$scenario}"),
        };
    }

    private function ownerForStatus(RequestStatus $status): UserRole
    {
        return match ($status) {
            RequestStatus::DRAFT,
            RequestStatus::DRAFT_REJECTED_INTERNAL,
            RequestStatus::BANK_RETURNED,
            RequestStatus::SUPPORT_RETURNED => UserRole::DATA_ENTRY,

            RequestStatus::SUBMITTED,
            RequestStatus::BANK_REVIEW,
            RequestStatus::SUPPORT_REJECTED,
            RequestStatus::BANK_REJECTED => UserRole::BANK_REVIEWER,

            RequestStatus::BANK_APPROVED,
            RequestStatus::SUPPORT_REVIEW_PENDING,
            RequestStatus::SUPPORT_REVIEW_IN_PROGRESS => UserRole::SUPPORT_COMMITTEE,

            RequestStatus::SUPPORT_APPROVED,
            RequestStatus::WAITING_FOR_SWIFT => UserRole::SWIFT_OFFICER,

            RequestStatus::SWIFT_UPLOADED,
            RequestStatus::WAITING_FOR_VOTING_OPEN => UserRole::COMMITTEE_DIRECTOR,

            RequestStatus::EXECUTIVE_VOTING_OPEN,
            RequestStatus::EXECUTIVE_VOTING_CLOSED => UserRole::EXECUTIVE_MEMBER,

            RequestStatus::EXECUTIVE_APPROVED,
            RequestStatus::EXECUTIVE_REJECTED,
            RequestStatus::CUSTOMS_DECLARATION_ISSUED,
            RequestStatus::COMPLETED => UserRole::COMMITTEE_DIRECTOR,
        };
    }

    private function resolveVotingSessionStatus(string $scenario): ?string
    {
        return match (true) {
            in_array($scenario, ['executive_voting_open', 'executive_voting_open_tie'], true) => VotingSessionStatus::OPEN->value,
            $scenario === 'executive_voting_closed' => VotingSessionStatus::CLOSED->value,
            in_array($scenario, ['executive_approved', 'executive_rejected', 'customs_declaration_issued', 'completed', 'completed_with_revision'], true) => VotingSessionStatus::FINALIZED->value,
            default => null,
        };
    }

    private function resolveClaim(array $data, Collection $supportUsers): array
    {
        [$claimedBy, $claimedAt, $claimExpiresAt] = [null, null, null];

        if (($data['claim_state'] ?? null) === 'active') {
            $claimer = $supportUsers->first();
            $claimedBy = $claimer?->id;
            $claimedAt = now()->subMinutes(5);
            $claimExpiresAt = now()->addMinutes(10);
        } elseif (($data['claim_state'] ?? null) === 'expired') {
            $claimer = $supportUsers->skip(1)->first() ?? $supportUsers->first();
            $claimedBy = $claimer?->id;
            $claimedAt = now()->subHours(2);
            $claimExpiresAt = now()->subHour();
        }

        return [$claimedBy, $claimedAt, $claimExpiresAt];
    }

    // -------------------------------------------------------------------------
    // Timeline
    // -------------------------------------------------------------------------

    private function timelineForStatus(RequestStatus $status, ?Carbon $baseDate = null): array
    {
        $created = $baseDate ?? now()->subDays(rand(20, 80));
        $submitted = $created->copy()->addDays(rand(1, 3));
        $bankApproved = $submitted->copy()->addDays(rand(1, 3));
        $supportApproved = $bankApproved->copy()->addDays(rand(2, 5));
        $swiftUploaded = $supportApproved->copy()->addDays(rand(1, 3));
        $votingOpened = $swiftUploaded->copy()->addDays(rand(1, 3));
        $votingClosed = $votingOpened->copy()->addDays(rand(1, 2));
        $execDecided = $votingClosed->copy()->addHours(rand(1, 12));
        $customsIssued = $execDecided->copy()->addDays(rand(1, 2));
        $completed = $customsIssued->copy()->addHours(rand(1, 24));

        $r = fn (RequestStatus $t) => $this->reached($t, $status);

        return [
            'created_at' => $created,
            'submitted_at' => $r(RequestStatus::SUBMITTED) ? $submitted : null,
            'bank_approved_at' => $r(RequestStatus::BANK_APPROVED) ? $bankApproved : null,
            'support_approved_at' => $r(RequestStatus::SUPPORT_APPROVED) ? $supportApproved : null,
            'swift_uploaded_at' => $r(RequestStatus::SWIFT_UPLOADED) ? $swiftUploaded : null,
            'voting_opened_at' => $r(RequestStatus::EXECUTIVE_VOTING_OPEN) ? $votingOpened : null,
            'voting_closed_at' => $r(RequestStatus::EXECUTIVE_VOTING_CLOSED) ? $votingClosed : null,
            'executive_decided_at' => ($r(RequestStatus::EXECUTIVE_APPROVED) || $status === RequestStatus::EXECUTIVE_REJECTED) ? $execDecided : null,
            'final_decision_at' => ($r(RequestStatus::EXECUTIVE_APPROVED) || $status === RequestStatus::EXECUTIVE_REJECTED) ? $execDecided : null,
            'customs_issued_at' => $r(RequestStatus::CUSTOMS_DECLARATION_ISSUED) ? $customsIssued : null,
            'updated_at' => $completed,
        ];
    }

    /**
     * Workflow progress order. Branching/terminal statuses sit at the level they
     * branched FROM so `reached()` produces correct boolean results.
     *
     * DRAFT_REJECTED_INTERNAL / BANK_RETURNED / BANK_REJECTED all exited at
     * BANK_REVIEW level (order 3) without reaching BANK_APPROVED (order 4).
     *
     * SUPPORT_REJECTED / SUPPORT_RETURNED exited at SUPPORT_REVIEW_IN_PROGRESS
     * level (order 5) without reaching SUPPORT_APPROVED (order 6).
     */
    private function reached(RequestStatus $target, RequestStatus $current): bool
    {
        static $order = null;
        if ($order === null) {
            $order = [
                RequestStatus::DRAFT->value => 1,
                RequestStatus::SUBMITTED->value => 2,
                RequestStatus::BANK_REVIEW->value => 3,
                RequestStatus::DRAFT_REJECTED_INTERNAL->value => 3,
                RequestStatus::BANK_RETURNED->value => 3,
                RequestStatus::BANK_REJECTED->value => 3,
                RequestStatus::BANK_APPROVED->value => 4,
                RequestStatus::SUPPORT_REVIEW_PENDING->value => 4,
                RequestStatus::SUPPORT_REVIEW_IN_PROGRESS->value => 5,
                RequestStatus::SUPPORT_REJECTED->value => 5,
                RequestStatus::SUPPORT_RETURNED->value => 5,
                RequestStatus::SUPPORT_APPROVED->value => 6,
                RequestStatus::WAITING_FOR_SWIFT->value => 6,
                RequestStatus::SWIFT_UPLOADED->value => 7,
                RequestStatus::WAITING_FOR_VOTING_OPEN->value => 7,
                RequestStatus::EXECUTIVE_VOTING_OPEN->value => 8,
                RequestStatus::EXECUTIVE_VOTING_CLOSED->value => 9,
                RequestStatus::EXECUTIVE_APPROVED->value => 10,
                RequestStatus::EXECUTIVE_REJECTED->value => 10,
                RequestStatus::CUSTOMS_DECLARATION_ISSUED->value => 11,
                RequestStatus::COMPLETED->value => 12,
            ];
        }

        return ($order[$current->value] ?? 0) >= ($order[$target->value] ?? 0);
    }

    // -------------------------------------------------------------------------
    // Document seeding
    // -------------------------------------------------------------------------

    private function docTypes(): Collection
    {
        if ($this->documentTypes === null) {
            $this->documentTypes = DocumentType::query()->where('is_active', true)->orderBy('sort_order')->get();
        }

        return $this->documentTypes;
    }

    private function seedRequestDocs(ImportRequest $request, User $actor, Carbon $at): void
    {
        $types = $this->docTypes();
        $required = $types->where('is_required', true)->values();
        $optional = $types->where('is_required', false)->values();

        $isDraft = $request->status === RequestStatus::DRAFT;
        $count = $isDraft ? rand(0, 2) : rand(3, min(6, $required->count() + $optional->count()));

        // Always include required docs for non-draft; pad with optional ones
        if ($isDraft) {
            $selected = $required->take($count)->merge($optional->take(max(0, $count - $required->count())))->values();
        } else {
            $extra = max(0, $count - $required->count());
            $selected = $required->merge($optional->take($extra))->values();
        }

        $filenames = [
            'commercial_invoice' => ['commercial_invoice.pdf', 'invoice_supplier.pdf', 'فاتورة_تجارية.pdf'],
            'packing_list' => ['packing_list.pdf', 'قائمة_التعبئة.pdf'],
            'bill_of_lading' => ['bill_of_lading.pdf', 'سند_الشحن.pdf'],
            'certificate_of_origin' => ['certificate_of_origin.pdf', 'شهادة_المنشأ.pdf'],
            'import_license' => ['import_license.pdf', 'رخصة_الاستيراد.pdf'],
            'insurance_policy' => ['insurance_policy.pdf', 'وثيقة_التأمين.pdf'],
            'quality_certificate' => ['quality_certificate.pdf', 'شهادة_الجودة.pdf'],
            'other' => ['supporting_document.pdf', 'وثيقة_داعمة.pdf'],
        ];

        foreach ($selected as $i => $docType) {
            $filename = Arr::random($filenames[$docType->slug] ?? ['document.pdf']);
            $storedPath = "requests/{$request->id}/".Str::uuid().'.pdf';
            $file = $this->writeSeedPdf($storedPath, $filename);
            $doc = RequestDocument::query()->create([
                'request_id' => $request->id,
                'uploaded_by' => $actor->id,
                'type' => 'REQUEST_DOC',
                'document_type_id' => $docType->id,
                'original_filename' => $filename,
                'stored_path' => $storedPath,
                'mime_type' => 'application/pdf',
                'size_bytes' => $file['size'],
                'checksum' => $file['checksum'],
                'created_at' => $at->copy()->addMinutes($i + 1),
                'updated_at' => $at->copy()->addMinutes($i + 1),
            ]);
            $this->log(AuditAction::DOCUMENT_UPLOADED, $actor, $doc, ['request_id' => $request->id], $doc->created_at);
        }
    }

    private function seedFxRequestDoc(ImportRequest $request, User $actor, Carbon $at): void
    {
        $storedPath = "fx-request/{$request->id}/".Str::uuid().'.pdf';
        $file = $this->writeSeedPdf($storedPath, 'fx_confirmation_request.pdf');
        $doc = RequestDocument::query()->create([
            'request_id' => $request->id,
            'uploaded_by' => $actor->id,
            'type' => 'FX_REQUEST',
            'document_type_id' => null,
            'original_filename' => 'fx_confirmation_request.pdf',
            'stored_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => $file['size'],
            'checksum' => $file['checksum'],
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $this->log(AuditAction::DOCUMENT_UPLOADED, $actor, $doc, ['request_id' => $request->id, 'type' => 'FX_REQUEST'], $at);
    }

    private function seedSwiftDoc(ImportRequest $request, User $actor, Carbon $at): void
    {
        $storedPath = "swift/{$request->id}/".Str::uuid().'.pdf';
        $file = $this->writeSeedPdf($storedPath, 'swift_message.pdf');
        $doc = RequestDocument::query()->create([
            'request_id' => $request->id,
            'uploaded_by' => $actor->id,
            'type' => 'SWIFT',
            'document_type_id' => null,
            'original_filename' => 'swift_message.pdf',
            'stored_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => $file['size'],
            'checksum' => $file['checksum'],
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $this->log(AuditAction::SWIFT_UPLOADED, $actor, $doc, ['request_id' => $request->id], $at);
    }

    private function seedCustomsDoc(ImportRequest $request, User $actor, string $declNo, Carbon $at): void
    {
        $filename = "customs_declaration_{$declNo}.pdf";
        $storedPath = "customs/{$request->id}/".Str::uuid().'.pdf';
        $file = $this->writeSeedPdf($storedPath, $filename);
        $doc = RequestDocument::query()->create([
            'request_id' => $request->id,
            'uploaded_by' => $actor->id,
            'type' => 'CUSTOMS',
            'document_type_id' => null,
            'original_filename' => $filename,
            'stored_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => $file['size'],
            'checksum' => $file['checksum'],
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $this->log(AuditAction::DOCUMENT_UPLOADED, $actor, $doc, ['request_id' => $request->id], $at);
    }

    /**
     * @return array{size: int, checksum: string}
     */
    private function writeSeedPdf(string $storedPath, string $title): array
    {
        $content = $this->seedPdfContent($title);
        Storage::disk('local')->put('private/'.$storedPath, $content);

        return [
            'size' => strlen($content),
            'checksum' => hash('sha256', $content),
        ];
    }

    private function seedPdfContent(string $title): string
    {
        $safeTitle = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $title);
        $stream = "BT /F1 14 Tf 72 760 Td (The National Committee for Regulating & Financing Imports seeded PDF) Tj 0 -24 Td ({$safeTitle}) Tj ET\n";
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
            '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
            '5 0 obj << /Length '.strlen($stream)." >> stream\n{$stream}endstream endobj",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer << /Root 1 0 R /Size 6 >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
    }

    // -------------------------------------------------------------------------
    // Stage history seeding (clean step-by-step pipeline)
    // -------------------------------------------------------------------------

    private function seedHistory(
        string $scenario,
        ImportRequest $request,
        User $entry, User $reviewer, User $support,
        User $swift, User $director,
        array $timeline,
    ): void {
        $steps = [];
        $add = function (
            ?RequestStatus $from, RequestStatus $to,
            UserRole $fromOwner, ?UserRole $toOwner,
            User $actor, string $action,
            ?string $reason = null, ?Carbon $at = null,
        ) use (&$steps): void {
            $steps[] = compact('from', 'to', 'fromOwner', 'toOwner', 'actor', 'action', 'reason', 'at');
        };

        $sub = $timeline['submitted_at'];
        $bkAt = $timeline['bank_approved_at'];
        $spAt = $timeline['support_approved_at'];
        $swAt = $timeline['swift_uploaded_at'];
        $voAt = $timeline['voting_opened_at'];
        $vcAt = $timeline['voting_closed_at'];
        $edAt = $timeline['executive_decided_at'];
        $csAt = $timeline['customs_issued_at'];

        // Derive intermediate timestamps where direct timestamps are unavailable
        $bankBeganAt = $sub ? $sub->copy()->addHours(rand(1, 4)) : now();
        $bankDecidedAt = $sub ? $sub->copy()->addDays(rand(1, 3)) : now();
        $spClaimAt = $bkAt ? $bkAt->copy()->addHours(rand(6, 24)) : now();
        $spDecidedAt = $bkAt ? $bkAt->copy()->addDays(rand(2, 5)) : now();

        // Step 1 — DRAFT → SUBMITTED
        if ($scenario !== 'draft') {
            $add(RequestStatus::DRAFT, RequestStatus::SUBMITTED,
                UserRole::DATA_ENTRY, UserRole::BANK_REVIEWER,
                $entry, 'submit', null, $sub);
        }

        // Step 2 — SUBMITTED → BANK_REVIEW  (bank reviewer opens it)
        $bankReviewStarted = ! in_array($scenario, ['draft', 'submitted'], true);
        if ($bankReviewStarted) {
            $add(RequestStatus::SUBMITTED, RequestStatus::BANK_REVIEW,
                UserRole::BANK_REVIEWER, UserRole::BANK_REVIEWER,
                $reviewer, 'bank_begin_review', null, $bankBeganAt);
        }

        // Step 3 — bank exits
        if ($scenario === 'draft_rejected_internal') {
            $add(RequestStatus::BANK_REVIEW, RequestStatus::DRAFT_REJECTED_INTERNAL,
                UserRole::BANK_REVIEWER, UserRole::DATA_ENTRY,
                $reviewer, 'bank_return', 'Incomplete documents — please resubmit', $bkAt ?? $bankDecidedAt);
        } elseif ($scenario === 'bank_returned') {
            $add(RequestStatus::BANK_REVIEW, RequestStatus::BANK_RETURNED,
                UserRole::BANK_REVIEWER, UserRole::DATA_ENTRY,
                $reviewer, 'bank_return', 'Needs correction before approval', $bkAt ?? $bankDecidedAt);
        } elseif ($scenario === 'bank_rejected') {
            $add(RequestStatus::BANK_REVIEW, RequestStatus::BANK_REJECTED,
                UserRole::BANK_REVIEWER, UserRole::BANK_REVIEWER,
                $reviewer, 'bank_reject_terminal', 'Non-compliant request — permanently rejected', $bkAt ?? $bankDecidedAt);
        } elseif ($bankReviewStarted && ! in_array($scenario, ['bank_review'], true)) {
            // Bank approved path
            $add(RequestStatus::BANK_REVIEW, RequestStatus::BANK_APPROVED,
                UserRole::BANK_REVIEWER, UserRole::SUPPORT_COMMITTEE,
                $reviewer, 'bank_approve', null, $bkAt);
            $add(RequestStatus::BANK_APPROVED, RequestStatus::SUPPORT_REVIEW_PENDING,
                UserRole::SUPPORT_COMMITTEE, UserRole::SUPPORT_COMMITTEE,
                $reviewer, 'move_to_support_queue', null, $bkAt ? $bkAt->copy()->addMinute() : now());
        }

        // Step 4 — support claims
        $supportClaimStarted = ! in_array($scenario, [
            'draft', 'submitted', 'bank_review',
            'draft_rejected_internal', 'bank_returned', 'bank_rejected',
            'bank_approved', 'support_review_pending',
        ], true);
        if ($supportClaimStarted) {
            $add(RequestStatus::SUPPORT_REVIEW_PENDING, RequestStatus::SUPPORT_REVIEW_IN_PROGRESS,
                UserRole::SUPPORT_COMMITTEE, UserRole::SUPPORT_COMMITTEE,
                $support, 'support_claim', null, $spClaimAt);
        }

        // Step 5 — support exits
        if ($scenario === 'support_rejected') {
            $add(RequestStatus::SUPPORT_REVIEW_IN_PROGRESS, RequestStatus::SUPPORT_REJECTED,
                UserRole::SUPPORT_COMMITTEE, UserRole::BANK_REVIEWER,
                $support, 'support_reject', 'Compliance gap identified — returned to bank', $spDecidedAt);
        } elseif ($scenario === 'support_returned') {
            $add(RequestStatus::SUPPORT_REVIEW_IN_PROGRESS, RequestStatus::SUPPORT_RETURNED,
                UserRole::SUPPORT_COMMITTEE, UserRole::DATA_ENTRY,
                $support, 'support_return', 'Requires additional documentation from bank', $spDecidedAt);
        } elseif ($supportClaimStarted && ! in_array($scenario, [
            'support_review_in_progress_claimed', 'support_review_in_progress_expired',
            'support_rejected', 'support_returned',
        ], true)) {
            $add(RequestStatus::SUPPORT_REVIEW_IN_PROGRESS, RequestStatus::SUPPORT_APPROVED,
                UserRole::SUPPORT_COMMITTEE, UserRole::SWIFT_OFFICER,
                $support, 'support_approve', null, $spAt);
            $add(RequestStatus::SUPPORT_APPROVED, RequestStatus::WAITING_FOR_SWIFT,
                UserRole::SWIFT_OFFICER, UserRole::SWIFT_OFFICER,
                $support, 'move_to_swift_queue', null, $spAt ? $spAt->copy()->addMinute() : now());
        }

        // Step 6 — SWIFT upload
        $swiftScenarios = ['swift_uploaded', 'waiting_for_voting_open', 'executive_voting_open', 'executive_voting_open_tie', 'executive_voting_closed', 'executive_approved', 'executive_rejected', 'customs_declaration_issued', 'completed', 'completed_with_revision'];
        if (in_array($scenario, $swiftScenarios, true)) {
            $add(RequestStatus::WAITING_FOR_SWIFT, RequestStatus::SWIFT_UPLOADED,
                UserRole::SWIFT_OFFICER, UserRole::COMMITTEE_DIRECTOR,
                $swift, 'swift_upload', null, $swAt);
            $add(RequestStatus::SWIFT_UPLOADED, RequestStatus::WAITING_FOR_VOTING_OPEN,
                UserRole::COMMITTEE_DIRECTOR, UserRole::COMMITTEE_DIRECTOR,
                $swift, 'move_to_voting_queue', null, $swAt ? $swAt->copy()->addMinute() : now());
        }

        // Step 7 — open voting
        $votingScenarios = ['executive_voting_open', 'executive_voting_open_tie', 'executive_voting_closed', 'executive_approved', 'executive_rejected', 'customs_declaration_issued', 'completed', 'completed_with_revision'];
        if (in_array($scenario, $votingScenarios, true)) {
            $add(RequestStatus::WAITING_FOR_VOTING_OPEN, RequestStatus::EXECUTIVE_VOTING_OPEN,
                UserRole::COMMITTEE_DIRECTOR, UserRole::EXECUTIVE_MEMBER,
                $director, 'open_voting', null, $voAt);
        }

        // Step 8 — close voting
        $closedScenarios = ['executive_voting_closed', 'executive_approved', 'executive_rejected', 'customs_declaration_issued', 'completed', 'completed_with_revision'];
        if (in_array($scenario, $closedScenarios, true)) {
            $add(RequestStatus::EXECUTIVE_VOTING_OPEN, RequestStatus::EXECUTIVE_VOTING_CLOSED,
                UserRole::EXECUTIVE_MEMBER, UserRole::COMMITTEE_DIRECTOR,
                $director, 'close_voting', null, $vcAt);
        }

        // Step 9 — finalize decision
        if ($scenario === 'executive_rejected') {
            $add(RequestStatus::EXECUTIVE_VOTING_CLOSED, RequestStatus::EXECUTIVE_REJECTED,
                UserRole::COMMITTEE_DIRECTOR, null,
                $director, 'finalize_rejected', 'Majority voted against', $edAt);
        } elseif (in_array($scenario, ['executive_approved', 'customs_declaration_issued', 'completed', 'completed_with_revision'], true)) {
            $add(RequestStatus::EXECUTIVE_VOTING_CLOSED, RequestStatus::EXECUTIVE_APPROVED,
                UserRole::COMMITTEE_DIRECTOR, UserRole::COMMITTEE_DIRECTOR,
                $director, 'finalize_approved', null, $edAt);
        }

        // Step 10 — issue customs
        if (in_array($scenario, ['customs_declaration_issued', 'completed', 'completed_with_revision'], true)) {
            $add(RequestStatus::EXECUTIVE_APPROVED, RequestStatus::CUSTOMS_DECLARATION_ISSUED,
                UserRole::COMMITTEE_DIRECTOR, UserRole::COMMITTEE_DIRECTOR,
                $director, 'issue_customs', null, $csAt);
        }

        // Step 11 — complete
        if (in_array($scenario, ['completed', 'completed_with_revision'], true)) {
            $add(RequestStatus::CUSTOMS_DECLARATION_ISSUED, RequestStatus::COMPLETED,
                UserRole::COMMITTEE_DIRECTOR, null,
                $director, 'complete', null, $csAt ? $csAt->copy()->addHour() : now());
        }

        foreach ($steps as $step) {
            $row = RequestStageHistory::query()->create([
                'request_id' => $request->id,
                'from_status' => $step['from'],
                'to_status' => $step['to'],
                'from_owner_role' => $step['fromOwner'],
                'to_owner_role' => $step['toOwner'],
                'actor_id' => $step['actor']->id,
                'actor_role' => $step['actor']->role,
                'action' => $step['action'],
                'reason' => $step['reason'],
                'metadata' => null,
                'created_at' => $step['at'] ?? now(),
                'updated_at' => $step['at'] ?? now(),
            ]);
            $this->log(
                AuditAction::STATUS_TRANSITION,
                $step['actor'], $request,
                ['history_id' => $row->id, 'to_status' => $step['to']->value],
                $row->created_at,
            );
        }
    }

    // -------------------------------------------------------------------------
    // Vote seeding
    // -------------------------------------------------------------------------

    private function seedVotes(string $scenario, ImportRequest $request, Collection $execs, array $timeline): void
    {
        $votingScenarios = [
            'executive_voting_open', 'executive_voting_open_tie', 'executive_voting_closed',
            'executive_approved', 'executive_rejected',
            'customs_declaration_issued', 'completed', 'completed_with_revision',
        ];
        if (! in_array($scenario, $votingScenarios, true)) {
            return;
        }

        $votes = match ($scenario) {
            'executive_voting_open' => array_fill(0, rand(1, min(4, $execs->count())), null),
            'executive_voting_open_tie' => [VoteType::APPROVE, VoteType::APPROVE, VoteType::APPROVE, VoteType::REJECT, VoteType::REJECT, VoteType::REJECT],
            'executive_voting_closed' => [VoteType::APPROVE, VoteType::APPROVE, VoteType::APPROVE, VoteType::APPROVE, VoteType::REJECT, VoteType::ABSTAIN],
            'executive_approved',
            'customs_declaration_issued',
            'completed',
            'completed_with_revision' => [VoteType::APPROVE, VoteType::APPROVE, VoteType::APPROVE, VoteType::APPROVE, VoteType::REJECT, VoteType::ABSTAIN],
            'executive_rejected' => [VoteType::REJECT, VoteType::REJECT, VoteType::REJECT, VoteType::REJECT, VoteType::APPROVE, VoteType::AUTO_ABSTAIN_TIMEOUT],
            default => [],
        };

        $baseAt = $timeline['voting_opened_at'] ?? now();
        foreach ($votes as $idx => $voteType) {
            if ($voteType === null) {
                $voteType = Arr::random([VoteType::APPROVE, VoteType::REJECT, VoteType::ABSTAIN]);
            }
            $actor = $execs[$idx] ?? $execs->last();
            $at = $baseAt->copy()->addHours($idx + 1);
            $vote = RequestVote::query()->create([
                'request_id' => $request->id,
                'user_id' => $actor->id,
                'vote' => $voteType,
                'justification' => fake()->boolean(50)
                    ? Arr::random(['Need stronger documentation', 'High risk profile', 'Compliant with regulations', 'Insufficient collateral', 'All checks passed'])
                    : null,
                'is_director_override' => false,
                'voted_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $this->log(AuditAction::VOTE_CAST, $actor, $vote, ['request_id' => $request->id], $at);
        }
    }

    // -------------------------------------------------------------------------
    // Customs declaration seeding
    // -------------------------------------------------------------------------

    private function seedCustomsDeclaration(ImportRequest $request, User $issuer, Carbon $at): CustomsDeclaration
    {
        $year = now()->format('Y');
        $number = 'CD-'.$year.'-'.str_pad((string) $this->customsSequence++, 6, '0', STR_PAD_LEFT);

        $decl = CustomsDeclaration::query()->create([
            'request_id' => $request->id,
            'declaration_number' => $number,
            'issued_by' => $issuer->id,
            'issued_at' => $at,
            'pdf_path' => "customs/{$request->id}/{$number}.pdf",
            'metadata' => [
                'supplier' => $request->supplier_name,
                'amount' => (float) $request->amount,
                'currency' => is_string($request->currency) ? $request->currency : $request->currency?->value,
                'goods' => $request->goods_description,
                'port' => $request->port_of_entry,
                'invoice' => $request->invoice_number,
                'bl_number' => $request->bl_number,
            ],
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $this->log(AuditAction::CUSTOMS_ISSUED, $issuer, $decl, ['request_id' => $request->id], $at);

        return $decl;
    }

    // -------------------------------------------------------------------------
    // Audit log helper
    // -------------------------------------------------------------------------

    private function log(AuditAction $action, User $actor, $subject, array $meta, Carbon $at): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'user_role' => $actor->role->value,
            'action' => $action->value,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Seeder/2.0',
            'metadata' => $meta,
            'created_at' => $at,
        ]);
    }
}
