<?php

namespace Tests\Feature\Documents;

use App\Models\SystemSetting;
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
        SystemSetting::create(['key' => 'pdf_upload_size_limit', 'value' => 25]);

        $this->assertSame(25 * 1024, app(UploadSizeLimit::class)->maxKilobytes());
    }
}
