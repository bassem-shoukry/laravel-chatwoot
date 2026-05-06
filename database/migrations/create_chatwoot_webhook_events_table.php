<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatwoot_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('account_name')->index();
            $table->string('event')->index();
            $table->json('payload');
            $table->boolean('verified')->default(false);
            $table->timestamps();

            $table->index(['account_name', 'event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatwoot_webhook_events');
    }
};
