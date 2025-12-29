<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdp_data', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->nullable();
            $table->string('country', 100)->nullable();
            $table->decimal('gdp', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdp_data');
    }
};
