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
        Schema::create('facebook_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_page_id')->constrained()->onDelete('cascade');
            $table->string('message_id');
            $table->string('sender_id');
            $table->string('recipient_id');
            $table->text('message_text');
            $table->json('attachments')->nullable(); // Store message attachments
            $table->timestamp('sent_at');
            $table->boolean('is_reply')->default(false);
            $table->text('reply_text')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->unique(['facebook_page_id', 'message_id']);
            $table->index(['facebook_page_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_messages');
    }
};
