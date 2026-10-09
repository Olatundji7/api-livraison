<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\PricingService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FedaPayService
{
    private function baseUrl(): string
    {
        return rtrim((string) config('services.fedapay.base_url', env('FEDAPAY_API_BASE_URL', 'https://sandbox-api.fedapay.com/v1')), '/');
    }

    private function secretKey(): string
    {
        return trim((string) config('services.fedapay.secret_key', env('FEDAPAY_SECRET_KEY', '')));
    }

    public function initiate(Order $order): PaymentTransaction
    {
        $secret = $this->secretKey();
        if ($secret === '') throw new RuntimeException('FedaPay n’est pas configuré. Ajoutez FEDAPAY_SECRET_KEY dans .env.');

        $amount = (int) $order->total;
        if ($amount <= 0) throw new RuntimeException('Le montant final de la commande doit être supérieur à 0 FCFA.');

        $client = $order->client;
        $names = preg_split('/\s+/', trim((string) $client?->name), 2) ?: [];
        $firstname = $names[0] ?? 'Client';
        $lastname = $names[1] ?? 'MA Livraison';
        $callback = (string) config('services.fedapay.callback_url', env('FEDAPAY_CALLBACK_URL', ''));

        $payload = [
            'description' => 'MA Livraison - Commande #' . $order->id,
            'amount' => $amount,
            'currency' => ['iso' => 'XOF'],
            'customer' => [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $client?->email,
                'phone_number' => [
                    'number' => $client?->phone,
                    'country' => 'BJ',
                ],
            ],
        ];
        if ($callback !== '') $payload['callback_url'] = $callback;

        $create = Http::withToken($secret)->acceptJson()->asJson()->timeout(30)
            ->post($this->baseUrl() . '/transactions', $payload);
        if ($create->failed()) throw new RuntimeException($this->providerError($create));

        $created = $create->json();
        $transactionId = $this->findValue($created, ['id']);
        if ($transactionId === null) throw new RuntimeException('FedaPay n’a pas retourné l’identifiant de transaction.');

        $tokenResponse = Http::withToken($secret)->acceptJson()->asJson()->timeout(30)
            ->post($this->baseUrl() . '/transactions/' . $transactionId . '/token');
        if ($tokenResponse->failed()) throw new RuntimeException($this->providerError($tokenResponse));

        $tokenData = $tokenResponse->json();
        $paymentUrl = $this->findValue($tokenData, ['url', 'payment_url']);
        $token = $this->findValue($tokenData, ['token']);
        if (! $paymentUrl) throw new RuntimeException('FedaPay n’a pas retourné le lien de paiement.');

        return PaymentTransaction::create([
            'order_id' => $order->id,
            'reference' => 'ML-' . $order->id . '-' . Str::upper(Str::random(10)),
            'provider' => 'fedapay',
            'provider_transaction_id' => (string) $transactionId,
            'amount' => $amount,
            'fee_amount' => 0,
            'merchant_net_amount' => $amount,
            'currency' => 'XOF',
            'status' => 'pending',
            'payment_url' => $paymentUrl,
            'metadata' => [
                'token' => $token,
                'environment' => env('FEDAPAY_ENV', 'sandbox'),
                'service_fee_envelope' => PricingService::FRAIS_SERVICE,
            ],
        ]);
    }

    public function refresh(PaymentTransaction $payment): PaymentTransaction
    {
        $secret = $this->secretKey();
        if ($secret === '') throw new RuntimeException('FedaPay n’est pas configuré.');

        $response = Http::withToken($secret)->acceptJson()->timeout(30)
            ->get($this->baseUrl() . '/transactions/' . $payment->provider_transaction_id);
        if ($response->failed()) throw new RuntimeException($this->providerError($response));

        $data = $response->json();
        $status = strtolower((string) ($this->findValue($data, ['status']) ?? 'pending'));
        $local = match ($status) {
            'approved', 'paid', 'successful', 'success' => 'paid',
            'canceled', 'cancelled' => 'cancelled',
            'declined', 'failed', 'failure' => 'failed',
            default => 'pending',
        };

        $payment->update([
            'status' => $local,
            'paid_at' => $local === 'paid' ? ($payment->paid_at ?? now()) : $payment->paid_at,
            'metadata' => array_merge($payment->metadata ?? [], ['provider_status' => $status]),
        ]);

        $order = $payment->order()->first();
        if ($local === 'paid' && $order) {
            $fee = $this->providerFee($data, (int) $payment->amount);
            $merchantNet = max(0, (int) $payment->amount - $fee);
            $serviceFee = (int) ($order->service_fee ?: PricingService::FRAIS_SERVICE);
            $netServiceRevenue = max(0, $serviceFee - $fee);

            $payment->update([
                'fee_amount' => $fee,
                'merchant_net_amount' => $merchantNet,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'fee_source' => $this->feeSource($data),
                    'service_fee_envelope' => $serviceFee,
                    'net_service_revenue' => $netServiceRevenue,
                ]),
            ]);

            $order->update([
                'payment_status' => 'paye',
                'fedapay_fee' => $fee,
                'net_service_revenue' => $netServiceRevenue,
            ]);
        } elseif ($local === 'failed' || $local === 'cancelled') {
            $payment->order()->update(['payment_status' => 'echoue']);
        }

        return $payment->refresh();
    }

    private function providerFee(array $data, int $amount): int
    {
        $fee = $data['fees'] ?? data_get($data, 'transaction.fees') ?? data_get($data, 'data.fees');
        if (is_numeric($fee)) return max(0, (int) round((float) $fee));

        $commission = $data['commission'] ?? data_get($data, 'transaction.commission') ?? data_get($data, 'data.commission');
        $fixed = $data['fixed_commission'] ?? data_get($data, 'transaction.fixed_commission') ?? data_get($data, 'data.fixed_commission');
        if (is_numeric($commission) || is_numeric($fixed)) {
            return max(0, (int) round((float) ($commission ?? 0) + (float) ($fixed ?? 0)));
        }

        $rate = (float) env('FEDAPAY_FEE_RATE', 0.018);
        $fixedFallback = (float) env('FEDAPAY_FIXED_FEE', 0);
        return max(0, (int) round(($amount * $rate) + $fixedFallback));
    }

    private function feeSource(array $data): string
    {
        if (is_numeric($data['fees'] ?? null) || is_numeric(data_get($data, 'transaction.fees')) || is_numeric(data_get($data, 'data.fees'))) return 'fedapay_fees';
        if (is_numeric($data['commission'] ?? null) || is_numeric($data['fixed_commission'] ?? null)) return 'fedapay_commission';
        return 'configured_estimate';
    }

    private function providerError(Response $response): string
    {
        $json = $response->json();
        if (is_array($json)) return 'FedaPay : ' . (($json['message'] ?? $json['error'] ?? null) ?: json_encode($json));
        return 'FedaPay : HTTP ' . $response->status();
    }

    private function findValue(mixed $value, array $keys): mixed
    {
        if (! is_array($value)) return null;
        foreach ($keys as $key) {
            if (array_key_exists($key, $value) && $value[$key] !== null) return $value[$key];
        }
        foreach ($value as $child) {
            $found = $this->findValue($child, $keys);
            if ($found !== null) return $found;
        }
        return null;
    }
}
