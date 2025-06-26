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
        Schema::create('carts', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('user_id');
            $table->unsignedBigInteger('products_id'); // product table ID
            $table->string('aid'); // product AID
            $table->unsignedBigInteger('uid'); // variation UID
            $table->decimal('regular_price', 10, 2);
            $table->decimal('sell_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('total_price', 10, 2);
            $table->timestamps(); // created_at and updated_at

            // Optional foreign keys (uncomment if desired)
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('products_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
