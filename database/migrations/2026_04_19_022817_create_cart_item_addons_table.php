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
        Schema::create('cart_item_addons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_item_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('addon_group_id')
                ->constrained('addons')
                ->cascadeOnDelete();

            $table->foreignId('addon_option_id')
                ->constrained('addon_options')
                ->cascadeOnDelete();

            $table->decimal('price', 10, 2)->default(0);
            $table->string('name')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_item_addons');
    }
};
