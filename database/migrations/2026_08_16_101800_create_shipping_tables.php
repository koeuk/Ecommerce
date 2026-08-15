<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cambodia-only: zones are Phnom Penh vs provinces, matched by the
     * province/state on the delivery address.
     */
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);            // "Phnom Penh", "Provinces"
            $table->json('provinces')->nullable();  // matched against address state
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->json('name');                   // translatable: "Standard Delivery"
            $table->json('description')->nullable();
            $table->string('rate_type', 20)->default('flat'); // flat, weight, free
            $table->decimal('base_rate', 12, 2)->default(0);
            $table->decimal('per_kg_rate', 12, 2)->nullable();
            $table->decimal('free_above_total', 12, 2)->nullable();
            $table->unsignedSmallInteger('min_days')->nullable();
            $table->unsignedSmallInteger('max_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['shipping_zone_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zones');
    }
};
