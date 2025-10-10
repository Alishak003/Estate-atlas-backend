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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_id',
                'subscription_amount',
                'subscription_currency',
                'subscription_start_date'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('subscription_id')->nullable();
            $table->decimal('subscription_amount', 8, 2)->nullable();
            $table->string('subscription_currency')->nullable();
            $table->timestamp('subscription_start_date')->nullable();
        });
    }
};
