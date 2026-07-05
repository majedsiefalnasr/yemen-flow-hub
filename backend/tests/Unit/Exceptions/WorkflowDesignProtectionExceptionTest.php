<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\WorkflowDesignProtectionException;
use PHPUnit\Framework\TestCase;

class WorkflowDesignProtectionExceptionTest extends TestCase
{
    public function test_version_in_use_factory_sets_code_and_message(): void
    {
        $exception = WorkflowDesignProtectionException::versionInUse();

        $this->assertSame('WORKFLOW_VERSION_IN_USE', $exception->errorCode);
        $this->assertSame('لا يمكن حذف نسخة مرتبطة بطلبات.', $exception->getMessage());
    }

    public function test_definition_in_use_factory_sets_code_and_message(): void
    {
        $exception = WorkflowDesignProtectionException::definitionInUse();

        $this->assertSame('WORKFLOW_DEFINITION_IN_USE', $exception->errorCode);
        $this->assertSame('لا يمكن حذف مسار عمل مرتبط بطلبات.', $exception->getMessage());
    }
}
