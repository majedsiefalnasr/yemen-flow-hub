<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('request_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->index('request_id', 'rv_request_id_idx');
            $table->unsignedBigInteger('user_id');
            $table->index('user_id', 'rv_user_id_idx');
            $table->enum('vote', ['APPROVE', 'REJECT', 'ABSTAIN', 'AUTO_ABSTAIN_TIMEOUT']);
            $table->text('justification')->nullable();
            $table->boolean('is_director_override')->default(false);
            $table->timestamps();
            $table->unique(['request_id', 'user_id']);

            $table->foreign('request_id', 'rv_request_id_fk')
                ->references('id')
                ->on('import_requests')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'rv_user_id_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_votes');
    }
};
