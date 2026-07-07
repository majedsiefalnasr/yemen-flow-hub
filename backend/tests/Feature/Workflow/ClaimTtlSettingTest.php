<?php

namespace Tests\Feature\Workflow;

use App\Models\SystemSetting;
use App\Services\Workflow\EngineClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaimTtlSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ttl_reads_db_setting(): void
    {
        SystemSetting::create(['key' => 'support_claim_ttl', 'value' => 30]);

        $service = app(EngineClaimService::class);

        $reflection = new \ReflectionMethod($service, 'ttlMinutes');
        $reflection->setAccessible(true);

        $this->assertSame(30, $reflection->invoke($service));
    }

    public function test_ttl_falls_back_to_default_without_row(): void
    {
        $service = app(EngineClaimService::class);

        $reflection = new \ReflectionMethod($service, 'ttlMinutes');
        $reflection->setAccessible(true);

        $this->assertSame(15, $reflection->invoke($service));
    }
}
