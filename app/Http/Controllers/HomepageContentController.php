<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HomepageContentController extends Controller
{
    public function show()
    {
        return response()->json([
            'success' => true,
            'message' => 'Homepage content fetched successfully.',
            'data' => $this->content(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function update(Request $request)
    {
        $request->validate([
            'key' => 'required|string|in:hero',
            'content' => 'required|array',
        ]);

        $content = $this->content();
        $key = (string) $request->input('key');
        $incoming = $request->input('content', []);

        if (!is_array($incoming)) {
            $incoming = [];
        }

        $content[$key] = $this->mergeSection($key, $incoming);
        $this->write($content);

        return response()->json([
            'success' => true,
            'message' => 'Homepage content updated successfully.',
            'data' => $content,
        ]);
    }

    private function content(): array
    {
        $defaults = [
            'hero' => $this->defaultsFor('hero'),
        ];

        $path = config('homepage_content.file');

        if (!Storage::disk('local')->exists($path)) {
            return $defaults;
        }

        $decoded = json_decode(Storage::disk('local')->get($path), true);

        if (!is_array($decoded)) {
            return $defaults;
        }

        foreach ($defaults as $key => $fallback) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                $defaults[$key] = $this->mergeSection($key, $decoded[$key]);
            }
        }

        return $defaults;
    }

    private function mergeSection(string $key, array $incoming): array
    {
        $merged = $this->defaultsFor($key);

        foreach ($merged as $field => $fallback) {
            if (!array_key_exists($field, $incoming)) {
                continue;
            }

            $value = is_string($incoming[$field]) ? trim($incoming[$field]) : $incoming[$field];
            $merged[$field] = $value === '' || $value === null ? $fallback : $value;
        }

        return $merged;
    }

    private function defaultsFor(string $key): array
    {
        return config('homepage_content.' . $key, []);
    }

    private function write(array $content): void
    {
        Storage::disk('local')->put(
            config('homepage_content.file'),
            json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
