<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmService
{
    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function accessToken(): string
    {
        return Cache::remember('ma_livraison_fcm_access_token', now()->addMinutes(50), function () {
            $path = config('services.firebase.credentials');
            if (! $path || ! is_file($path)) {
                throw new \RuntimeException('Fichier de service Firebase introuvable.');
            }

            $serviceAccount = json_decode(file_get_contents($path), true);
            if (! is_array($serviceAccount) || empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
                throw new \RuntimeException('Fichier de service Firebase invalide.');
            }

            $now = time();
            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $unsigned = $header.'.'.$claims;
            $signature = '';
            $key = openssl_pkey_get_private($serviceAccount['private_key']);
            if (! $key || ! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Impossible de signer le jeton de service Firebase.');
            }
            $assertion = $unsigned.'.'.$this->base64UrlEncode($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);
            if (! $response->successful() || ! $response->json('access_token')) {
                throw new \RuntimeException('Authentification OAuth Firebase impossible : '.$response->body());
            }
            return $response->json('access_token');
        });
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        foreach ($user->fcmTokens()->get() as $device) {
            $this->send($device, $title, $body, $data);
        }
    }

    public function sendToUsers(iterable $users, string $title, string $body, array $data = []): void
    {
        foreach ($users as $user) {
            $this->sendToUser($user, $title, $body, $data);
        }
    }

    private function send(FcmToken $device, string $title, string $body, array $data): void
    {
        try {
            $projectId = config('services.firebase.project_id');
            if (! $projectId) throw new \RuntimeException('FIREBASE_PROJECT_ID non configuré.');

            $response = Http::withToken($this->accessToken())->acceptJson()->post(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                ['message' => [
                    'token' => $device->token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map(static fn ($value) => (string) $value, $data),
                    'android' => ['priority' => 'HIGH', 'notification' => ['channel_id' => 'fcm_fallback_notification_channel']],
                    'webpush' => ['headers' => ['Urgency' => 'high']],
                ]]
            );

            if ($response->successful()) return;
            $errorCode = data_get($response->json(), 'error.details.0.errorCode');
            if ($errorCode === 'UNREGISTERED' || $response->status() === 404) {
                $device->delete();
            }
            Log::warning('Échec envoi FCM', ['user_id' => $device->user_id, 'status' => $response->status(), 'response' => $response->json()]);
        } catch (Throwable $e) {
            Log::error('Erreur FCM', ['user_id' => $device->user_id, 'message' => $e->getMessage()]);
        }
    }
}
