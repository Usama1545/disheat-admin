<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('store_inventory_logs', function (Blueprint $table) {
            // Drop existing foreign key constraint (replace 'store_inventory_logs_product_variant_id_foreign' with actual constraint name)
            $table->dropForeign(['product_variant_id']);
            
            // Rename the column
            $table->renameColumn('product_variant_id', 'product_id');
        });
        
        // Add new foreign key constraint to products table
        Schema::table('store_inventory_logs', function (Blueprint $table) {
            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('store_inventory_logs', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign(['product_id']);
            
            // Rename back to original column name
            $table->renameColumn('product_id', 'product_variant_id');
        });
        
        // Restore original foreign key constraint (adjust constraint name as needed)
        Schema::table('store_inventory_logs', function (Blueprint $table) {
            $table->foreign('product_variant_id')
                  ->references('id')
                  ->on('product_variants')
                  ->onDelete('cascade');
        });
    }
};
