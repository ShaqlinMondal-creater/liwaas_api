<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterMessageMail;
use App\Mail\NewsletterWelcomeMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NewsletterSubscriberController extends Controller
{
    private const MAX_EMAILS_PER_MINUTE = 6;

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:150',
        ]);

        $email = strtolower(trim($data['email']));
        $existing = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'You are already on the list.',
                'data' => [
                    'id' => (string) $existing->id,
                ],
            ]);
        }

        try {
            $subscriber = NewsletterSubscriber::query()->create([
                'email' => $email,
            ]);
        } catch (QueryException) {
            $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

            if ($subscriber) {
                return response()->json([
                    'success' => true,
                    'message' => 'You are already on the list.',
                    'data' => [
                        'id' => (string) $subscriber->id,
                    ],
                ]);
            }

            throw new \RuntimeException('Unable to save this subscriber.');
        }

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
                'id' => (string) $subscriber->id,
            ],
        ], 201);
    }

    public function count()
    {
        return response()->json([
            'success' => true,
            'count' => NewsletterSubscriber::query()->count(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function sendMessage(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:5000',
            'subscriber_id' => 'nullable|integer|min:1',
        ]);

        $subject = trim(strip_tags($data['subject']));
        $message = trim(strip_tags($data['message']));

        if ($subject === '' || $message === '') {
            return response()->json([
                'success' => false,
                'message' => 'Subject and message are required.',
            ], 422);
        }

        $query = NewsletterSubscriber::query()->orderByDesc('id');

        if (!empty($data['subscriber_id'])) {
            $query->whereKey($data['subscriber_id']);
        }

        $recipients = $query
            ->pluck('email')
            ->map(static fn ($email) => strtolower(trim((string) $email)))
            ->filter(static fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => empty($data['subscriber_id'])
                    ? 'No valid subscriber email to send to.'
                    : 'Subscriber not found.',
            ], empty($data['subscriber_id']) ? 422 : 404);
        }

        if ($recipients->count() > self::MAX_EMAILS_PER_MINUTE) {
            return response()->json([
                'success' => false,
                'message' => 'You can send up to 6 emails per minute. Choose fewer people.',
            ], 422);
        }

        if (!$this->reserveEmailSlots($recipients->count())) {
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
        $rows = NewsletterSubscriber::query()
            ->orderByDesc('id')
            ->get(['id', 'email', 'created_at'])
            ->map(static fn (NewsletterSubscriber $row) => [
                'id' => (string) $row->id,
                'email' => $row->email,
                'created_at' => optional($row->created_at)->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Newsletter subscribers fetched successfully.',
            'data' => $rows,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function destroy(string $id)
    {
        $deleted = NewsletterSubscriber::query()->whereKey($id)->delete();

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Subscriber not found.',
            ], 404);
        }

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
}
