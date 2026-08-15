<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attributes are the options that GENERATE variants (RAM, Storage,
     * Colour, Case Size). Descriptive-only data lives in
     * product_specifications instead.
     */
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->json('name');                 // translatable: "RAM", "Case Size"
            $table->string('code', 50)->unique(); // ram, case_size
            $table->string('input_type', 20)->default('select'); // select, colour, button
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->json('label');                // translatable: "16 GB"
            $table->string('value', 100);         // canonical: "16gb"
            $table->string('colour_hex', 7)->nullable(); // for colour swatches
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
