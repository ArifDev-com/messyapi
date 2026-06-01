<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('facebook_messages', function (Blueprint $table) {
            $table->dropUnique(
                'facebook_messages_facebook_page_id_message_id_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('facebook_messages', function (Blueprint $table) {
            $table->unique(
                ['facebook_page_id', 'message_id'],
                'facebook_messages_facebook_page_id_message_id_unique'
            );
        });
    }
};
