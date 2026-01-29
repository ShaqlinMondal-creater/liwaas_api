<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class FirebaseAuthService
{
    protected Auth $auth;

    public function __construct()
    {
        $credentialsPath = config('services.firebase.credentials');

        if (!$credentialsPath || !file_exists(base_path($credentialsPath))) {
            throw new \Exception('Firebase credentials file not found.');
        }

        $factory = (new Factory)
            ->withServiceAccount(base_path($credentialsPath));

        $this->auth = $factory->createAuth();
    }

    /**
     * Verify Firebase ID Token
     */
    public function verifyIdToken(string $idToken): array
    {
        try {
            $verifiedToken = $this->auth->verifyIdToken($idToken);
            return $verifiedToken->claims()->all();

        } catch (FailedToVerifyToken $e) {
            throw new \Exception('Invalid or expired Firebase token.');
        }
    }
}
