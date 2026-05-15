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
        Schema::create('bonus_grab_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_snapshot_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('route_label');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('cash_cost_min_nok')->nullable();
            $table->unsignedInteger('cash_cost_max_nok')->nullable();
            $table->unsignedInteger('expected_bonus_points')->default(0);
            $table->unsignedInteger('expected_level_points')->default(0);
            $table->unsignedTinyInteger('nights_away')->default(0);
            $table->string('cabin')->default('premium_economy');
            $table->unsignedTinyInteger('feasibility_score')->nullable();
            $table->string('status')->default('candidate');
            $table->string('source_url', 2000)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['loyalty_program_snapshot_id', 'status']);
            $table->index(['starts_on', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_grab_trips');
    }
};
