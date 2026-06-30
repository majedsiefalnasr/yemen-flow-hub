<?php

namespace Tests\Feature\Engine;

use App\Models\EngineRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineClaimSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_engine_request_has_claim_columns_and_helpers(): void
    {
        $this->assertTrue(\Schema::hasColumn('engine_requests', 'claimed_by'));
        $this->assertTrue(\Schema::hasColumn('engine_requests', 'claimed_at'));
        $this->assertTrue(\Schema::hasColumn('engine_requests', 'claim_expires_at'));
        $this->assertTrue(\Schema::hasColumn('workflow_stages', 'requires_claim'));

        $request = new EngineRequest(['claim_expires_at' => now()->addMinutes(5), 'claimed_by' => 1]);
        $this->assertTrue($request->isClaimed());
        $this->assertFalse($request->claimIsExpired());
    }
}
