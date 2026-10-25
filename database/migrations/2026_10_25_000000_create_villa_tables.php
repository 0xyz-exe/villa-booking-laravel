<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('villas', function (Blueprint $table) {
            $table->id(); $table->foreignId('owner_id')->constrained('users')->restrictOnDelete(); $table->string('name'); $table->string('location');
            $table->text('description')->nullable(); $table->unsignedSmallInteger('guest_capacity'); $table->decimal('nightly_rate', 10, 2);
            $table->unsignedSmallInteger('minimum_stay')->default(1); $table->string('image_path')->nullable(); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('amenities', function (Blueprint $table) { $table->id(); $table->string('name')->unique(); $table->timestamps(); });
        Schema::create('amenity_villa', function (Blueprint $table) { $table->foreignId('villa_id')->constrained()->cascadeOnDelete(); $table->foreignId('amenity_id')->constrained()->cascadeOnDelete(); $table->primary(['villa_id', 'amenity_id']); });
        Schema::create('seasonal_rates', function (Blueprint $table) {
            $table->id(); $table->foreignId('villa_id')->constrained()->cascadeOnDelete(); $table->date('starts_on'); $table->date('ends_on');
            $table->decimal('nightly_rate', 10, 2); $table->timestamps(); $table->index(['villa_id', 'starts_on', 'ends_on']);
        });
        Schema::create('reservations', function (Blueprint $table) {
            $table->id(); $table->string('reference', 20)->unique(); $table->foreignId('villa_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->constrained('users')->cascadeOnDelete(); $table->date('check_in'); $table->date('check_out');
            $table->unsignedSmallInteger('guests'); $table->unsignedSmallInteger('nights'); $table->decimal('total_price', 12, 2);
            $table->string('status')->default('confirmed'); $table->timestamps(); $table->index(['villa_id', 'check_in', 'check_out', 'status']);
        });
        Schema::create('villa_reviews', function (Blueprint $table) {
            $table->id(); $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete(); $table->foreignId('guest_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); $table->string('comment', 1000)->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('villa_reviews'); Schema::dropIfExists('reservations'); Schema::dropIfExists('seasonal_rates'); Schema::dropIfExists('amenity_villa'); Schema::dropIfExists('amenities'); Schema::dropIfExists('villas');
    }
};
