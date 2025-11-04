<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // Basic coupon info
            $table->string('code')->unique();          // e.g., KEEP30, BLACKFRIDAY
            $table->string('name')->nullable();        // Optional human-readable name
            $table->enum('type', [ 'manual', 'promo_code'])->default('manual');

            // Discount details
            $table->enum('discount_type', ['percent', 'amount'])->default('percent');
            $table->decimal('discount_value', 8, 2);   // 30 for 30%, 10 for $10 off

            // Duration
            $table->enum('duration', ['once', 'repeating', 'forever'])->default('once');
            $table->integer('duration_in_months')->nullable(); // Only for repeating
            $table->integer('duration_in_days')->nullable();   // Optional for manual/promo discounts

            // Metadata
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
