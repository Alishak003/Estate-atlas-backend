<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id'); // reference to subscriptions table
            $table->string('discount_type', 255)->nullable(); // e.g., percentage, fixed
            $table->string('discount_id', 255)->nullable(); // Stripe coupon ID or internal ID
            $table->integer('discount_percent')->nullable(); // discount percentage
            $table->timestamp('discount_applied_at')->nullable(); // when discount started
            $table->timestamp('discount_ends_at')->nullable(); // when discount ends
            $table->string('status', 50)->default('active'); // active, expired, upcoming
            $table->timestamps();

            // Foreign key constraint if subscriptions table exists
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_discounts');
    }
};
