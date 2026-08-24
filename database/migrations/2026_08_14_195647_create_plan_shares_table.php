<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plan_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('group');
            // a shared group either gets full management rights
            // (create/edit/delete shifts, manage subscriptions,
            // export/import) or just read-only access to view the plan
            $table->string('access')->default('manage');
            $table->timestamps();

            $table->unique(['plan_id', 'group']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_shares');
    }
};
