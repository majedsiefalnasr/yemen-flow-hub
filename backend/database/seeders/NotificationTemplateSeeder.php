<?php

namespace Database\Seeders;

use App\Enums\NotificationType;
use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    /**
     * Initial admin-editable Markdown templates, transcribed from the existing
     * request-approved/rejected/returned Blade fallback wording.
     *
     * @var array<string, array{subject: string, body: string}>
     */
    private array $templates = [
        NotificationType::REQUEST_APPROVED->value => [
            'subject' => 'تمت الموافقة على طلبكم - The National Committee for Regulating & Financing Imports',
            'body' => <<<'MARKDOWN'
عزيزي {{user_name}}،

يسعدنا إبلاغكم بأنه تمت الموافقة على طلبكم في منصة اللجنة الوطنية لتنظيم وتمويل الواردات.

**رقم الطلب:** {{reference_number}}

**المبلغ:** {{amount}} {{currency}}

**المورد:** {{importer_name}}

[عرض الطلب]({{action_url}})

شكراً لاستخدامكم منصة اللجنة الوطنية لتنظيم وتمويل الواردات.
MARKDOWN,
        ],
        NotificationType::REQUEST_REJECTED->value => [
            'subject' => 'تم رفض طلبكم - The National Committee for Regulating & Financing Imports',
            'body' => <<<'MARKDOWN'
عزيزي {{user_name}}،

نأسف لإبلاغكم بأنه تم رفض طلبكم في منصة اللجنة الوطنية لتنظيم وتمويل الواردات.

**رقم الطلب:** {{reference_number}}

**المبلغ:** {{amount}} {{currency}}

**المورد:** {{importer_name}}

[عرض الطلب]({{action_url}})

شكراً لاستخدامكم منصة اللجنة الوطنية لتنظيم وتمويل الواردات.
MARKDOWN,
        ],
        NotificationType::REQUEST_RETURNED->value => [
            'subject' => 'تم إعادة طلبكم للتعديل - The National Committee for Regulating & Financing Imports',
            'body' => <<<'MARKDOWN'
عزيزي {{user_name}}،

تم إعادة طلبكم للتعديل في منصة اللجنة الوطنية لتنظيم وتمويل الواردات.

**رقم الطلب:** {{reference_number}}

**المبلغ:** {{amount}} {{currency}}

**المورد:** {{importer_name}}

[عرض الطلب وتعديله]({{action_url}})

شكراً لاستخدامكم منصة اللجنة الوطنية لتنظيم وتمويل الواردات.
MARKDOWN,
        ],
    ];

    public function run(): void
    {
        foreach ($this->templates as $type => $templateSource) {
            // firstOrCreate (not updateOrCreate): never force is_active back to true
            // on re-run, which would silently re-activate a template an admin disabled.
            $template = NotificationTemplate::query()->firstOrCreate(
                ['notification_type' => $type],
                ['is_active' => true],
            );

            if ($template->versions()->exists()) {
                continue;
            }

            // Route the write through createActiveVersion() — the single guarded
            // path that holds the "exactly one active version" invariant.
            $template->createActiveVersion(
                $templateSource['subject'],
                $templateSource['body'],
                null,
            );
        }
    }
}
