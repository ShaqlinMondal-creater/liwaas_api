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
        Schema::create('stocks_sales_orders', function (Blueprint $table) {

            $table->id();

            $table->string('sales_order_no')->unique();

            $table->unsignedBigInteger('client_id');

            $table->decimal('grand_total', 12, 2)->default(0);

            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('round_amount', 12, 2)->default(0);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks_sales_orders');
    }
};
