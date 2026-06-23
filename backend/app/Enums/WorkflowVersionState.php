<?php

namespace App\Enums;

enum WorkflowVersionState: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case ARCHIVED = 'ARCHIVED';

    /**
     * Only DRAFT versions are editable. PUBLISHED and ARCHIVED are frozen.
     */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }
}
