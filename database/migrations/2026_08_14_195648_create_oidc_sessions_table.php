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
        Schema::create('oidc_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sub')->index();
            $table->string('sid')->nullable()->index();
            $table->string('laravel_session_id')->index();
            // needed as the id_token_hint on RP-initiated logout - without it
            // most providers won't trust our post_logout_redirect_uri and
            // show their own logout page instead of sending the visitor back
            $table->text('id_token')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oidc_sessions');
    }
};
