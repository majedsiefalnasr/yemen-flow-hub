<?php

namespace App\Enums;

/**
 * boring-avatars supported variants. Kept in sync with the frontend constant
 * `AVATAR_VARIANTS` (see app/composables/useUserAvatar.ts).
 */
enum AvatarVariant: string
{
    case MARBLE = 'marble';
    case BEAM = 'beam';
    case PIXEL = 'pixel';
    case SUNSET = 'sunset';
    case RING = 'ring';
    case BAUHAUS = 'bauhaus';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
