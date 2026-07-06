<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a workflow design element cannot be mutated because it is bound to
 * downstream config or runtime data (e.g. a stage referenced by a transition or a
 * request). Callers translate it into a 422 response carrying `errorCode`.
 */
class WorkflowDesignProtectionException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function versionInUse(): self
    {
        return new self('WORKFLOW_VERSION_IN_USE', 'لا يمكن حذف نسخة مرتبطة بطلبات.');
    }

    public static function definitionInUse(): self
    {
        return new self('WORKFLOW_DEFINITION_IN_USE', 'لا يمكن حذف مسار عمل مرتبط بطلبات.');
    }

    public static function publishedNotDeletable(): self
    {
        return new self('PUBLISHED_NOT_DELETABLE', 'Published workflow versions cannot be deleted; archive instead.');
    }

    public static function definitionHasPublishedVersions(): self
    {
        return new self('DEFINITION_HAS_PUBLISHED_VERSIONS', 'Workflow definitions with published or archived versions cannot be deleted.');
    }
}
