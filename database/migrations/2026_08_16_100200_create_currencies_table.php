<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prices are stored in USD as the base currency. KHR is a display
     * conversion driven by `exchange_rate`. Orders snapshot the rate at
     * placement so a later change never rewrites historical totals.
     */
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();          // USD, KHR
            $table->string('name', 50);
            $table->string('symbol', 10);
            $table->decimal('exchange_rate', 16, 6)->default(1); // per 1 USD
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
