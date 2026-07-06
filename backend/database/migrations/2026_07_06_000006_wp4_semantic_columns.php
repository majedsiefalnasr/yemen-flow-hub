<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_definitions', function (Blueprint $table): void {
            $table->string('semantic_tag', 50)->nullable()->after('key');
            $table->index(['workflow_version_id', 'semantic_tag'], 'field_definitions_version_semantic_tag_idx');
        });

        Schema::table('workflow_stages', function (Blueprint $table): void {
            $table->string('semantic_role', 50)->nullable()->after('code');
            $table->json('attached_effects')->nullable()->after('semantic_role');
            $table->index(['workflow_version_id', 'semantic_role'], 'workflow_stages_version_semantic_role_idx');
        });
    }

    public function down(): void
    {
        Schema::table('field_definitions', function (Blueprint $table): void {
            $table->dropIndex('field_definitions_version_semantic_tag_idx');
            $table->dropColumn('semantic_tag');
        });

        Schema::table('workflow_stages', function (Blueprint $table): void {
            $table->dropIndex('workflow_stages_version_semantic_role_idx');
            $table->dropColumn(['semantic_role', 'attached_effects']);
        });
    }
};
