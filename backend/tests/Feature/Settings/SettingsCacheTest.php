<?php

namespace Tests\Feature\Settings;

use App\Models\SystemSetting;
use App\Services\Settings\AdminSettingsService;
use App\Services\Settings\SettingResolver;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingsCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_invalidated_on_update(): void
    {
        SystemSetting::updateOrCreate(['key' => 'support_claim_ttl'], ['value' => 15]);

        $resolver = app(SettingResolver::class);
        $this->assertSame(15, $resolver->get('support_claim_ttl', 15));

        SystemSetting::where('key', 'support_claim_ttl')->update(['value' => 30]);
        Cache::put('setting:support_claim_ttl', 15, now()->addHour());

        $actor = auth()->user() ?? UserFactory::new()->create();
        app(AdminSettingsService::class)->updateSetting('support_claim_ttl', 30, $actor);

        $this->assertSame(30, $resolver->get('support_claim_ttl', 15));
    }
}
