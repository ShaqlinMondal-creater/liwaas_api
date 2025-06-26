<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use SimpleSoftwareIO\QrCode\Generator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    // public function boot()
    // {
    //     QrCode::format('png'); // Ensures the default format is png

    //     // Force GD driver (not Imagick)
    //     app()->bind('qrCode', function () {
    //         return new Generator(new \SimpleSoftwareIO\QrCode\Bacon\QrCodeGenerator(
    //             new \BaconQrCode\Renderer\Image\Renderer(
    //                 new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
    //                 new \BaconQrCode\Renderer\Image\GDLibImageBackEnd() // ✅ USE GD NOT IMAGICK
    //             )
    //         ));
    //     });
    // }
}
