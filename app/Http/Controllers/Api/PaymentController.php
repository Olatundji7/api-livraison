<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\FedaPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(private FedaPayService $fedaPay) {}

    public function initiate(Request $request, Order $order)
    {
        $user = $request->user();
        if (! $user->isAdmin() && $order->client_id !== $user->id) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        if ($order->payment_status === 'paye') {
            return response()->json(['message' => 'Cette commande est déjà payée.'], 409);
        }

        try {
            $payment = PaymentTransaction::where('order_id', $order->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if (! $payment) {
                $payment = $this->fedaPay->initiate($order->load('client'));
            }

            return response()->json([
                'message' => 'Paiement à effectuer.',
                'payment' => [
                    'reference' => $payment->reference,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'payment_url' => $payment->payment_url,
                ],
            ], 201);
        } catch (Throwable $e) {
            Log::error('Payment initiation failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function status(Request $request, string $reference)
    {
        $payment = PaymentTransaction::where('reference', $reference)->firstOrFail();
        $order = $payment->order;
        if (! $request->user()->isAdmin() && $order->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        try {
            $payment = $this->fedaPay->refresh($payment);
        } catch (Throwable $e) {
            Log::warning('Payment refresh failed', ['reference' => $reference, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'payment' => $payment->fresh(),
            'order' => $order->fresh(),
        ]);
    }

    public function webhook(Request $request)
    {
        $providerId = data_get($request->all(), 'id')
            ?? data_get($request->all(), 'transaction.id')
            ?? data_get($request->all(), 'data.id');

        if (! $providerId) {
            return response()->json(['message' => 'Transaction provider manquante.'], 422);
        }

        $payment = PaymentTransaction::where('provider_transaction_id', (string) $providerId)->first();
        if (! $payment) {
            return response()->json(['message' => 'Transaction inconnue.'], 404);
        }

        try {
            $this->fedaPay->refresh($payment);
        } catch (Throwable $e) {
            Log::error('FedaPay webhook refresh failed', ['provider_id' => $providerId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Webhook reçu mais vérification échouée.'], 500);
        }

        return response()->json(['message' => 'Webhook traité.']);
    }
}
