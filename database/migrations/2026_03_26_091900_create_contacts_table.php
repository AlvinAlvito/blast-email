<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('telegram')->nullable()->index();
            $table->string('source_sheet')->nullable();
            $table->string('source_year')->nullable()->index();
            $table->string('segment')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->boolean('email_opt_out')->default(false);
            $table->boolean('is_duplicate')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
