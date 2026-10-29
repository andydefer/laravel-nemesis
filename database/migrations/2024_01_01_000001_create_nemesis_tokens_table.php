<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nemesis_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('token_hash', 64)
                ->unique()
                ->comment('Hashed token value (SHA256) - NEVER store raw tokens');

            $table->morphs('tokenable');

            $table->softDeletes();

            $table->string('name')
                ->nullable()
                ->comment('Descriptive name for the token (e.g., "Mobile App", "API Key")');

            $table->string('source')
                ->nullable()
                ->comment('Source/origin of the token (web, mobile, api, cli, etc.)');

            $table->json('abilities')
                ->nullable()
                ->comment('JSON array of permissions/abilities granted to this token');

            $table->json('metadata')
                ->nullable()
                ->comment('JSON object with additional metadata (user agent, IP, custom data)');

            $table->json('allowed_origins')
                ->nullable()
                ->comment('JSON array of allowed CORS origins (supports wildcards like *.example.com)');

            $table->timestamp('last_used_at')
                ->nullable()
                ->comment('Timestamp of the last time this token was used for authentication');

            $table->timestamp('expires_at')
                ->nullable()
                ->comment('Token expiration timestamp (null = never expires)');

            $table->timestamps();

            $table->index('expires_at');
            $table->index('source');
            $table->index('last_used_at');
            $table->index('token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nemesis_tokens');
    }
};
