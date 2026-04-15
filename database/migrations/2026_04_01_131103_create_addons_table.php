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
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->onDelete('cascade');
            $table->json('name'); // for translations (en, ar, etc.)
            $table->enum('type', ['single', 'multiple'])->default('multiple');
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('min_select')->default(0);
            $table->unsignedInteger('max_select')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
