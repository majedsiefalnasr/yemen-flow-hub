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
        $resolver = app(SettingResolver::class);

        $this->assertSame(15, $resolver->get('support_claim_ttl', 15));
    }

    public function test_returns_db_value_when_row_exists(): void
    {
        SystemSetting::create([
            'key' => 'support_claim_ttl',
            'value' => 30,
        ]);

        $resolver = app(SettingResolver::class);

        $this->assertSame(30, $resolver->get('support_claim_ttl', 15));
    }
}
