<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chatwoot_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('contact_id')->index();
            $table->string('name')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('phone_number')->nullable()->index();
            $table->string('avatar_url')->nullable();
            $table->string('identifier')->nullable()->index();
            $table->json('custom_attributes')->nullable();
            $table->json('additional_attributes')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['account_id', 'contact_id'], 'unique_account_contact');
            
            // Additional indexes for common lookups
            $table->index(['account_id', 'email'], 'account_email_idx');
            $table->index(['account_id', 'phone_number'], 'account_phone_idx');
            $table->index(['account_id', 'identifier'], 'account_identifier_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chatwoot_contacts');
    }
};