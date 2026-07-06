<?php

namespace App\Exceptions;

use App\Enums\FieldSemanticTag;
use RuntimeException;

class SemanticMappingUnresolvedException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly ?FieldSemanticTag $tag,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forTag(string $errorCode, FieldSemanticTag $tag): self
    {
        return new self(
            $errorCode,
            $tag,
            "Required semantic mapping {$tag->value} could not be resolved.",
        );
    }
}
