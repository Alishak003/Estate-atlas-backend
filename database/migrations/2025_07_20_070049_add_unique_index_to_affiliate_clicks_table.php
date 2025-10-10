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
    Schema::table('affiliate_clicks', function (Blueprint $table) {
        // Drop the index if it exists
        $table->dropUnique('unique_affiliate_click');
        // Now add the unique index
        $table->unique(['affiliate_id', 'ip_address', 'user_agent'], 'unique_affiliate_click');
    });
}

public function down()
{
    Schema::table('affiliate_clicks', function (Blueprint $table) {
        $table->dropUnique('unique_affiliate_click');
    });
}

};
