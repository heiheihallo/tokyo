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
        Schema::create('accommodations', function (Blueprint $table) {
            $table->id();
            $table->string('stable_key')->unique();
            $table->string('name');
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('breakfast_note')->nullable();
            $table->string('dinner_note')->nullable();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('reservation_url')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accommodations');
    }
};
