<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('newsletter_subscribers')) {
            Schema::create('newsletter_subscribers', function (Blueprint $table) {
                $table->id();
                $table->string('email', 150)->unique();
                $table->timestamps();
            });
        }

        $path = storage_path('app/newsletter_subscribers.json');

        if (!is_file($path)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            return;
        }

        $now = now();
        $rows = [];
        $seen = [];

        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $email = strtolower(trim((string) ($item['email'] ?? '')));

            if ($email === '' || isset($seen[$email]) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $seen[$email] = true;
            $createdAt = strtotime((string) ($item['created_at'] ?? ''));

            $rows[] = [
                'email' => $email,
                'created_at' => $createdAt ? date('Y-m-d H:i:s', $createdAt) : $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('newsletter_subscribers')->insertOrIgnore($rows);
        }

        unlink($path);
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
