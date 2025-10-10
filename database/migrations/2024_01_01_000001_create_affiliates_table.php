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
    Schema::create('affiliates', function (Blueprint $table) {
        $table->id();
        $table->string('user_id'); // Removed ->constrained(), should be bigint if FK
        $table->string('affiliate_code', 20)->unique()->nullable();
        $table->decimal('commission_rate', 5, 2)->default(50.00);
        $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
        $table->integer('total_clicks')->default(0);
        $table->integer('total_referrals')->default(0);
        $table->decimal('total_commission', 10, 2)->default(0.00);
        $table->integer('total_visits')->default(0);
        $table->integer('link_generated_count')->default(0);
        $table->text('affiliate_link')->nullable();
        $table->integer('visits_count')->default(0);

        $table->timestamps();

        $table->index(['affiliate_code']);
        $table->index(['user_id']);
        $table->index(['user_id', 'status']);
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliates');
    }
};
