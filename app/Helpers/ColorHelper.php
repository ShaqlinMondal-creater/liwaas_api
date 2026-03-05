<?php

namespace App\Helpers;

class ColorHelper
{
    private static $colors = null;

    private static function load()
    {
        if (self::$colors === null) {

            $path = storage_path('app/data/colors.json');
            $json = file_get_contents($path);
            $data = json_decode($json, true);

            self::$colors = collect($data['colors'] ?? [])
                ->keyBy('name');
        }

        return self::$colors;
    }

    public static function get($name)
    {
        $colors = self::load();

        if (!$name) return null;

        return [
            'name' => $name,
            'code' => $colors[$name]['code'] ?? null
        ];
    }
}
