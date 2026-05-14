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
        Schema::create('day_node_accommodation', function (Blueprint $table) {
            $table->foreignId('day_node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accommodation_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('overnight');
            $table->string('confirmation_status')->default('planned');
            $table->string('reservation_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->primary(['day_node_id', 'accommodation_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_node_accommodation');
    }
};
