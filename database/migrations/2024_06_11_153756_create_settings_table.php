<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simple_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->text('val');
            $table->char('type', 20)->default('string');
            $table->timestamps();
            $table->unique(['group', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simple_settings');
    }
};