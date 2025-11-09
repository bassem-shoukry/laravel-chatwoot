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
        Schema::create('chatwoot_messages', function (Blueprint $table) {
            $table->id();
            $table->string('account_key');
            $table->string('inbox_key');
            $table->string('template_key')->nullable();
            $table->integer('conversation_id')->nullable();
            $table->integer('contact_id')->nullable();
            $table->string('channel');
            $table->text('content');
            $table->json('variables')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'read'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['account_key', 'inbox_key', 'status']);
            $table->index(['conversation_id', 'created_at']);
            $table->index(['status', 'retry_count']);
            $table->foreign('account_key')->references('account_key')->on('chatwoot_accounts')->onDelete('cascade');
        });
    }

    /**
     * @throws RuntimeException
     */
    public function down(): void
    {
        Schema::dropIfExists('chatwoot_messages');
    }
};
