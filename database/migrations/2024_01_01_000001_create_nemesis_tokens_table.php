<?php
// database/migrations/2024_01_01_000001_create_nemesis_tokens_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nemesis_tokens', function (Blueprint $table) {
            $table->id();

            // Token storage
            $table->string('token', 64)->unique()->comment('Hashed token value');

            // Polymorphic relationship (auto-crée tokenable_type, tokenable_id ET leurs indexes)
            $table->morphs('tokenable');

            // Token metadata
            $table->string('name')->nullable()->comment('Descriptive name for the token');
            $table->string('source')->nullable()->comment('Source/origin of the token (web, mobile, api, etc.)');

            // Permissions
            $table->json('abilities')->nullable()->comment('Array of permissions/abilities');
            $table->json('metadata')->nullable()->comment('Additional metadata');

            // Security and restrictions
            $table->json('allowed_origins')->nullable();

            // Timestamps
            $table->timestamp('last_used_at')->nullable()->comment('Last time token was used');
            $table->timestamp('expires_at')->nullable()->comment('Token expiration timestamp');
            $table->timestamps();

            // Additional indexes
            $table->index('expires_at');
            $table->index('source');
            $table->index('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nemesis_tokens');
    }
};
