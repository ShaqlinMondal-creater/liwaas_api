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
        Schema::create('section_views', function (Blueprint $table) {
            $table->id();
            $table->string('section_name');
            $table->unsignedBigInteger('uid');
            $table->boolean('status')->default(true);         // true = active
            $table->boolean('force_status')->default(false);  // true = locked
            $table->timestamps(); // includes `created_at` and `updated_at`
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_views');
    }
};
