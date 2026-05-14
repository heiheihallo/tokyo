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
        Schema::create('activity_day_node', function (Blueprint $table) {
            $table->foreignId('day_node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->string('time_block')->nullable();
            $table->string('booking_status')->default('planned');
            $table->string('reservation_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->primary(['day_node_id', 'activity_id']);
            $table->index(['day_node_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_day_node');
    }
};
