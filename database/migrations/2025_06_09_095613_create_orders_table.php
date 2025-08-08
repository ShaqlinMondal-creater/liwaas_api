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
        Schema::create('orders', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->unsignedBigInteger('user_id');

            $table->string('order_code', 9)->unique(); // Format: XXXX-YYYY
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('shipping_id')->nullable();

            // New fields added
            $table->decimal('tax_price', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2); // after tax + shipping (+/- coupon)

            $table->enum('payment_type', ['COD', 'Preppaid', 'Postpaid'])->default('COD');
            $table->unsignedBigInteger('payment_id')->nullable(); // updated from payment_details 

            $table->enum('delivery_status', ['pending', 'completed', 'shipped', 'Near You'])->default('pending');

            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->decimal('coupon_discount', 10, 2)->nullable();
            $table->text('other_text')->nullable(); 
            $table->timestamps(); // created_at and updated_at

            // Foreign key constraint
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
