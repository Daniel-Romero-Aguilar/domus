<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GooglePlayDemoClient
{
    public function lookup(string $purchaseToken): ?array
    {
        $package = rawurlencode(config('billing_demo.package_name'));
        $token = rawurlencode($purchaseToken);
        try {
            $response = $this->request()->get(
                "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$package}/purchases/subscriptionsv2/tokens/{$token}",
            );
        } catch (ConnectionException) {
            abort(503, 'No se pudo conectar la prueba con Google Play.');
        }

        if (in_array($response->status(), [404, 410], true)) {
            return null;
        }

        // Keep tokens, credentials and Google's raw error response off the API.
        abort_unless($response->successful(), 503, 'No se pudo verificar la compra con Google Play.');
        $purchase = $response->json();
        abort_unless(is_array($purchase), 503, 'Google Play no devolvió una respuesta válida.');

        return $purchase;
    }

    public function acknowledge(string $purchaseToken): void
    {
        $package = rawurlencode(config('billing_demo.package_name'));
        $product = rawurlencode(config('billing_demo.product_id'));
        $token = rawurlencode($purchaseToken);
        try {
            $response = $this->request()->post(
                "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$package}/purchases/subscriptions/{$product}/tokens/{$token}:acknowledge",
                ['developerPayload' => 'Domus subscription demo'],
            );
        } catch (ConnectionException) {
            abort(503, 'No se pudo conectar la prueba con Google Play.');
        }

        abort_unless($response->successful(), 503, 'No se pudo finalizar la compra con Google Play. Reintenta la confirmación.');
    }

    private function request(): PendingRequest
    {
        $json = config('billing_demo.credentials_json');
        if (! is_string($json) || $json === '') {
            $path = config('billing_demo.credentials_path');
            abort_unless(is_string($path) && is_readable($path), 503, 'La prueba de compras todavía no está configurada.');
            $json = file_get_contents($path);
        }

        $credentials = json_decode($json, true);
        abort_unless(is_array($credentials)
            && ($credentials['type'] ?? null) === 'service_account'
            && ! empty($credentials['private_key'])
            && ! empty($credentials['client_email']),
            503, 'La prueba de compras todavía no está configurada.',
        );

        try {
            $accessToken = Cache::remember(
                'google-play-demo.oauth.'.hash('sha256', $json),
                now()->addMinutes(50),
                function () use ($credentials) {
                    $auth = new ServiceAccountCredentials(
                        'https://www.googleapis.com/auth/androidpublisher',
                        $credentials,
                    );
                    return $auth->fetchAuthToken()['access_token'] ?? null;
                },
            );
        } catch (\Throwable) {
            abort(503, 'No se pudo conectar la prueba con Google Play.');
        }

        abort_unless(is_string($accessToken) && $accessToken !== '', 503, 'No se pudo conectar la prueba con Google Play.');

        return Http::withToken($accessToken)->acceptJson()->timeout(20);
    }
}
