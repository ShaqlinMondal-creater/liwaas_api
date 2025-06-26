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
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id(); // id (auto-increment)
            $table->unsignedBigInteger('uid'); // user ID or related UID
            $table->string('aid'); // product AID (foreign key to products table if needed)
            $table->string('color');
            $table->string('size');
            $table->float('regular_price', 10, 2);
            $table->float('sell_price', 10, 2);
            $table->string('currency')->default('INR');
            $table->float('gst', 10, 2);
            $table->integer('stock');
             $table->string('images_id')->nullable();
            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
