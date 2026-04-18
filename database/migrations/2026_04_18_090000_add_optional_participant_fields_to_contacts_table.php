<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('school')->nullable()->after('education_level');
            $table->string('field')->nullable()->after('school');
            $table->string('participant_no')->nullable()->after('field')->index();
            $table->text('participant_card_link')->nullable()->after('participant_no');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['participant_no']);
            $table->dropColumn(['school', 'field', 'participant_no', 'participant_card_link']);
        });
    }
};
