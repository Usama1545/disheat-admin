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
        Schema::table('cart_items', function (Blueprint $table) {
            $table->string('addons_signature')->default('no_addons')->after('save_for_later');
            $table->index(['cart_id', 'product_id', 'store_id', 'addons_signature'], 'cart_items_unique_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_unique_lookup');
            $table->dropColumn('addons_signature');
        });
    }
};
