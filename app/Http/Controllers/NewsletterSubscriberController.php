<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterMessageMail;
use App\Mail\NewsletterWelcomeMail;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsletterSubscriberController extends Controller
{
    private const MAX_EMAILS_PER_MINUTE = 6;
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

        if ($this->reserveEmailSlots(1)) {
            $this->sendWelcome($email);
        } else {
            Log::info('Newsletter welcome skipped because the minute limit was reached.', [
                'email' => $email,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'You are in. Welcome to the circle.',
            'data' => [
                'id' => $item['id'],
            ],
        ], 201);
    }

    public function count()
    {
        return response()->json([
            'success' => true,
            'count' => count($this->readAll()),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function sendMessage(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:5000',
            'subscriber_id' => 'nullable|string|max:80',
        ]);

        $subject = trim(strip_tags($data['subject']));
        $message = trim(strip_tags($data['message']));

        if ($subject === '' || $message === '') {
            return response()->json([
                'success' => false,
                'message' => 'Subject and message are required.',
            ], 422);
        }

        $items = $this->readAll();

        if (!empty($data['subscriber_id'])) {
            $items = array_values(array_filter(
                $items,
                static fn ($item) => (string) ($item['id'] ?? '') === $data['subscriber_id']
            ));

            if ($items === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscriber not found.',
                ], 404);
            }
        }

        $recipients = [];

        foreach ($items as $item) {
            $email = strtolower(trim((string) ($item['email'] ?? '')));

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $recipients[$email] = $email;
        }

        $recipients = array_values($recipients);

        if ($recipients === []) {
            return response()->json([
                'success' => false,
                'message' => 'No valid subscriber email to send to.',
            ], 422);
        }

        if (count($recipients) > self::MAX_EMAILS_PER_MINUTE) {
            return response()->json([
                'success' => false,
                'message' => 'You can send up to 6 emails per minute. Choose fewer people.',
            ], 422);
        }

        if (!$this->reserveEmailSlots(count($recipients))) {
            return response()->json([
                'success' => false,
                'message' => 'Limit reached. You can send 6 newsletter emails per minute. Wait a minute and try again.',
                'limit' => self::MAX_EMAILS_PER_MINUTE,
            ], 429);
        }

        $sent = 0;
        $failed = [];

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new NewsletterMessageMail($subject, $message));
                $sent++;
            } catch (\Throwable $exception) {
                Log::warning('Newsletter message failed.', [
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);
                $failed[] = $email;
            }
        }

        if ($sent === 0) {
            return response()->json([
                'success' => false,
                'message' => 'The email could not be sent. Check the mail settings on the server.',
                'sent' => 0,
                'failed' => $failed,
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => $sent === 1 ? 'Email sent.' : "Email sent to {$sent} subscribers.",
            'sent' => $sent,
            'failed' => $failed,
        ]);
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

    private function reserveEmailSlots(int $count): bool
    {
        if ($count < 1) {
            return false;
        }

        $key = 'newsletter-mail:' . now()->format('YmdHi');

        try {
            return Cache::lock($key . ':lock', 5)->block(3, function () use ($key, $count) {
                $used = (int) Cache::get($key, 0);

                if ($used + $count > self::MAX_EMAILS_PER_MINUTE) {
                    return false;
                }

                Cache::put($key, $used + $count, now()->addMinutes(2));

                return true;
            });
        } catch (LockTimeoutException) {
            return false;
        }
    }

    private function sendWelcome(string $email): void
    {
        try {
            Mail::to($email)->send(new NewsletterWelcomeMail());
        } catch (\Throwable $exception) {
            Log::warning('Newsletter welcome email failed.', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
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
