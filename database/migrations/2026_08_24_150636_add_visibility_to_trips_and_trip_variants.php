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
            $table->string('visibility', 20)->default('private')->after('published_at');
            $table->index(['visibility', 'published_at']);
        });

        Schema::table('trip_variants', function (Blueprint $table) {
            $table->string('visibility', 20)->default('private')->after('published_at');
            $table->index(['trip_id', 'visibility', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_variants', function (Blueprint $table) {
            $table->dropIndex(['trip_id', 'visibility', 'sort_order']);
            $table->dropColumn('visibility');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['visibility', 'published_at']);
            $table->dropColumn('visibility');
        });
    }
};
