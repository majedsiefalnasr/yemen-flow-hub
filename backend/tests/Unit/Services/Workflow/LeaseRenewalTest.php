<?php

namespace Tests\Unit\Services\Workflow;

use App\Enums\OrganizationClassification;
use App\Enums\WorkflowVersionState;
use App\Models\IdempotencyKey;
use App\Models\Organization;
use App\Models\TemporaryUpload;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\IdempotencyCoordinator;
use App\Services\Workflow\TemporaryUploadReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reproduces and guards against a same-second false-negative in lease
 * renewal: locked_until (idempotency_keys) and reservation_expires_at
 * (temporary_uploads) are plain `timestamp` columns (second precision, see
 * their migrations), while PHP's now() carries microsecond precision. A
 * renewal computed and applied within the same wall-clock second as a prior
 * renewal can write the identical (second-truncated) value the row already
 * has. MySQL's UPDATE then reports 0 affected rows for that column — not
 * because the WHERE predicate failed to match, but because the SET value
 * didn't change anything — and both renewLease()/renew() previously read
 * that as "not owned" and threw SUBMISSION_LEASE_LOST.
 */
class LeaseRenewalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WorkflowVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name' => 'Lease Test Org',
            'code' => 'lease_test_org',
            'classification' => OrganizationClassification::OTHER->value,
        ]);
        $this->user = User::create([
            'name' => 'Lease Tester',
            'email' => 'lease-tester@example.test',
            'password' => bcrypt('password'),
            'organization_id' => $org->id,
            'is_active' => true,
        ]);

        $definition = WorkflowDefinition::create([
            'code' => 'LEASE_TEST_WF',
            'name' => 'Lease Test Workflow',
            'is_active' => true,
        ]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
            'version' => 1,
        ]);
    }

    private function makeIdempotencyKey(string $claimToken, Carbon $lockedUntil): IdempotencyKey
    {
        return IdempotencyKey::query()->create([
            'key' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'organization_id' => $this->user->organization_id,
            'operation' => IdempotencyCoordinator::OPERATION_ENGINE_REQUEST_CREATE,
            'request_fingerprint' => 'fp-'.Str::random(8),
            'state' => 'PROCESSING',
            'claim_token' => $claimToken,
            'locked_until' => $lockedUntil,
        ]);
    }

    private function makeTemporaryUpload(int $keyId, string $claimToken, Carbon $reservationExpiresAt): TemporaryUpload
    {
        return TemporaryUpload::query()->create([
            'token' => Str::random(40),
            'upload_session_token' => Str::random(40),
            'user_id' => $this->user->id,
            'organization_id' => $this->user->organization_id,
            'workflow_version_id' => $this->version->id,
            'original_name' => 'file.pdf',
            'path' => 'private-tmp/file.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'checksum' => hash('sha256', 'x'),
            'scan_status' => 'clean',
            'expires_at' => now()->addHour(),
            'reserved_by_idempotency_key_id' => $keyId,
            'reservation_claim_token' => $claimToken,
            'reservation_expires_at' => $reservationExpiresAt,
        ]);
    }

    public function test_idempotency_renewal_immediately_after_claim_in_the_same_second_is_not_a_false_negative(): void
    {
        // Freeze at a point mid-second, then advance only in microseconds —
        // the claim and the renewal fall in the same wall-clock second, so a
        // naive affected-row check on locked_until can misfire.
        $frozen = Carbon::create(2026, 1, 1, 12, 0, 0)->addMicroseconds(500000);
        Carbon::setTestNow($frozen);

        $claimToken = (string) Str::uuid();
        $key = $this->makeIdempotencyKey($claimToken, now()->addSeconds(120));

        Carbon::setTestNow($frozen->copy()->addMicroseconds(1000));

        $coordinator = app(IdempotencyCoordinator::class);
        $result = $coordinator->renewLease($key->id, $claimToken, 120);

        $this->assertTrue(
            $result,
            'renewLease() must return true for a renewal that matched the row '.
            'by claim_token+state, even if the second-truncated locked_until '.
            'value happens to write identically and MySQL reports 0 affected rows.',
        );
    }

    public function test_reservation_renewal_immediately_after_reserve_in_the_same_second_is_not_a_false_negative(): void
    {
        $frozen = Carbon::create(2026, 1, 1, 12, 0, 0)->addMicroseconds(500000);
        Carbon::setTestNow($frozen);

        $claimToken = (string) Str::uuid();
        $key = $this->makeIdempotencyKey($claimToken, now()->addSeconds(120));
        $upload = $this->makeTemporaryUpload($key->id, $claimToken, now()->addSeconds(120));

        Carbon::setTestNow($frozen->copy()->addMicroseconds(1000));

        $reservations = app(TemporaryUploadReservationService::class);
        $result = $reservations->renew([$upload->id], $key->id, $claimToken, 120);

        $this->assertTrue(
            $result,
            'renew() must return true for a reservation that matched by '.
            'reserved_by_idempotency_key_id+reservation_claim_token, even if '.
            'the second-truncated reservation_expires_at writes identically.',
        );
    }

    public function test_idempotency_renewal_with_the_wrong_claim_token_still_returns_false(): void
    {
        $frozen = Carbon::create(2026, 1, 1, 12, 0, 0)->addMicroseconds(500000);
        Carbon::setTestNow($frozen);

        $realToken = (string) Str::uuid();
        $key = $this->makeIdempotencyKey($realToken, now()->addSeconds(120));

        Carbon::setTestNow($frozen->copy()->addMicroseconds(1000));

        $coordinator = app(IdempotencyCoordinator::class);
        $result = $coordinator->renewLease($key->id, (string) Str::uuid(), 120);

        $this->assertFalse(
            $result,
            'A renewal under a claim_token that does not own the row must '.
            'still fail — the same-second fix must not weaken this guard.',
        );
    }

    public function test_reservation_renewal_with_the_wrong_claim_token_still_returns_false(): void
    {
        $frozen = Carbon::create(2026, 1, 1, 12, 0, 0)->addMicroseconds(500000);
        Carbon::setTestNow($frozen);

        $realToken = (string) Str::uuid();
        $key = $this->makeIdempotencyKey($realToken, now()->addSeconds(120));
        $upload = $this->makeTemporaryUpload($key->id, $realToken, now()->addSeconds(120));

        Carbon::setTestNow($frozen->copy()->addMicroseconds(1000));

        $reservations = app(TemporaryUploadReservationService::class);
        $result = $reservations->renew([$upload->id], $key->id, (string) Str::uuid(), 120);

        $this->assertFalse($result, 'A reservation renewal under the wrong claim_token must still fail.');
    }

    public function test_idempotency_renewal_after_the_lease_was_actually_reclaimed_still_returns_false(): void
    {
        $frozen = Carbon::create(2026, 1, 1, 12, 0, 0)->addMicroseconds(500000);
        Carbon::setTestNow($frozen);

        $originalToken = (string) Str::uuid();
        $key = $this->makeIdempotencyKey($originalToken, now()->subSeconds(1));

        // Simulate a second attempt reclaiming the expired lease under a new
        // claim_token — the original attempt's token is now stale.
        $key->update(['claim_token' => (string) Str::uuid(), 'locked_until' => now()->addSeconds(120)]);

        Carbon::setTestNow($frozen->copy()->addMicroseconds(1000));

        $coordinator = app(IdempotencyCoordinator::class);
        $result = $coordinator->renewLease($key->id, $originalToken, 120);

        $this->assertFalse(
            $result,
            'Once a lease has been genuinely reclaimed under a new '.
            'claim_token, the superseded attempt\'s renewal must still fail '.
            '— this is real ownership loss, not a same-second false negative.',
        );
    }

    public function test_reservation_renewal_after_the_reservation_was_actually_reclaimed_still_returns_false(): void
    {
        $frozen = Carbon::create(2026, 1, 1, 12, 0, 0)->addMicroseconds(500000);
        Carbon::setTestNow($frozen);

        $originalToken = (string) Str::uuid();
        $key = $this->makeIdempotencyKey($originalToken, now()->addSeconds(120));
        $upload = $this->makeTemporaryUpload($key->id, $originalToken, now()->subSeconds(1));

        // Reclaimed by a different attempt under a new claim_token.
        $upload->update(['reservation_claim_token' => (string) Str::uuid(), 'reservation_expires_at' => now()->addSeconds(120)]);

        Carbon::setTestNow($frozen->copy()->addMicroseconds(1000));

        $reservations = app(TemporaryUploadReservationService::class);
        $result = $reservations->renew([$upload->id], $key->id, $originalToken, 120);

        $this->assertFalse(
            $result,
            'Once a reservation has been genuinely reclaimed under a new '.
            'claim_token, the superseded attempt\'s renewal must still fail.',
        );
    }
}
