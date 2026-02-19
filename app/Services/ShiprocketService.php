<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ShiprocketService
{
    protected $email;
    protected $password;
    protected $baseUrl;

    public function __construct()
    {
        $this->email = config('services.shiprocket.email');
        $this->password = config('services.shiprocket.password');
        $this->baseUrl = 'https://apiv2.shiprocket.in/v1/external';
    }

    /**
     * Get and cache the Shiprocket token for 55 minutes
     */
    public function getToken()
    {
        return Cache::remember('shiprocket_token', now()->addMinutes(55), function () {
        $response = Http::withOptions([
            'verify' => 'C:/xampp/php/extras/ssl/cacert.pem' // manual override
        ])->timeout(20)->post('https://apiv2.shiprocket.in/v1/external/auth/login', [
            'email' => env('SHIPROCKET_EMAIL'),
            'password' => env('SHIPROCKET_PASSWORD'),
        ]);

        dd($response->json());

            if ($response->successful()) {
                return $response->json()['token'];
            }

            throw new \Exception('Shiprocket authentication failed: ' . $response->body());
        });
    }

    /**
     * Create a new order in Shiprocket
     */
    public function createOrder(array $orderData)
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->post($this->baseUrl . '/orders/create/adhoc', $orderData);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Shiprocket order creation failed: ' . $response->body());
    }

    /**
     * Optional: Cancel an order
     */
    public function cancelOrder($shiprocketOrderId)
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->get($this->baseUrl . '/orders/cancel', [
                'ids' => $shiprocketOrderId
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to cancel Shiprocket order: ' . $response->body());
    }

    /**
     * Optional: Get shipment tracking status
     */
    public function trackShipment($shipmentId)
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->get($this->baseUrl . "/courier/track/shipment/$shipmentId");

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to track shipment: ' . $response->body());
    }
}
