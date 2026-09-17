<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContactInquiryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:30',
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:2000',
            'promotions' => 'nullable',
        ]);

        $item = [
            'id' => (string) Str::uuid(),
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'subject' => trim($data['subject']),
            'message' => trim($data['message']),
            'promotions' => $this->toBool($data['promotions'] ?? false),
            'created_at' => now()->toIso8601String(),
        ];

        $items = $this->readAll();
        array_unshift($items, $item);
        $this->writeAll($items);

        return response()->json([
            'success' => true,
            'message' => 'Message received. We will get back to you soon.',
            'data' => [
                'id' => $item['id'],
            ],
        ], 201);
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Contact inquiries fetched successfully.',
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
                'message' => 'Contact inquiry not found.',
            ], 404);
        }

        $this->writeAll($remaining);

        return response()->json([
            'success' => true,
            'message' => 'Contact inquiry deleted successfully.',
        ]);
    }

    private function readAll(): array
    {
        $path = config('contact_inquiries.file');

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
            config('contact_inquiries.file'),
            json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }
}
