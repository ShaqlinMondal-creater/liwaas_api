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
        Schema::create('extras', function (Blueprint $table) {
            $table->id();
            $table->string('purpose_name');              // e.g. banner, offer, etc.
            $table->string('comments');  
            $table->string('highlighs');  
            $table->string('file_name');                 // original file name or custom name
            $table->string('file_path');                 // relative or full file path
            $table->boolean('show_status')->default(1); // 1 = show, 0 = hidden
            $table->timestamps();                        // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extras');
    }
};
