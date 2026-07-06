<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditAction;
use App\Enums\NotificationType;
use App\Http\Controllers\Api\Controller;
use App\Http\Resources\NotificationTemplateResource;
use App\Models\NotificationTemplate;
use App\Services\Audit\AuditService;
use App\Services\Notifications\NotificationRegistry;
use App\Services\Notifications\TemplateRenderer;
use App\Services\Notifications\TemplateValidator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class NotificationTemplateController extends Controller
{
    public function __construct(
        private readonly NotificationRegistry $registry,
        private readonly TemplateValidator $validator,
        private readonly TemplateRenderer $renderer,
        private readonly AuditService $auditService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('cbyAdmin', $request->user());

        $templates = NotificationTemplate::query()
            ->whereIn('notification_type', $this->editableTypeValues())
            ->with(['activeVersion.changedBy', 'versions.changedBy'])
            ->orderBy('notification_type')
            ->get();

        return ApiResponse::success(
            NotificationTemplateResource::collection($templates),
            'Notification templates retrieved.'
        );
    }

    public function show(Request $request, string $type): JsonResponse
    {
        Gate::authorize('cbyAdmin', $request->user());

        return ApiResponse::success(
            new NotificationTemplateResource($this->editableTemplate($type)),
            'Notification template retrieved.'
        );
    }

    public function update(Request $request, string $type): JsonResponse
    {
        Gate::authorize('cbyAdmin', $request->user());

        $template = $this->editableTemplate($type);
        $sanitized = $this->validatedTemplateContent($request, $template->notification_type);

        // Version write + audit are atomic: a failed audit insert must not leave an
        // un-audited template change committed (and vice-versa).
        DB::transaction(function () use ($template, $sanitized, $request): void {
            $template->createActiveVersion(
                $sanitized['subject'],
                $sanitized['body'],
                $request->user()->id,
            );

            $this->auditService->log(
                AuditAction::EMAIL_TEMPLATE_UPDATED,
                $request->user(),
                $template,
                [
                    'template_type' => $template->notification_type->value,
                    'changed_by' => $request->user()->id,
                    'timestamp' => now()->toISOString(),
                ],
            );
        });

        $template->load(['activeVersion.changedBy', 'versions.changedBy']);

        return ApiResponse::success(
            new NotificationTemplateResource($template),
            'Notification template updated.'
        );
    }

    public function preview(Request $request, string $type)
    {
        Gate::authorize('cbyAdmin', $request->user());

        // Require the template row to exist (firstOrFail), matching show/update — so
        // an editable type with no stored template 404s consistently across methods
        // instead of previewing fine but failing to open/save.
        $template = $this->editableTemplate($type);
        $sanitized = $this->validatedTemplateContent($request, $template->notification_type);

        return ApiResponse::success([
            'source' => [
                'subject' => $sanitized['subject'],
                'body' => $sanitized['body'],
            ],
            'rendered' => $this->renderer->renderSource(
                $template->notification_type,
                $sanitized['subject'],
                $sanitized['body'],
                $this->sampleVariables(),
            ),
        ], 'Template preview rendered.');
    }

    /**
     * @return array<int, string>
     */
    private function editableTypeValues(): array
    {
        return collect($this->registry->all())
            ->filter(fn (array $definition): bool => $definition['admin_editable'] === true)
            ->keys()
            ->all();
    }

    private function editableType(string $type): NotificationType
    {
        $notificationType = NotificationType::tryFrom($type);

        if ($notificationType === null || ! $this->registry->for($notificationType)['admin_editable']) {
            abort(404);
        }

        return $notificationType;
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function validatedTemplateContent(Request $request, NotificationType $type): array
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:65535'],
        ]);

        return $this->validator->validateForSave($type, $validated['subject'], $validated['body']);
    }

    private function editableTemplate(string $type): NotificationTemplate
    {
        return NotificationTemplate::query()
            ->where('notification_type', $this->editableType($type)->value)
            ->with(['activeVersion.changedBy', 'versions.changedBy'])
            ->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    private function sampleVariables(): array
    {
        return [
            'reference_number' => 'YFH-2026-000123',
            'bank_name' => 'Yemen International Bank',
            'importer_name' => 'Yemen International Bank',
            'amount' => '1,000,000',
            'currency' => 'USD',
            'status' => 'معتمد',
            'action_url' => 'https://app.yemenflowhub.ye/workflows/instances/123',
            'user_name' => 'مدير النظام',
        ];
    }
}
