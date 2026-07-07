<?php

namespace App\Support;

use App\Services\Settings\SettingResolver;

class UploadSizeLimit
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * Maximum upload size in kilobytes (for Laravel `max:` validation rule).
     * DB setting is in MB; convert to KB.
     */
    public function maxKilobytes(): int
    {
        $mb = (int) $this->settings->get('pdf_upload_size_limit', 10);

        return $mb * 1024;
    }
}
