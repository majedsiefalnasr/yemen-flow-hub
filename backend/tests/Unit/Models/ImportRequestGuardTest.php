<?php

namespace Tests\Unit\Models;

use App\Enums\RequestStatus;
use App\Exceptions\DirectStatusMutationException;
use App\Models\ImportRequest;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

class ImportRequestGuardTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application();
        Application::setInstance($this->app);
    }

    protected function tearDown(): void
    {
        Application::setInstance(null);
        parent::tearDown();
    }

    public function test_direct_status_mutation_throws_exception(): void
    {
        $this->expectException(DirectStatusMutationException::class);

        $model = new ImportRequest();
        $model->setAttribute('status', RequestStatus::SUBMITTED);
    }

    public function test_status_mutation_allowed_with_ioc_flag(): void
    {
        $this->app->instance('workflow.transition.active', true);

        $model = new ImportRequest();
        $model->setAttribute('status', RequestStatus::SUBMITTED);

        // Verify both raw value and cast type
        $this->assertEquals(RequestStatus::SUBMITTED->value, $model->getAttributes()['status']);
        $this->assertInstanceOf(RequestStatus::class, $model->status);
        $this->assertSame(RequestStatus::SUBMITTED, $model->status);
    }

    public function test_guard_re_engages_after_ioc_flag_removed(): void
    {
        $this->app->instance('workflow.transition.active', true);
        $model = new ImportRequest();
        $model->setAttribute('status', RequestStatus::SUBMITTED);
        $this->app->offsetUnset('workflow.transition.active');

        // Guard must be active again — further direct mutation must throw
        $this->expectException(DirectStatusMutationException::class);
        $model->setAttribute('status', RequestStatus::DRAFT);
    }

    public function test_non_status_attributes_are_not_guarded(): void
    {
        $model = new ImportRequest();
        $model->setAttribute('supplier_name', 'Test Supplier');

        $this->assertEquals('Test Supplier', $model->getAttributes()['supplier_name']);
    }
}
