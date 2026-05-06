<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatwoot_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('account_name')->index();
            $table->unsignedBigInteger('chatwoot_account_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('identifier')->nullable();
            $table->string('avatar_url')->nullable();
            $table->json('additional_attributes')->nullable();
            $table->json('custom_attributes')->nullable();
            $table->timestamps();

            $table->unique(['account_name', 'contact_id']);
            $table->index(['account_name', 'phone_number']);
            $table->index(['account_name', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatwoot_contacts');
    }
};
