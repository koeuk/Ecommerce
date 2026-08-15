<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The spec sheet — descriptive only, never generates a SKU.
     * e.g. group "Processor", key "CPU", value "Intel Core i7-13700H".
     */
    public function up(): void
    {
        Schema::create('product_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->json('group')->nullable();    // translatable: "Display"
            $table->json('key');                  // translatable: "Screen Size"
            $table->json('value');                // translatable: "15.6 inch"
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specifications');
    }
};
