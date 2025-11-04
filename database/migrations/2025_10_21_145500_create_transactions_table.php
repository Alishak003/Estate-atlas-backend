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
        if (!Schema::hasTable('transactions')){
        Schema::create('transactions', function (Blueprint $table) {
            $table->id(); // Handles the BIGINT UNSIGNED primary key

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('stripe_charge_id', 50)->nullable()->unique();
            $table->string('stripe_invoice_id', 50)->nullable()->unique();
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3);
            $table->string('type', 50); // VARCHAR for flexibility

            // Foreign keys to related entities (nullable for flexibility)
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->onDelete('set null');

            $table->string('failure_reason')->nullable();
            
            $table->timestamps(); // Handles created_at and updated_at
            $table->unsignedBigInteger('created_by')->nullable(); // For admin creation
        });
    }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
