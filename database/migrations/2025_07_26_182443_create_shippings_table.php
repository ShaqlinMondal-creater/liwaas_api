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
        Schema::create('t_shipping', function (Blueprint $table) {
            $table->id();
            $table->enum('shipping_status', ['Pending', 'Approved', 'Completed'])->default('Pending');
            $table->enum('shipping_type', ['Home', 'Work', 'Other'])->default('Home');
            $table->string('shipping_by')->nullable(); // e.g., Shiprocket, Delhivery
            $table->unsignedBigInteger('address_id')->nullable();
            $table->decimal('shipping_charge', 10, 2)->default(0);
            $table->string('shipping_delivery_id')->nullable(); // external ID from courier service
            $table->text('response_')->nullable(); // full JSON/text response
            $table->timestamps(); // includes created_at and updated_at

            // Optional foreign key constraint
            // $table->foreign('address_id')->references('id')->on('addresses')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_shipping');
    }
};
