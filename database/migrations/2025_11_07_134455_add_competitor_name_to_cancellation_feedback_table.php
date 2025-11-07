<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cancellation_feedback', function (Blueprint $table) {
            $table->string('competitor_name')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('cancellation_feedback', function (Blueprint $table) {
            $table->dropColumn('competitor_name');
        });
    }
};
