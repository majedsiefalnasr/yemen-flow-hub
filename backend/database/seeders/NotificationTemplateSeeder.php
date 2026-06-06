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
            'subject' => 'تمت الموافقة على طلبكم - Yemen Flow Hub',
            'body' => <<<'MARKDOWN'
عزيزي {{user_name}}،

يسعدنا إبلاغكم بأنه تمت الموافقة على طلبكم في منصة Yemen Flow Hub.

**رقم الطلب:** {{reference_number}}

**المبلغ:** {{amount}} {{currency}}

**المورد:** {{importer_name}}

[عرض الطلب]({{action_url}})

شكراً لاستخدامكم منصة Yemen Flow Hub.
MARKDOWN,
        ],
        NotificationType::REQUEST_REJECTED->value => [
            'subject' => 'تم رفض طلبكم - Yemen Flow Hub',
            'body' => <<<'MARKDOWN'
عزيزي {{user_name}}،

نأسف لإبلاغكم بأنه تم رفض طلبكم في منصة Yemen Flow Hub.

**رقم الطلب:** {{reference_number}}

**المبلغ:** {{amount}} {{currency}}

**المورد:** {{importer_name}}

[عرض الطلب]({{action_url}})

شكراً لاستخدامكم منصة Yemen Flow Hub.
MARKDOWN,
        ],
        NotificationType::REQUEST_RETURNED->value => [
            'subject' => 'تم إعادة طلبكم للتعديل - Yemen Flow Hub',
            'body' => <<<'MARKDOWN'
عزيزي {{user_name}}،

تم إعادة طلبكم للتعديل في منصة Yemen Flow Hub.

**رقم الطلب:** {{reference_number}}

**المبلغ:** {{amount}} {{currency}}

**المورد:** {{importer_name}}

[عرض الطلب وتعديله]({{action_url}})

شكراً لاستخدامكم منصة Yemen Flow Hub.
MARKDOWN,
        ],
    ];

    public function run(): void
    {
        foreach ($this->templates as $type => $templateSource) {
            $template = NotificationTemplate::query()->updateOrCreate(
                ['notification_type' => $type],
                ['is_active' => true],
            );

            if ($template->versions()->exists()) {
                continue;
            }

            $template->versions()->create([
                'subject' => $templateSource['subject'],
                'body' => $templateSource['body'],
                'changed_by' => null,
                'is_active_version' => true,
            ]);
        }
    }
}
