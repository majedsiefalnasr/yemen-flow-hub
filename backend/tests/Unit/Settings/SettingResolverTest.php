<?php

namespace Tests\Unit\Settings;

use App\Models\SystemSetting;
use App\Services\Settings\SettingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_default_when_no_row_exists(): void
    {
        SystemSetting::where('key', 'support_claim_ttl')->delete();
        app(SettingResolver::class)->forget('support_claim_ttl');

        $resolver = app(SettingResolver::class);

        $this->assertSame(15, $resolver->get('support_claim_ttl', 15));
    }

    public function test_returns_db_value_when_row_exists(): void
    {
        SystemSetting::findByKey('support_claim_ttl')?->update(['value' => 30]);
        app(SettingResolver::class)->forget('support_claim_ttl');

        $resolver = app(SettingResolver::class);

        $this->assertSame(30, $resolver->get('support_claim_ttl', 15));
    }
}
