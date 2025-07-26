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
        Schema::create('t_coupon', function (Blueprint $table) {
            $table->id();
            $table->string('key_name')->unique();              // coupon code/key
            $table->decimal('value', 10, 2)->default(0);       // discount value (flat or %)
            $table->enum('status', ['active', 'deactive'])->default('active');
            $table->string('start_date')->nullable();          // string format (e.g., Y-m-d)
            $table->string('end_date')->nullable();            // string format (e.g., Y-m-d)
            $table->timestamps();                              // created_at & updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_coupon');
    }
};
