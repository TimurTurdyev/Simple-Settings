<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The type column was created as char(20). PostgreSQL (bpchar) returns such
 * values padded with trailing spaces, which broke type detection on read;
 * casting the column to varchar also strips that padding from the stored data.
 */
return new class extends Migration {
    public function up(): void
    {
        $table = config('simple-settings.table_name', 'simple_settings');

        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->string('type', 20)->default('string')->change();
        });
    }

    public function down(): void
    {
        $table = config('simple-settings.table_name', 'simple_settings');

        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->char('type', 20)->default('string')->change();
        });
    }
};
