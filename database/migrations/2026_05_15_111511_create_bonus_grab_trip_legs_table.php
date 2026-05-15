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
        Schema::create('bonus_grab_trip_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bonus_grab_trip_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('origin');
            $table->string('destination');
            $table->string('carrier')->nullable();
            $table->string('flight_number')->nullable();
            $table->string('cabin')->nullable();
            $table->dateTime('departs_at')->nullable();
            $table->dateTime('arrives_at')->nullable();
            $table->unsignedInteger('expected_bonus_points')->default(0);
            $table->unsignedInteger('expected_level_points')->default(0);
            $table->timestamps();

            $table->index(['bonus_grab_trip_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_grab_trip_legs');
    }
};
