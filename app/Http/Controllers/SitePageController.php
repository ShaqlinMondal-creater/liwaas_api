<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SitePageController extends Controller
{
    public function getPages()
    {
        return response()->json([
            'success' => true,
            'message' => 'Site pages fetched successfully.',
            'data' => $this->enabledMap(),
            'items' => $this->itemList(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function updatePages(Request $request)
    {
        $catalog = $this->catalog();
        $current = $this->enabledMap();

        if ($request->filled('key')) {
            $request->validate([
                'key' => 'required|string|in:' . implode(',', array_keys($catalog)),
                'enabled' => 'required',
            ]);

            $current[$request->key] = $this->toBool($request->enabled);
        } else {
            $request->validate([
                'pages' => 'required|array',
            ]);

            foreach ($request->pages as $key => $enabled) {
                if (!array_key_exists($key, $catalog)) {
                    continue;
                }

                $current[$key] = $this->toBool($enabled);
            }
        }

        Storage::disk('local')->put(
            config('site_pages.file'),
            json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return response()->json([
            'success' => true,
            'message' => 'Site pages updated successfully.',
            'data' => $current,
            'items' => $this->itemList($current),
        ]);
    }

    private function catalog(): array
    {
        return config('site_pages.items', []);
    }

    private function defaults(): array
    {
        $defaults = [];

        foreach ($this->catalog() as $key => $item) {
            $defaults[$key] = (bool) ($item['default'] ?? true);
        }

        return $defaults;
    }

    private function enabledMap(): array
    {
        $defaults = $this->defaults();
        $path = config('site_pages.file');

        if (!Storage::disk('local')->exists($path)) {
            return $defaults;
        }

        $decoded = json_decode(Storage::disk('local')->get($path), true);

        if (!is_array($decoded)) {
            return $defaults;
        }

        $enabled = $defaults;

        foreach ($defaults as $key => $fallback) {
            if (array_key_exists($key, $decoded)) {
                $enabled[$key] = $this->toBool($decoded[$key]);
            }
        }

        return $enabled;
    }

    private function itemList(?array $enabled = null): array
    {
        $enabled ??= $this->enabledMap();
        $items = [];

        foreach ($this->catalog() as $key => $item) {
            $items[] = [
                'key' => $key,
                'label' => $item['label'] ?? $key,
                'description' => $item['description'] ?? '',
                'path' => $item['path'] ?? '',
                'enabled' => (bool) ($enabled[$key] ?? ($item['default'] ?? true)),
                'default' => (bool) ($item['default'] ?? true),
            ];
        }

        return $items;
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
