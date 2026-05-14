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
        Schema::create('day_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_variant_id')->constrained()->cascadeOnDelete();
            $table->string('stable_key');
            $table->unsignedSmallInteger('day_number');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('location');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('node_types');
            $table->string('booking_priority')->default('low');
            $table->string('booking_status')->default('unbooked');
            $table->string('weather_class')->nullable();
            $table->string('kid_energy_level')->nullable();
            $table->string('luggage_complexity')->nullable();
            $table->string('transport_method')->nullable();
            $table->string('duration_label')->nullable();
            $table->integer('cost_value_min_nok')->nullable();
            $table->integer('cost_value_max_nok')->nullable();
            $table->integer('cost_premium_min_nok')->nullable();
            $table->integer('cost_premium_max_nok')->nullable();
            $table->integer('cost_value_min_jpy')->nullable();
            $table->integer('cost_value_max_jpy')->nullable();
            $table->integer('cost_premium_min_jpy')->nullable();
            $table->integer('cost_premium_max_jpy')->nullable();
            $table->string('reservation_url')->nullable();
            $table->timestamp('cancellation_window_at')->nullable();
            $table->boolean('ics_exportable')->default(false);
            $table->json('details')->nullable();
            $table->timestamps();

            $table->unique(['trip_variant_id', 'stable_key']);
            $table->index(['trip_variant_id', 'day_number']);
            $table->index(['trip_id', 'booking_priority']);
            $table->index(['trip_id', 'booking_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_nodes');
    }
};
