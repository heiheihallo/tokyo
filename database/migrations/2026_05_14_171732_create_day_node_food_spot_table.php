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
        Schema::create('day_node_food_spot', function (Blueprint $table) {
            $table->foreignId('day_node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_spot_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->string('meal_type')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->primary(['day_node_id', 'food_spot_id']);
            $table->index(['day_node_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_node_food_spot');
    }
};
