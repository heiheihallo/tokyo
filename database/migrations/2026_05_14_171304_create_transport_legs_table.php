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
        Schema::create('transport_legs', function (Blueprint $table) {
            $table->id();
            $table->string('stable_key')->unique();
            $table->string('mode');
            $table->string('route_label');
            $table->string('duration_label')->nullable();
            $table->string('operator')->nullable();
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
            $table->string('reservation_url')->nullable();
            $table->json('geo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transport_legs');
    }
};
