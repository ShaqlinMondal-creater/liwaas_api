<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsletterSubscriberController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:150',
        ]);

        $email = strtolower(trim($data['email']));
        $items = $this->readAll();

        foreach ($items as $existing) {
            if (strtolower((string) ($existing['email'] ?? '')) === $email) {
                return response()->json([
                    'success' => true,
                    'message' => 'You are already on the list.',
                    'data' => [
                        'id' => $existing['id'] ?? null,
                    ],
                ]);
            }
        }

        $item = [
            'id' => (string) Str::uuid(),
            'email' => $email,
            'created_at' => now()->toIso8601String(),
        ];

        array_unshift($items, $item);
        $this->writeAll($items);

        return response()->json([
            'success' => true,
            'message' => 'You are in. Welcome to the circle.',
            'data' => [
                'id' => $item['id'],
            ],
        ], 201);
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Newsletter subscribers fetched successfully.',
            'data' => $this->readAll(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function destroy(string $id)
    {
        $items = $this->readAll();
        $remaining = array_values(array_filter(
            $items,
            static fn ($item) => (string) ($item['id'] ?? '') !== $id
        ));

        if (count($remaining) === count($items)) {
            return response()->json([
                'success' => false,
                'message' => 'Subscriber not found.',
            ], 404);
        }

        $this->writeAll($remaining);

        return response()->json([
            'success' => true,
            'message' => 'Subscriber deleted successfully.',
        ]);
    }

    private function readAll(): array
    {
        $path = config('newsletter_subscribers.file');

        if (!Storage::disk('local')->exists($path)) {
            return [];
        }

        $decoded = json_decode(Storage::disk('local')->get($path), true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    private function writeAll(array $items): void
    {
        Storage::disk('local')->put(
            config('newsletter_subscribers.file'),
            json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
