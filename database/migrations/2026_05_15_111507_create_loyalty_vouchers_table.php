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
        Schema::create('loyalty_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_snapshot_id')->constrained()->cascadeOnDelete();
            $table->string('voucher_type')->default('2-for-1');
            $table->string('status')->default('expected');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('earned_threshold_nok')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['loyalty_program_snapshot_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_vouchers');
    }
};
