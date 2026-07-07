<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $branding = SystemSetting::getValueByKey('settings.branding', []);

        $dataUrl = $branding['brandLogoDataUrl'] ?? null;
        if ($dataUrl === null || ! str_starts_with($dataUrl, 'data:')) {
            return; // No base64 to migrate.
        }

        // Decode and store as a file.
        if (preg_match('/^data:(image\/[a-z+.-]+);base64,(.+)$/', $dataUrl, $m)) {
            $ext = str_contains($m[1], 'svg') ? 'svg' : (str_contains($m[1], 'png') ? 'png' : 'jpg');
            $name = 'logos/migrated-'.uniqid().'.'.$ext;
            Storage::disk('public')->put($name, base64_decode($m[2]));
            $branding['brandLogoPath'] = $name;
            unset($branding['brandLogoDataUrl']);
            SystemSetting::where('key', 'settings.branding')->update(['value' => $branding]);
        }
    }

    public function down(): void {}
};
