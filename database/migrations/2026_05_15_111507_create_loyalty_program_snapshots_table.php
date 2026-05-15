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
        Schema::create('loyalty_program_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('program_name')->default('EuroBonus');
            $table->unsignedInteger('current_points')->default(0);
            $table->unsignedInteger('current_level_points')->default(0);
            $table->date('qualification_starts_on')->nullable();
            $table->date('qualification_ends_on')->nullable();
            $table->string('target_tier')->default('Gold');
            $table->unsignedInteger('target_level_points')->default(45000);
            $table->unsignedInteger('target_qualifying_flights')->nullable();
            $table->unsignedInteger('expected_trip_level_points')->default(0);
            $table->unsignedInteger('signup_bonus_points')->default(0);
            $table->unsignedInteger('card_spend_target_nok')->default(0);
            $table->unsignedInteger('card_points_per_100_nok')->default(0);
            $table->unsignedInteger('card_level_points_per_100_nok')->default(0);
            $table->unsignedInteger('projected_card_points')->default(0);
            $table->unsignedInteger('projected_card_level_points')->default(0);
            $table->json('assumptions')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'program_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_program_snapshots');
    }
};
