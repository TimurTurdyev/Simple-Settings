<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('simple-settings.table_name', 'simple_settings');

        if (!Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) {
                $table->string('group');
                $table->string('name');
                $table->text('val');
                $table->char('type', 20)->default('string');
                $table->timestamps();
                $table->primary(['group', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('simple-settings.table_name', 'simple_settings'));
    }
};