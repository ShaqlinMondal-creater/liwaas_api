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
        Schema::create('stocks_products', function (Blueprint $table) {

            $table->id();

            $table->string('uid')->unique();

            $table->string('name');

            $table->string('size')->nullable();

            $table->string('color')->nullable();

            $table->decimal('list_price', 10, 2)->default(0);

            $table->decimal('sale_price', 10, 2)->default(0);

            $table->integer('stock')->default(0);

            $table->boolean('status')->default(1);

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks_products');
    }
};
