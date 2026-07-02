<?php

namespace Database\Seeders;

use App\Enums\NotificationType;
use App\Models\CustomsDeclaration;
use App\Models\EngineNotification;
use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\FieldDefinition;
use App\Models\NotificationRecipient;
use App\Models\NotificationTemplateVersion;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EngineAuxiliaryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $requests = EngineRequest::query()
            ->with('currentStage')
            ->orderBy('reference')
            ->get()
            ->keyBy('reference');

        if ($requests->isEmpty()) {
            $this->command?->warn('No engine requests exist — auxiliary demo data skipped.');

            return;
        }

        $director = User::query()->where('email', 'director@cby.gov.ye')->firstOrFail();
        $entry = User::query()->where('email', 'entry@ybrd.com.ye')->firstOrFail();
        $support = User::query()->where('email', 'support1@cby.gov.ye')->firstOrFail();

        $this->seedDocuments($requests, $entry);
        $this->seedCustomsDeclarations($requests, $director);
        $this->seedNotifications($requests, $entry, $support, $director);
        $this->seedEmailDeliveries($requests, $entry, $support, $director);
        $this->seedReportExports($director);
    }

    private function seedDocuments($requests, User $entry): void
    {
        if (EngineRequestDocument::query()->exists()) {
            return;
        }

        $fieldId = FieldDefinition::query()->where('key', 'docCommercialInvoice')->value('id');

        foreach ($requests as $request) {
            $path = "engine-requests/{$request->id}/seeded-commercial-invoice.pdf";
            $this->putPdf($path, "Commercial invoice for {$request->reference}");

            EngineRequestDocument::query()->create([
                'request_id' => $request->id,
                'field_id' => $fieldId,
                'uploaded_by' => $entry->id,
                'stage_id' => $request->current_stage_id,
                'original_name' => "{$request->reference}-commercial-invoice.pdf",
                'path' => $path,
                'mime' => 'application/pdf',
                'size' => strlen($this->pdfBody("Commercial invoice for {$request->reference}")),
                'checksum' => hash('sha256', $this->pdfBody("Commercial invoice for {$request->reference}")),
                'version' => 1,
            ]);
        }
    }

    private function seedCustomsDeclarations($requests, User $director): void
    {
        if (CustomsDeclaration::query()->whereNotNull('engine_request_id')->exists()) {
            return;
        }

        foreach (['ENG-2026-002019', 'ENG-2026-002018'] as $reference) {
            $request = $requests[$reference];
            $sequence = substr($reference, -6);
            $pdfPath = "customs/{$reference}/declaration.pdf";
            $signedPath = "customs/{$reference}/signed-fx.pdf";

            $this->putPdf($pdfPath, "Customs declaration {$reference}");
            $this->putPdf($signedPath, "Signed FX confirmation {$reference}");

            CustomsDeclaration::query()->create([
                'engine_request_id' => $request->id,
                'declaration_number' => "CD-2026-{$sequence}",
                'issued_by' => $director->id,
                'issued_at' => Carbon::parse('2026-06-20 10:00:00')->addMinutes((int) $sequence - 2014),
                'pdf_path' => $pdfPath,
                'signed_fx_doc_path' => $signedPath,
                'signed_fx_doc_uploaded_at' => Carbon::parse('2026-06-20 12:00:00'),
                'signed_fx_doc_uploaded_by' => $director->id,
                'metadata' => [
                    'seeded' => true,
                    'reference' => $reference,
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                ],
            ]);
        }
    }

    private function seedNotifications($requests, User $entry, User $support, User $director): void
    {
        if (EngineNotification::query()->exists()) {
            return;
        }

        $rows = [
            ['REQUEST_SUBMITTED', 'info', 'تم تقديم طلب جديد', 'طلب جديد بانتظار المراجعة البنكية.', 'ENG-2026-002001', $entry],
            ['SUPPORT_REVIEW', 'warning', 'طلب بانتظار لجنة الدعم', 'يوجد طلب يحتاج إلى مراجعة لجنة الدعم.', 'ENG-2026-002013', $support],
            ['FX_CONFIRMATION', 'info', 'تأكيد مصارفة خارجية', 'طلب جاهز لتأكيد المصارفة الخارجية.', 'ENG-2026-002017', $director],
            ['REQUEST_COMPLETED', 'success', 'اكتمل الطلب', 'تم إغلاق الطلب وإصدار بياناته النهائية.', 'ENG-2026-002019', $entry],
        ];

        foreach ($rows as [$type, $severity, $title, $body, $reference, $recipient]) {
            $request = $requests[$reference];
            $notification = EngineNotification::query()->create([
                'type' => $type,
                'severity' => $severity,
                'title' => $title,
                'body' => $body,
                'entity_type' => EngineRequest::class,
                'entity_id' => $request->id,
                'action_url' => "/admin/requests/{$request->id}",
            ]);

            NotificationRecipient::query()->create([
                'notification_id' => $notification->id,
                'user_id' => $recipient->id,
                'read_at' => $severity === 'success' ? now()->subDay() : null,
                'archived_at' => null,
            ]);
        }
    }

    private function seedEmailDeliveries($requests, User $entry, User $support, User $director): void
    {
        if (DB::table('email_deliveries')->exists()) {
            return;
        }

        $templateVersionId = NotificationTemplateVersion::query()
            ->whereHas('template', fn ($query) => $query->where('notification_type', NotificationType::REQUEST_APPROVED->value))
            ->value('id');

        $rows = [
            [NotificationType::REQUEST_APPROVED->value, 'ENG-2026-002019', $entry, 'sent', 'تمت الموافقة على طلبكم'],
            [NotificationType::REQUEST_RETURNED->value, 'ENG-2026-002013', $support, 'queued', 'طلب يحتاج إلى مراجعة'],
            [NotificationType::REQUEST_REJECTED->value, 'ENG-2026-002020', $director, 'failed', 'تعذر إرسال إشعار الرفض'],
        ];

        foreach ($rows as [$type, $reference, $recipient, $status, $subject]) {
            $request = $requests[$reference];
            $now = now()->subHours($request->id);

            DB::table('email_deliveries')->insert([
                'notification_type' => $type,
                'event_id' => "seeded:{$reference}:{$type}",
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
        if (ReportExport::query()->exists()) {
            return;
        }

        $path = 'reports/seeded-summary.csv';
        Storage::disk('private')->put($path, "metric,value\nengine_requests,20\nclosed_requests,1\n");

        ReportExport::query()->create([
            'requested_by' => $director->id,
            'report_type' => 'summary',
            'filters' => ['seeded' => true],
            'format' => 'csv',
            'status' => 'COMPLETED',
            'file_path' => $path,
            'version' => 1,
        ]);

        ReportExport::query()->create([
            'requested_by' => $director->id,
            'report_type' => 'by-workflow-stage',
            'filters' => ['seeded' => true],
            'format' => 'csv',
            'status' => 'PENDING',
            'file_path' => null,
            'version' => 1,
        ]);
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
