<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('platform'); // 'facebook', 'whatsapp'
            $table->string('platform_user_id'); // Facebook sender ID
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('profile_data')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'platform_user_id']);
        });

        // Add customer_id to facebook_messages table
        Schema::table('facebook_messages', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->index('customer_id');
            $table->boolean('is_echo')->default(false);
        });
    }

    public function down()
    {
        Schema::table('facebook_messages', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
            $table->dropColumn('is_echo');
        });
        Schema::dropIfExists('customers');
    }
};
