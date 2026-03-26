<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sender_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mailer')->default('smtp');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('encryption')->nullable();
            $table->string('username');
            $table->text('password');
            $table->string('from_address');
            $table->string('from_name');
            $table->string('reply_to_address')->nullable();
            $table->unsignedInteger('daily_limit')->default(150);
            $table->unsignedInteger('hourly_limit')->default(40);
            $table->unsignedInteger('sent_today')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sender_accounts');
    }
};
