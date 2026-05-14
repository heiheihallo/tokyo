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
        Schema::create('trip_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('budget_scenario')->default('value');
            $table->string('stopover_type')->nullable();
            $table->string('flight_strategy')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('overrides')->nullable();
            $table->timestamps();

            $table->unique(['trip_id', 'slug']);
            $table->index(['trip_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_variants');
    }
};
