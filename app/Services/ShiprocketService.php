<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShiprocketService
{
    protected $baseUrl = "https://apiv2.shiprocket.in/v1/external";
    protected $token;

    public function __construct()
    {
        $this->token = $this->authenticate();
    }

    public function authenticate()
    {
        $response = Http::post($this->baseUrl . "/auth/login", [
            "email" => config('services.shiprocket.email'),
            "password" => config('services.shiprocket.password'),
        ]);

        return $response['token'] ?? null;
    }

    public function createOrder($orderData)
    {
        $response = Http::withToken($this->token)
            ->post($this->baseUrl . "/orders/create/adhoc", $orderData);

        return $response->json();
    }

    public function trackOrder($shipmentId)
    {
        $response = Http::withToken($this->token)
            ->get($this->baseUrl . "/courier/track?shipment_id=" . $shipmentId);

        return $response->json();
    }
}
