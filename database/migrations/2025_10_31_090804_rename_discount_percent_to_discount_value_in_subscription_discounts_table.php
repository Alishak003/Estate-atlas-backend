<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_discounts', function (Blueprint $table) {
            $table->renameColumn('discount_percent', 'discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_discounts', function (Blueprint $table) {
            $table->renameColumn('discount_value', 'discount_percent');
        });
    }
};
