<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('user_preferences')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $preferences = is_string($user->user_preferences)
                        ? json_decode($user->user_preferences, true)
                        : (array) $user->user_preferences;

                    if (!is_array($preferences) || !array_key_exists('theming', $preferences)) {
                        continue;
                    }

                    unset($preferences['theming']);

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'user_preferences' => $preferences === []
                                ? null
                                : json_encode($preferences, JSON_UNESCAPED_UNICODE),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Appearance overrides intentionally cannot be restored. Users inherit
        // system appearance until they make a new explicit change.
    }
};
