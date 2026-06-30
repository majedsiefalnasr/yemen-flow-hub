<?php

namespace App\Http\Resources;

use App\Services\Notifications\NotificationRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $definition = app(NotificationRegistry::class)->for($this->notification_type);

        return [
            'type' => $this->notification_type->value,
            'admin_editable' => $definition['admin_editable'],
            'is_active' => $this->is_active,
            'allowed_variables' => $definition['allowed_variables'],
            'active' => $this->activeVersionPayload(),
            'versions' => $this->versionHistoryPayload(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeVersionPayload(): ?array
    {
        if (! $this->relationLoaded('activeVersion') || $this->activeVersion === null) {
            return null;
        }

        $activeVersion = $this->activeVersion;

        return [
            'id' => $activeVersion->id,
            'subject' => $activeVersion->subject,
            'body' => $activeVersion->body,
            'changed_by' => $activeVersion->changed_by,
            'changed_by_name' => $activeVersion->changedBy?->name,
            'changed_at' => $activeVersion->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function versionHistoryPayload(): array
    {
        if (! $this->relationLoaded('versions')) {
            return [];
        }

        return $this->versions
            ->sortByDesc('created_at')
            ->values()
            ->map(fn ($version): array => [
                'id' => $version->id,
                'changed_by' => $version->changed_by,
                'changed_by_name' => $version->changedBy?->name,
                'changed_at' => $version->created_at?->toISOString(),
                'is_active_version' => $version->is_active_version,
            ])
            ->all();
    }
}
