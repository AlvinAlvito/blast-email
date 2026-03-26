<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel')->default('email')->index();
            $table->string('status')->default('draft')->index();
            $table->string('segment')->nullable()->index();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->unsignedInteger('batch_size')->default(50);
            $table->unsignedInteger('delay_seconds')->default(10);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
