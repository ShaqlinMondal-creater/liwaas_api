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
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('products_id'); // product table ID
            $table->string('aid'); // product AID
            $table->unsignedBigInteger('uid'); // variation UID
            $table->tinyInteger('total_star')->comment('Rating from 1 to 5');
            $table->text('comments')->nullable();
            $table->json('upload_images')->nullable(); // Store image URLs or paths as JSON
            $table->timestamps(); // created_at and updated_at

            // Optional foreign key constraints (uncomment if needed)
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('products_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
