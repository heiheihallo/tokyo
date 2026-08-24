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
        Schema::table('trips', function (Blueprint $table) {
            $table->string('frontend_access', 20)->nullable()->after('visibility')->index();
        });

        Schema::table('trip_variants', function (Blueprint $table) {
            $table->string('frontend_access', 20)->nullable()->after('visibility')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_variants', function (Blueprint $table) {
            $table->dropColumn('frontend_access');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('frontend_access');
        });
    }
};
