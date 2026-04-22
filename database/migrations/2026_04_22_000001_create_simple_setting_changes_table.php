<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('simple-settings.audit.table', 'simple_setting_changes');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->string('event', 20);
            $table->text('old_payload')->nullable();
            $table->text('new_payload')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['group', 'name', 'created_at'], 'simple_setting_changes_target_idx');
            $table->index(['causer_type', 'causer_id'], 'simple_setting_changes_causer_idx');
            $table->index('event', 'simple_setting_changes_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('simple-settings.audit.table', 'simple_setting_changes'));
    }
};
