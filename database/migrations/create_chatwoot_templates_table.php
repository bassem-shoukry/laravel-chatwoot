<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @throws RuntimeException
     */
    public function up(): void
    {
        Schema::create('chatwoot_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key')->index(); // 'welcome', 'follow_up', etc.
            $table->string('account_key');
            $table->string('inbox_key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('content'); // template content with variables
            $table->json('variables')->nullable(); // required variables
            $table->json('channel_restrictions')->nullable(); // allowed channels
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['template_key', 'account_key', 'inbox_key']);
            $table->foreign('account_key')->references('account_key')->on('chatwoot_accounts')->onDelete('cascade');
            $table->index(['account_key', 'inbox_key', 'is_active']);
        });
    }

    /**
     * @throws RuntimeException
     */
    public function down(): void
    {
        Schema::dropIfExists('chatwoot_templates');
    }
};
