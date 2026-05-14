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
            $table->boolean('is_public')->default(false)->after('arrival_preference');
            $table->timestamp('published_at')->nullable()->after('is_public');
            $table->index(['is_public', 'published_at']);
        });

        Schema::table('trip_variants', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('is_default');
            $table->timestamp('published_at')->nullable()->after('is_public');
            $table->index(['trip_id', 'is_public', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_variants', function (Blueprint $table) {
            $table->dropIndex(['trip_id', 'is_public', 'sort_order']);
            $table->dropColumn(['is_public', 'published_at']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'published_at']);
            $table->dropColumn(['is_public', 'published_at']);
        });
    }
};
