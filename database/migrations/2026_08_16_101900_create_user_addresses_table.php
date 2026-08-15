<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50)->nullable();   // Home, Office
            $table->string('receiver_name');
            $table->string('phone', 30);
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city', 100);
            $table->string('state', 100);              // province — matched to shipping zone
            $table->string('postal_code', 20)->nullable();
            $table->string('country_code', 2)->default('KH');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_default_shipping']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
