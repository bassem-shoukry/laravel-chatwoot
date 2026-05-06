<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * Removed in v1: template management is delegated to Chatwoot itself
 * (use the CannedResponseResource or WhatsApp message templates). This
 * file is a no-op kept for legacy installs and may be deleted on new installs.
 */
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
