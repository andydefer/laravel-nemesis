<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create the nemesis_tokens table for token-based authentication.
 *
 * This table stores authentication tokens for multiple models (users, API clients, etc.)
 * with support for expiration, abilities, metadata, CORS restrictions, and soft deletes.
 *
 * @package Kani\Nemesis\Database\Migrations
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nemesis_tokens', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Token storage - stores hashed value only, never raw tokens
            $table->string('token_hash', 64)
                ->unique()
                ->comment('Hashed token value (SHA256) - NEVER store raw tokens');

            // Polymorphic relationship to the owning model (User, ApiClient, etc.)
            // Automatically creates tokenable_type, tokenable_id columns with indexes
            $table->morphs('tokenable');

            // Soft delete support for token revocation with audit trail
            $table->softDeletes();

            // Token identification
            $table->string('name')
                ->nullable()
                ->comment('Descriptive name for the token (e.g., "Mobile App", "API Key")');

            $table->string('source')
                ->nullable()
                ->comment('Source/origin of the token (web, mobile, api, cli, etc.)');

            // Permissions and data
            $table->json('abilities')
                ->nullable()
                ->comment('JSON array of permissions/abilities granted to this token');

            $table->json('metadata')
                ->nullable()
                ->comment('JSON object with additional metadata (user agent, IP, custom data)');

            // Security restrictions
            $table->json('allowed_origins')
                ->nullable()
                ->comment('JSON array of allowed CORS origins (supports wildcards like *.example.com)');

            // Usage tracking
            $table->timestamp('last_used_at')
                ->nullable()
                ->comment('Timestamp of the last time this token was used for authentication');

            // Expiration
            $table->timestamp('expires_at')
                ->nullable()
                ->comment('Token expiration timestamp (null = never expires)');

            // Standard timestamps
            $table->timestamps();

            // Performance indexes for common queries
            $table->index('expires_at');
            $table->index('source');
            $table->index('last_used_at');
            $table->index('token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nemesis_tokens');
    }
};
