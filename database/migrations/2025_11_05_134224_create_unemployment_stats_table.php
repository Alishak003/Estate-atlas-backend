<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unemployment_stats', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->string('country', 100);
            $table->decimal('unemployment_rate', 10, 2)->nullable();
            $table->timestamp('date_recorded')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unemployment_stats');
    }
};
