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
        Schema::create('day_itinerary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('day_node_id')->constrained()->cascadeOnDelete();
            $table->string('stable_key');
            $table->string('item_type');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('time_label')->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('location_label')->nullable();
            $table->nullableMorphs('subject');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('details')->nullable();
            $table->timestamps();

            $table->unique(['day_node_id', 'stable_key']);
            $table->index(['day_node_id', 'sort_order']);
            $table->index(['trip_variant_id', 'item_type']);
            $table->index(['trip_id', 'item_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_itinerary_items');
    }
};
