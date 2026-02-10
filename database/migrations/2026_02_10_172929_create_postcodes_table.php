<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postcodes', function (Blueprint $table) {
            $table->id();
            $table->string('postcode');
            $table->decimal('latitude', 11, 7);
            $table->decimal('longitude', 11, 7);
            $table->timestamps();

            $table->unique('postcode');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postcodes');
    }
};
