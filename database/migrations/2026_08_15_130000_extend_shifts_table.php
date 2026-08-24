<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The `group` column has no working UI to set it since the shift-category
     * feature replaced it as the way to group shifts - every current insert
     * path (CSV import, the Livewire create form) either hardcodes 0 or omits
     * it entirely, which fails without a default since the column is NOT NULL.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->integer('group')->default(0)->change();
            $table->text('description')->nullable()->change();
            $table->boolean('requires_health_certificate')->default(false);
            $table->boolean('requires_clothing_size')->default(false);
            $table->unsignedInteger('unsubscribe_lock_hours')->default(24);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->integer('group')->change();
            $table->string('description', 500)->nullable()->change();
            $table->dropColumn(['requires_health_certificate', 'requires_clothing_size', 'unsubscribe_lock_hours']);
        });
    }
};
