<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatwoot_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('account_key');
            $table->string('event_type'); // 'conversation_created', 'message_created', etc.
            $table->json('payload');
            $table->string('signature')->nullable();
            $table->boolean('verified')->default(false);
            $table->boolean('processed')->default(false);
            $table->text('processing_error')->nullable();
            $table->timestamps();
            
            $table->index(['account_key', 'event_type', 'processed']);
            $table->index(['created_at', 'processed']);
            $table->index(['verified', 'processed']);
            $table->foreign('account_key')->references('account_key')->on('chatwoot_accounts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatwoot_webhook_events');
    }
};