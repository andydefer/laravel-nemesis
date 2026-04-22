<?php
// tests/database/migrations/2024_01_01_000001_create_test_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('test_api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('api_key')->unique();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('test_api_clients');
        Schema::dropIfExists('test_users');
    }
};
