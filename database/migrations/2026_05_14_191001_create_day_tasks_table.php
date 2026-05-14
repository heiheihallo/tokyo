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
        Schema::create('day_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('day_node_id')->constrained()->cascadeOnDelete();
            $table->string('stable_key');
            $table->string('task_type')->default('todo');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->date('due_on')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->unique(['day_node_id', 'stable_key']);
            $table->index(['day_node_id', 'status']);
            $table->index(['trip_variant_id', 'priority']);
            $table->index(['trip_id', 'task_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_tasks');
    }
};
