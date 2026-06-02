<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // boring-avatars variant. One of: marble, beam, pixel, sunset, ring, bauhaus.
            // Stored as a free string (not enum) so the catalogue can grow without a
            // schema migration; the canonical list lives in
            // App\Enums\AvatarVariant and is enforced at the validation layer.
            $table->string('avatar_variant', 20)->default('beam')->after('user_preferences');

            // Optional hex colour selected by the user via the colour picker.
            // When NULL, the frontend falls back to the shared brand palette so the
            // avatar still renders deterministically from the user's name.
            $table->string('avatar_color', 9)->nullable()->after('avatar_variant');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_variant', 'avatar_color']);
        });
    }
};
