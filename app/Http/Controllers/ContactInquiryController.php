<?php

namespace App\Http\Controllers;

use App\Models\ContactInquiry;
use Illuminate\Http\Request;

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

        $inquiry = ContactInquiry::query()->create([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'subject' => trim($data['subject']),
            'message' => trim($data['message']),
            'promotions' => $this->toBool($data['promotions'] ?? false),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message received. We will get back to you soon.',
            'data' => [
                'id' => (string) $inquiry->id,
            ],
        ], 201);
    }

    public function index()
    {
        $rows = ContactInquiry::query()
            ->orderByDesc('id')
            ->get()
            ->map(static fn (ContactInquiry $row) => [
                'id' => (string) $row->id,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'email' => $row->email,
                'phone' => $row->phone,
                'subject' => $row->subject,
                'message' => $row->message,
                'promotions' => (bool) $row->promotions,
                'created_at' => optional($row->created_at)->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Contact inquiries fetched successfully.',
            'data' => $rows,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function destroy(string $id)
    {
        $deleted = ContactInquiry::query()->whereKey($id)->delete();

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Contact inquiry not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact inquiry deleted successfully.',
        ]);
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
