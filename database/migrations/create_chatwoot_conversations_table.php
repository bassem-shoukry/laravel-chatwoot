<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatwoot_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('account_name')->index();
            $table->unsignedBigInteger('chatwoot_account_id');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('inbox_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->string('status')->default('open')->index();
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->json('labels')->nullable();
            $table->json('additional_attributes')->nullable();
            $table->timestamps();

            $table->unique(['account_name', 'conversation_id']);
            $table->index(['account_name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatwoot_conversations');
    }
};
