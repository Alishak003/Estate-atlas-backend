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
        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('affiliate_id')->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent', 255)->nullable(); // <-- FIXED: was text, now string(255)
            $table->string('referer')->nullable();
            $table->string('code')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->enum('status', ['referred', 'not_referred'])->default('not_referred');
            $table->timestamps();

            $table->index(['affiliate_id', 'clicked_at']);
            $table->index('ip_address');
            $table->unique(['affiliate_id', 'ip_address', 'user_agent'], 'unique_affiliate_click');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_clicks');
    }
};
