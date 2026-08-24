<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // nullable: existing plans predate accounts and have no owner to
            // backfill; the application always sets this for newly created
            // plans (login required)
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();

            $table->string('contact_email', 200)->nullable()->after('owner_email');
            $table->string('contact_phone', 20)->nullable()->after('contact_email');

            // plans created before these columns existed were effectively
            // always public and reachable via their link, so they're
            // backfilled as published/active below to keep their current
            // visibility - only newly created plans default to unpublished
            // and inactive until the owner explicitly changes that
            $table->boolean('published')->default(false);
            $table->boolean('active')->default(false);

            $table->string('logo')->nullable();
            $table->boolean('show_subscriber_names')->default(false);
        });

        // superseded by OIDC-login-based access (Plan::isManageableBy());
        // plan management is no longer reachable via a secret edit_id link
        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['edit_id']);
            $table->dropColumn(['edit_id', 'contact']);
            $table->text('description')->change();
        });

        DB::table('plans')->update(['published' => true, 'active' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('edit_id', 40)->unique()->nullable();
            $table->string('contact', 200)->nullable();
            $table->string('description', 500)->change();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['contact_email', 'contact_phone', 'published', 'active', 'logo', 'show_subscriber_names']);
        });
    }
};
