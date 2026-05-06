<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatwoot_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('account_name')->index();
            $table->unsignedBigInteger('chatwoot_account_id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->unsignedBigInteger('inbox_id')->nullable();
            $table->unsignedSmallInteger('message_type')->default(1);
            $table->string('content_type')->default('text');
            $table->longText('content')->nullable();
            $table->boolean('private')->default(false);
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('sender_type')->nullable();
            $table->json('content_attributes')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('chatwoot_created_at')->nullable();
            $table->timestamps();

            $table->unique(['account_name', 'message_id']);
            $table->index(['account_name', 'conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatwoot_messages');
    }
};
