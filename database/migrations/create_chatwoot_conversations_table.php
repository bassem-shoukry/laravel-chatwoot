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
        Schema::create('chatwoot_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('inbox_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->string('status')->default('open')->index();
            $table->unsignedBigInteger('assignee_id')->nullable()->index();
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->json('labels')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['account_id', 'conversation_id'], 'unique_account_conversation');

            // Composite indexes for common queries
            $table->index(['account_id', 'status'], 'account_status_idx');
            $table->index(['account_id', 'inbox_id'], 'account_inbox_idx');
            $table->index(['contact_id', 'status'], 'contact_status_idx');
        });
    }

    /**
     * @throws RuntimeException
     */
    public function down(): void
    {
        Schema::dropIfExists('chatwoot_conversations');
    }
};
