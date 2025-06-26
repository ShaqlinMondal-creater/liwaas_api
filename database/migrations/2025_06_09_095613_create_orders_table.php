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
            $table->string('invoice_no')->nullable();
            $table->string('invoice_link')->nullable();

            $table->enum('shipping', ['Pending', 'Approved', 'Completed'])->default('Pending');
            $table->string('shipping_type')->nullable();
            $table->string('shipping_by')->nullable();
            $table->text('shipping_address');
            $table->decimal('shipping_charge', 10, 2)->default(0);

            // New fields added
            $table->decimal('tax_price', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2); // after tax + shipping (+/- coupon)

            $table->enum('payment_status', ['pending', 'completed', 'cancelled', 'In Queue'])->default('pending');
            $table->string('razorpay_order_id')->nullable(); // updated from payment_details 
            $table->enum('payment_type', ['COD', 'Preppaid', 'Postpaid'])->default('COD');

            $table->enum('delivery_status', ['pending', 'completed', 'shipped', 'Near You'])->default('pending');

            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('track_code')->nullable();

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
