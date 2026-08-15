<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The product is the catalog header. Price and stock live on
     * product_variants — the columns here are defaults for display and
     * for filtering/sorting, kept in sync from the variant rows.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->json('title');                    // translatable
            $table->string('slug', 400)->unique();
            $table->string('sku', 100)->unique();
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();

            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            // Display price — mirrors the default variant, for listing and sort
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();

            $table->string('status', 20)->default('draft'); // draft, published, archived
            $table->string('condition', 20)->default('new'); // new, refurbished, used
            $table->boolean('is_featured')->default(false);

            // Electronics-specific
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->unsignedSmallInteger('release_year')->nullable();

            // Shipping dimensions
            $table->decimal('weight', 10, 3)->nullable();   // kg
            $table->decimal('length', 10, 2)->nullable();   // cm
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();

            // Denormalised aggregates
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);

            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_featured']);
            $table->index(['category_id', 'status']);
            $table->index(['brand_id', 'status']);
            $table->index('price');
            $table->index('release_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
