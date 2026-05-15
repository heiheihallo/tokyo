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
        foreach (['accommodations', 'activities', 'food_spots', 'transport_legs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('price_min_nok')->nullable()->after('notes');
                $table->unsignedInteger('price_max_nok')->nullable()->after('price_min_nok');
                $table->unsignedInteger('price_min_jpy')->nullable()->after('price_max_nok');
                $table->unsignedInteger('price_max_jpy')->nullable()->after('price_min_jpy');
                $table->string('price_basis')->default('unknown')->after('price_max_jpy');
                $table->text('price_notes')->nullable()->after('price_basis');

                $table->index('price_basis');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['accommodations', 'activities', 'food_spots', 'transport_legs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex($tableName.'_price_basis_index');
                $table->dropColumn([
                    'price_min_nok',
                    'price_max_nok',
                    'price_min_jpy',
                    'price_max_jpy',
                    'price_basis',
                    'price_notes',
                ]);
            });
        }
    }
};
