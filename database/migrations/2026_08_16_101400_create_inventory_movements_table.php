<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail for stock. Every change to a variant's
     * stock_quantity should write a row here so discrepancies are traceable.
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);            // in, out, adjustment, return
            $table->integer('quantity');           // signed
            $table->unsignedInteger('stock_after');
            $table->string('reason')->nullable();
            $table->nullableMorphs('reference');   // Order, Refund, manual...
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_variant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
