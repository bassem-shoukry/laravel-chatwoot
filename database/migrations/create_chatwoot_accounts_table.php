<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * Removed in v1: account configuration is now driven entirely by config/chatwoot.php
 * (no DB row required). This file is kept as a no-op so existing migration tables
 * can resolve the legacy filename without errors. New installs may delete it safely.
 */
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
