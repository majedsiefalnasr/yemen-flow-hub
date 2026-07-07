<?php

namespace App\Services\Settings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LogoStorageService
{
    private const DISK = 'public';

    private const DIRECTORY = 'logos';

    public function store(UploadedFile $file): string
    {
        $this->validateType($file);

        return $file->store(self::DIRECTORY, self::DISK);
    }

    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        // Legacy unmigrated rows may still store inline data URLs.
        if (str_starts_with($path, 'data:')) {
            return $path;
        }

        // Existing default static emblem (no upload yet) — serve as-is.
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk(self::DISK)->url($path);
    }

    private function validateType(UploadedFile $file): void
    {
        $allowed = ['image/png', 'image/svg+xml', 'image/jpeg', 'image/webp'];

        if (! in_array($file->getMimeType(), $allowed, true)) {
            throw new \InvalidArgumentException('Logo must be PNG, SVG, JPEG, or WebP.');
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('Logo must be under 2MB.');
        }
    }
}
