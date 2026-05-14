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
        Schema::create('route_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('day_node_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stable_key');
            $table->string('name');
            $table->string('category')->default('place');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->string('route_group')->nullable();
            $table->string('external_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['trip_variant_id', 'stable_key']);
            $table->index(['trip_variant_id', 'route_group', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_points');
    }
};
