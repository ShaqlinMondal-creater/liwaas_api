<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stocks_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->unsignedBigInteger('sales_order_item_id')->nullable();
            // ✅ STATUS COLUMN
            $table->enum('status', ['returned', 'migrated'])->default('returned');
            $table->string('uid', 100)->nullable();
            $table->integer('qty')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('sub_total', 10, 2)->default(0);
            $table->decimal('sub_total_tax', 10, 2)->default(0);
            $table->date('return_date')->nullable();
            $table->timestamps();

            // Optional foreign keys (if needed)
            // $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('cascade');
            // $table->foreign('sales_order_item_id')->references('id')->on('sales_order_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks_return_items');
    }
};