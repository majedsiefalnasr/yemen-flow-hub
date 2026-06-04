<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_stage_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->index('request_id', 'rsh_request_id_idx');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('from_owner_role')->nullable();
            $table->string('to_owner_role')->nullable();
            $table->unsignedBigInteger('actor_id');
            $table->index('actor_id', 'rsh_actor_id_idx');
            $table->string('actor_role');
            $table->string('action');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('request_id', 'rsh_request_id_fk')
                ->references('id')
                ->on('import_requests')
                ->cascadeOnDelete();

            $table->foreign('actor_id', 'rsh_actor_id_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_stage_history');
    }
};
