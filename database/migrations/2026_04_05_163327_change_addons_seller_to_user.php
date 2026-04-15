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
        Schema::table('addons', function (Blueprint $table) {
            // drop old FK
            $table->dropForeign(['seller_id']);

            // rename column
        });

        Schema::table('addons', function (Blueprint $table) {
            // add new FK
            $table->foreign('seller_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
        });

        Schema::table('addons', function (Blueprint $table) {
            $table->foreign('seller_id')
                ->references('id')
                ->on('sellers')
                ->onDelete('cascade');
        });
    }
};
