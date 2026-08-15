<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->default('general'); // general, shipping, tax, payment, mail
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('type', 20)->default('string');   // string, text, boolean, integer, json, file
            $table->boolean('is_translatable')->default(false);
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
