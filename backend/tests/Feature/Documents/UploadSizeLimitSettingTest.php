<?php

namespace Tests\Feature\Documents;

use App\Models\SystemSetting;
use App\Services\Settings\SettingResolver;
use App\Support\UploadSizeLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UploadSizeLimitSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_is_10mb(): void
    {
        $this->assertSame(10 * 1024, app(UploadSizeLimit::class)->maxKilobytes());
    }

    public function test_reads_db_setting(): void
    {
        SystemSetting::findByKey('pdf_upload_size_limit')?->update(['value' => 25]);
        app(SettingResolver::class)->forget('pdf_upload_size_limit');

        $this->assertSame(25 * 1024, app(UploadSizeLimit::class)->maxKilobytes());
    }
}
