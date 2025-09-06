<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatwoot_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_key')->unique(); // 'primary', 'secondary', etc.
            $table->string('name');
            $table->string('url');
            $table->text('token')->nullable(); // encrypted
            $table->json('config')->nullable(); // inbox configs, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            
            $table->index(['account_key', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatwoot_accounts');
    }
};
