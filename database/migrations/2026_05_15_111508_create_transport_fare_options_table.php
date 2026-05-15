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
        Schema::create('transport_fare_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_leg_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('fare_type')->default('cash');
            $table->string('cabin')->default('economy');
            $table->string('carrier')->nullable();
            $table->unsignedTinyInteger('passengers')->default(2);
            $table->unsignedInteger('cash_min_nok')->nullable();
            $table->unsignedInteger('cash_max_nok')->nullable();
            $table->unsignedInteger('cash_min_jpy')->nullable();
            $table->unsignedInteger('cash_max_jpy')->nullable();
            $table->unsignedInteger('points_min')->nullable();
            $table->unsignedInteger('points_max')->nullable();
            $table->unsignedInteger('taxes_fees_min_nok')->nullable();
            $table->unsignedInteger('taxes_fees_max_nok')->nullable();
            $table->unsignedTinyInteger('voucher_count')->default(0);
            $table->unsignedInteger('expected_level_points')->default(0);
            $table->unsignedInteger('expected_bonus_points')->default(0);
            $table->string('travel_dates')->nullable();
            $table->date('observed_at')->nullable();
            $table->date('fresh_until')->nullable();
            $table->string('source_priority')->default('secondary');
            $table->string('source_url', 2000)->nullable();
            $table->string('status')->default('candidate');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['transport_leg_id', 'fare_type']);
            $table->index(['status', 'fresh_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transport_fare_options');
    }
};
