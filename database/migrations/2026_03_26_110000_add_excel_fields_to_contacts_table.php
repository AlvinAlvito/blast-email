<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('import_no')->nullable()->after('id');
            $table->string('province')->nullable()->after('phone');
            $table->string('city')->nullable()->after('province');
            $table->string('education_level')->nullable()->after('city')->index();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['import_no', 'province', 'city', 'education_level']);
        });
    }
};
