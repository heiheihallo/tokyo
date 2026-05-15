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
        Schema::create('award_availability_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_fare_option_id')->constrained()->cascadeOnDelete();
            $table->date('checked_on');
            $table->string('route_label')->nullable();
            $table->string('travel_dates')->nullable();
            $table->string('cabin')->nullable();
            $table->unsignedTinyInteger('seats_seen')->nullable();
            $table->string('availability_status')->default('unknown');
            $table->string('source_url', 2000)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['transport_fare_option_id', 'checked_on']);
            $table->index('availability_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('award_availability_checks');
    }
};
