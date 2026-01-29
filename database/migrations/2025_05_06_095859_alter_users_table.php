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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['customer', 'admin'])->default('customer');
            $table->string('mobile')->unique();
            $table->string('google_id')->nullable()->unique();
            $table->string('auth_provider')->default('email'); // email | google
            $table->enum('is_active', ['true', 'false'])->default('false');
            $table->enum('is_logged_in', ['true', 'false'])->default('false');
            $table->timestamp('is_deleted')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
