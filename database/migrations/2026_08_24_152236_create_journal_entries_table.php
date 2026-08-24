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
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_variant_id')->nullable()->constrained('trip_variants')->nullOnDelete();
            $table->foreignId('day_node_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('day_itinerary_item_id')->nullable()->constrained('day_itinerary_items')->nullOnDelete();
            $table->string('title');
            $table->string('excerpt', 500)->nullable();
            $table->text('body')->nullable();
            $table->string('location_label')->nullable();
            $table->timestamp('happened_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('visibility', 20)->default('private');
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'visibility', 'published_at']);
            $table->index(['trip_variant_id', 'visibility']);
            $table->index(['day_node_id', 'visibility']);
            $table->index(['day_itinerary_item_id', 'visibility']);
            $table->index('happened_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
