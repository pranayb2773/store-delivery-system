<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address_line1');
            $table->string('city');
            $table->string('postcode');
            $table->decimal('latitude', 11, 7);
            $table->decimal('longitude', 11, 7);
            $table->decimal('delivery_radius_km', 6, 2)->default(5);
            $table->boolean('is_active')->default(true);
            $table->json('opening_hours')->nullable();
            $table->timestamps();

            $table->unique(['name', 'postcode']);
            $table->index(['latitude', 'longitude']);
            $table->index('is_active');
            $table->index('postcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
