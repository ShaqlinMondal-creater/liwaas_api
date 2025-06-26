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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registered_user'); // user ID without foreign key
            $table->string('name');
            $table->string('email');
            $table->enum('address_type', ['primary', 'secondary'])->default('secondary');
            $table->string('mobile');
            $table->string('state');
            $table->string('city');
            $table->string('country')->default('INDIA');
            $table->string('pincode');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
