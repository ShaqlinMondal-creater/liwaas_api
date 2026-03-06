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
        Schema::create('stocks_clients', function (Blueprint $table) {

            $table->id();

            $table->string('name');

            $table->string('owner_name')->nullable();

            $table->string('mobile')->nullable();

            $table->text('address')->nullable();

            $table->string('email')->nullable();

            $table->boolean('status')->default(1);

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks_clients');
    }
};
