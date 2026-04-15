<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('simple_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();

            $table->integer('base_prep_time')->nullable();

            $table->string('main_image')->nullable();
            $table->json('additional_images')->nullable();

            $table->enum('image_fit', ['cover', 'contain'])->default('cover');

            $table->string('short_description')->nullable();
            $table->longText('description')->nullable();

            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->decimal('cost_per_item', 10, 2)->nullable();

            // ✅ New fields
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();

            $table->boolean('is_featured')->default(false);

            $table->enum('verification_status', ['pending', 'approved', 'rejected'])
                ->default('pending');

            $table->softDeletes(); // adds deleted_at

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simple_products');
    }
};
