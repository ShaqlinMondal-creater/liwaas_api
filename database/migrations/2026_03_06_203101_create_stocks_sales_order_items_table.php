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
        Schema::create('stocks_sales_order_items', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('sales_order_id');
            // ✅ STATUS COLUMN
            $table->enum('status', ['returned', 'completed', 'inprocess'])->nullable()->default(null);

            $table->string('uid');

            $table->integer('qty');

            $table->decimal('price', 10, 2);

            $table->decimal('tax', 10, 2)->default(0);

            $table->decimal('sub_total', 12, 2);

            $table->decimal('sub_total_tax', 12, 2)->default(0);

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks_sales_order_items');
    }
};
