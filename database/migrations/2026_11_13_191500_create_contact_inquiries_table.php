<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contact_inquiries')) {
            Schema::create('contact_inquiries', function (Blueprint $table) {
                $table->id();
                $table->string('first_name', 80);
                $table->string('last_name', 80);
                $table->string('email', 150);
                $table->string('phone', 30)->nullable();
                $table->string('subject', 150);
                $table->text('message');
                $table->boolean('promotions')->default(false);
                $table->timestamps();
            });
        }

        $path = storage_path('app/contact_inquiries.json');

        if (!is_file($path)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $email = strtolower(trim((string) ($item['email'] ?? '')));
            $message = trim((string) ($item['message'] ?? ''));

            if ($email === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $createdAt = strtotime((string) ($item['created_at'] ?? ''));
            $promotions = $item['promotions'] ?? false;

            $rows[] = [
                'first_name' => mb_substr(trim((string) ($item['first_name'] ?? '')), 0, 80),
                'last_name' => mb_substr(trim((string) ($item['last_name'] ?? '')), 0, 80),
                'email' => mb_substr($email, 0, 150),
                'phone' => mb_substr(trim((string) ($item['phone'] ?? '')), 0, 30),
                'subject' => mb_substr(trim((string) ($item['subject'] ?? '')), 0, 150),
                'message' => $message,
                'promotions' => $promotions === true || $promotions === 1 || $promotions === '1' || $promotions === 'true' ? 1 : 0,
                'created_at' => $createdAt ? date('Y-m-d H:i:s', $createdAt) : $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('contact_inquiries')->insert($rows);
        }

        unlink($path);
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_inquiries');
    }
};
