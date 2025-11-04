<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            
            // Check existence for each column before adding it 🛡️
            if (!Schema::hasColumn('subscriptions', 'discount_type')) {
                $table->string('discount_type')->after('ends_at')->nullable(); 
            }
            if (!Schema::hasColumn('subscriptions', 'discount_id')) {
                $table->string('discount_id')->after('discount_type')->nullable(); 
            }
            if (!Schema::hasColumn('subscriptions', 'discount_percent')) {
                $table->integer('discount_percent')->after('discount_id')->nullable(); 
            }
            if (!Schema::hasColumn('subscriptions', 'discount_applied_at')) {
                $table->timestamp('discount_applied_at')->after('discount_percent')->nullable(); 
            }
            if (!Schema::hasColumn('subscriptions', 'discount_ends_at')) {
                $table->timestamp('discount_ends_at')->after('discount_applied_at')->nullable(); 
            }
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // It's also safer to wrap dropColumn in a check
            if (Schema::hasColumn('subscriptions', 'discount_type')) {
                $table->dropColumn([
                    'discount_type',
                    'discount_id',
                    'discount_percent',
                    'discount_applied_at',
                    'discount_ends_at',
                ]);
            }
        });
    }
};
