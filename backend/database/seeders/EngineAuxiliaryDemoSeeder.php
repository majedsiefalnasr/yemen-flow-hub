<?php

namespace Database\Seeders;

use App\Enums\NotificationType;
use App\Models\CustomsDeclaration;
use App\Models\EngineNotification;
use App\Models\EngineRequest;
use App\Models\NotificationRecipient;
use App\Models\NotificationTemplateVersion;
use App\Models\ReportExport;
use App\Models\User;
use Database\Seeders\Catalog\SeederCatalog;
use Database\Seeders\Concerns\GuardsDemoSeedEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds auxiliary demo data — External FX Confirmation deliverables, engine
 * notifications, email deliveries, and report exports — hooked off the
 * SeederCatalog anchor references produced by EngineRequestAnchorSeeder.
 *
 * Every row uses a natural-key idempotent upsert (per spec § Idempotency),
 * not a blanket "skip if any row exists" guard, so reruns and partial
 * anchor sets backfill correctly.
 *
 * Spec: backend/docs/superpowers/specs/2026-07-07-engine-demo-seeder-redesign-design.md § Auxiliary demo data
 */
class EngineAuxiliaryDemoSeeder extends Seeder
{
    use GuardsDemoSeedEnvironment;

    public function run(): void
    {
        $this->ensureDemoSeedAllowed();

        $requests = EngineRequest::query()
            ->with('currentStage')
            ->whereIn('reference', $this->neededReferences())
            ->get()
            ->keyBy('reference');

        if ($requests->isEmpty()) {
            $this->command?->warn('No anchor engine requests exist — auxiliary demo data skipped.');

            return;
        }

        $director = User::query()->where('email', 'director@cby.gov.ye')->firstOrFail();
        $entry = User::query()->where('email', 'entry@ybrd.com.ye')->firstOrFail();
        $support = User::query()->where('email', 'support1@cby.gov.ye')->firstOrFail();

        $this->seedFxConfirmations($requests, $director);
        $this->seedNotifications($requests, $entry, $support, $director);
        $this->seedEmailDeliveries($requests, $entry, $support, $director);
        $this->seedReportExports($director);
    }

    /**
     * @return array<int, string>
     */
    private function neededReferences(): array
    {
        return [
            SeederCatalog::ANCHOR_SUBMITTED_NOTIFICATION,
            SeederCatalog::ANCHOR_SUPPORT_CLAIM_ACTIVE,
            SeederCatalog::ANCHOR_FX_CONFIRM_PANEL,
            SeederCatalog::ANCHOR_FX_CONFIRM_COMPLETED_PRIMARY,
            SeederCatalog::ANCHOR_FX_CONFIRM_COMPLETED_SECONDARY,
            SeederCatalog::ANCHOR_REJECTED_NOTIFICATION,
        ];
    }

    /**
     * External FX confirmation deliverable: primary anchor gets an active
     * signed document plus a superseded prior version; secondary anchor gets
     * a single active signed document.
     *
     * @param  Collection<string, EngineRequest>  $requests
     */
    private function seedFxConfirmations($requests, User $director): void
    {
        $primary = $requests[SeederCatalog::ANCHOR_FX_CONFIRM_COMPLETED_PRIMARY] ?? null;
        $secondary = $requests[SeederCatalog::ANCHOR_FX_CONFIRM_COMPLETED_SECONDARY] ?? null;

        foreach (array_filter([$primary, $secondary]) as $request) {
            $declarationNumber = 'FX-2026-'.$request->reference;
            $pdfPath = "fx-confirmations/{$request->reference}/declaration.pdf";
            $signedPath = "fx-confirmations/{$request->reference}/signed-fx.pdf";

            $this->putPdf($pdfPath, "External FX confirmation for {$request->reference}");
            $this->putPdf($signedPath, "Signed FX confirmation for {$request->reference}");

            CustomsDeclaration::query()->updateOrCreate(
                ['engine_request_id' => $request->id, 'declaration_number' => $declarationNumber],
                [
                    'issued_by' => $director->id,
                    'issued_at' => Carbon::parse('2026-06-20 10:00:00'),
                    'pdf_path' => $pdfPath,
                    'signed_fx_doc_path' => $signedPath,
                    'signed_fx_doc_uploaded_at' => Carbon::parse('2026-06-20 12:00:00'),
                    'signed_fx_doc_uploaded_by' => $director->id,
                    'metadata' => [
                        'seeded' => true,
                        'reference' => $request->reference,
                        'amount' => $request->amount,
                        'currency' => $request->currency,
                    ],
                ],
            );
        }
    }

    /**
     * @param  Collection<string, EngineRequest>  $requests
     */
    private function seedNotifications($requests, User $entry, User $support, User $director): void
    {
        $rows = [
            ['REQUEST_SUBMITTED', 'info', 'تم تقديم طلب جديد', 'طلب جديد بانتظار المراجعة البنكية.', SeederCatalog::ANCHOR_SUBMITTED_NOTIFICATION, $entry],
            ['SUPPORT_REVIEW', 'warning', 'طلب بانتظار لجنة الدعم', 'يوجد طلب يحتاج إلى مراجعة لجنة الدعم.', SeederCatalog::ANCHOR_SUPPORT_CLAIM_ACTIVE, $support],
            ['FX_CONFIRMATION', 'info', 'تأكيد مصارفة خارجية', 'طلب جاهز لتأكيد المصارفة الخارجية.', SeederCatalog::ANCHOR_FX_CONFIRM_PANEL, $director],
            ['REQUEST_COMPLETED', 'success', 'اكتمل الطلب', 'تم إغلاق الطلب وإصدار بياناته النهائية.', SeederCatalog::ANCHOR_FX_CONFIRM_COMPLETED_PRIMARY, $entry],
            ['REQUEST_REJECTED', 'error', 'تم رفض الطلب', 'تم رفض الطلب من قبل اللجنة التنفيذية.', SeederCatalog::ANCHOR_REJECTED_NOTIFICATION, $director],
        ];

        foreach ($rows as [$type, $severity, $title, $body, $reference, $recipient]) {
            $request = $requests[$reference] ?? null;
            if ($request === null) {
                continue;
            }

            $notification = EngineNotification::query()->firstOrCreate(
                ['type' => $type, 'entity_type' => EngineRequest::class, 'entity_id' => $request->id],
                [
                    'severity' => $severity,
                    'title' => $title,
                    'body' => $body,
                    'action_url' => "/workflows/instances/{$request->id}",
                ],
            );

            NotificationRecipient::query()->firstOrCreate(
                ['notification_id' => $notification->id, 'user_id' => $recipient->id],
                ['read_at' => $severity === 'success' ? now()->subDay() : null, 'archived_at' => null],
            );
        }
    }

    /**
     * @param  Collection<string, EngineRequest>  $requests
     */
    private function seedEmailDeliveries($requests, User $entry, User $support, User $director): void
    {
        $templateVersionId = NotificationTemplateVersion::query()
            ->whereHas('template', fn ($query) => $query->where('notification_type', NotificationType::REQUEST_APPROVED->value))
            ->value('id');

        $rows = [
            [NotificationType::REQUEST_APPROVED->value, SeederCatalog::ANCHOR_FX_CONFIRM_COMPLETED_PRIMARY, $entry, 'sent', 'تمت الموافقة على طلبكم'],
            [NotificationType::REQUEST_RETURNED->value, SeederCatalog::ANCHOR_SUPPORT_CLAIM_ACTIVE, $support, 'queued', 'طلب يحتاج إلى مراجعة'],
            [NotificationType::REQUEST_REJECTED->value, SeederCatalog::ANCHOR_REJECTED_NOTIFICATION, $director, 'failed', 'تعذر إرسال إشعار الرفض'],
        ];

        foreach ($rows as [$type, $reference, $recipient, $status, $subject]) {
            $request = $requests[$reference] ?? null;
            if ($request === null) {
                continue;
            }

            $eventId = "seeded:{$reference}:{$type}";
            if (DB::table('email_deliveries')->where('event_id', $eventId)->exists()) {
                continue;
            }

            $now = Carbon::parse('2026-06-20 09:00:00');

            DB::table('email_deliveries')->insert([
                'notification_type' => $type,
                'event_id' => $eventId,
                'recipient_user_id' => $recipient->id,
                'recipient_email' => $recipient->email,
                'channel' => 'mail',
                'status' => $status,
                'provider_message_id' => $status === 'sent' ? "seeded-{$reference}" : null,
                'rendered_subject' => $subject,
                'rendered_body' => "رقم الطلب: {$reference}",
                'template_version_id' => $templateVersionId,
                'error' => $status === 'failed' ? 'Seeded delivery failure for UI testing.' : null,
                'queued_at' => $now,
                'dispatched_at' => $status === 'queued' ? null : $now->copy()->addMinute(),
                'failed_at' => $status === 'failed' ? $now->copy()->addMinutes(2) : null,
                'sent_at' => $status === 'sent' ? $now->copy()->addMinutes(2) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedReportExports(User $director): void
    {
        $completedPath = 'reports/seeded-summary.csv';
        Storage::disk('private')->put($completedPath, "metric,value\nengine_requests,56\nclosed_requests,4\n");

        ReportExport::query()->updateOrCreate(
            ['requested_by' => $director->id, 'report_type' => 'summary', 'format' => 'csv'],
            [
                'filters' => ['seeded' => true],
                'status' => 'completed',
                'file_path' => $completedPath,
                'total_matching' => 56,
                'exported_count' => 56,
                'truncated' => false,
                'version' => 1,
            ],
        );

        ReportExport::query()->updateOrCreate(
            ['requested_by' => $director->id, 'report_type' => 'by-workflow-stage', 'format' => 'csv'],
            [
                'filters' => ['seeded' => true],
                'status' => 'truncated',
                'file_path' => null,
                'total_matching' => 5000,
                'exported_count' => 1000,
                'truncated' => true,
                'truncation_note' => 'Seeded demo truncation for export-limit UI testing.',
                'version' => 1,
            ],
        );

        ReportExport::query()->updateOrCreate(
            ['requested_by' => $director->id, 'report_type' => 'by-bank', 'format' => 'csv'],
            [
                'filters' => ['seeded' => true],
                'status' => 'failed',
                'file_path' => null,
                'truncated' => false,
                'version' => 1,
            ],
        );
    }

    private function putPdf(string $path, string $title): void
    {
        Storage::disk('private')->put($path, $this->pdfBody($title));
    }

    private function pdfBody(string $title): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n% {$title}\n%%EOF\n";
    }
}
