<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nemesis_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->json('allowed_origins')->nullable();
            $table->unsignedInteger('max_requests')->default(1000);
            $table->unsignedInteger('requests_count')->default(0);
            $table->timestamp('last_request_at')->nullable();
            $table->string('name')->nullable()->comment('Descriptive name for the token');
            $table->text('block_reason')->nullable()->comment('Reason for blocking the token');
            $table->text('unblock_reason')->nullable()->comment('Reason for unblocking the token');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nemesis_tokens');
    }
};