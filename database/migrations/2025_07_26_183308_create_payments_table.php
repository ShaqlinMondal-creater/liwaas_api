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
        Schema::create('t_payments', function (Blueprint $table) {
            $table->id();
            $table->string('genarate_order_id')->nullable();       // Gateway-generated ID
            $table->enum('payment_type', ['COD', 'Preppaid'])->default('COD');           // e.g., Razorpay, COD
            $table->string('transaction_payment_id')->nullable();         // Payment ID from gateway
            $table->decimal('payment_amount', 10, 2)->default(0);   // Amount paid
            $table->enum('payment_status', ['pending', 'success', 'cancelled', 'failed', 'processing'])->default('pending');   // e.g., Pending, Success, Failed
            $table->unsignedBigInteger('order_id')->nullable();     // Link to your orders table
            $table->unsignedBigInteger('user_id')->nullable();      // Link to your users table
            $table->text('response_')->nullable();                  // JSON or raw text
            $table->timestamps();                                   // create & update
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_payments');
    }
};
