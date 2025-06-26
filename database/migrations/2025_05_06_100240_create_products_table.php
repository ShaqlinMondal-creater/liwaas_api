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
        Schema::create('products', function (Blueprint $table) {
            $table->id(); // Primary Key: id
            $table->string('aid'); // Product Code
            $table->string('name');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('category_id');
            $table->string('slug');
            $table->longText('description')->nullable();
            $table->longText('specification')->nullable();
            $table->enum('gender', ['male', 'female', 'unisex'])->default('unisex');
            $table->enum('cod', ['available', 'not available'])->default('available');
            $table->enum('shipping', ['available', 'not available'])->default('available');
            $table->double('ratings')->default(0);
            $table->longText('keyword')->nullable();
            $table->longText('image_url')->nullable();
            $table->string('upload_id')->nullable();
            // $table->unsignedBigInteger('upload_id')->nullable();
            $table->enum('product_status', ['active', 'inactive'])->default('active');
            $table->string('added_by')->default('admin');
            $table->enum('custom_design', ['available', 'not available'])->default('not available');
            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
