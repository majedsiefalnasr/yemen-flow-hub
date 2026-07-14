<?php

namespace App\Support;

use App\Models\FieldDefinition;

/**
 * Mime/size constraint checks for FILE-type fields, shared by
 * StageFieldRuleValidator (against real EngineRequestDocument rows) and
 * TemporaryUploadEvidenceResolver (against TemporaryUpload rows) so both
 * validate against identical logic rather than two copies drifting apart.
 */
class FileFieldConstraint
{
    public static function mimeAllowed(string $mime, FieldDefinition $field): bool
    {
        if (empty($field->allowed_file_types)) {
            return true;
        }

        $allowedMimes = array_map(
            fn (string $ext) => self::extensionToMime($ext),
            $field->allowed_file_types,
        );

        return in_array($mime, $allowedMimes, true);
    }

    public static function sizeAllowed(int $sizeBytes, FieldDefinition $field): bool
    {
        if ($field->max_file_size === null) {
            return true;
        }

        $sizeKb = (int) ceil($sizeBytes / 1024);

        return $sizeKb <= $field->max_file_size;
    }

    public static function extensionToMime(string $ext): string
    {
        return match ($ext) {
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => $ext,
        };
    }
}
