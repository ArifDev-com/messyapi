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
        Schema::create('facebook_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_account_id')->constrained()->onDelete('cascade');
            $table->string('page_id');
            $table->string('name');
            $table->string('access_token');
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('webhook_data')->nullable(); // Store webhook configuration
            $table->timestamps();

            $table->unique(['facebook_account_id', 'page_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_pages');
    }
};
