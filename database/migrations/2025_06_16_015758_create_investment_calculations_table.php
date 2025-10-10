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
        Schema::create('investment_calculations', function (Blueprint $table) {
             $table->id();
            $table->decimal('property_value', 15, 2);
            $table->decimal('down_payment', 5, 2); // %
            $table->decimal('interest_rate', 5, 2); // %
            $table->integer('loan_term'); // years
            $table->decimal('monthly_rental_income', 12, 2);
            $table->decimal('monthly_expenses', 12, 2);
            $table->decimal('monthly_mortgage', 12, 2);
            $table->decimal('annual_cash_flow', 12, 2);
            $table->decimal('roi_percent', 6, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_calculations');
    }
};
