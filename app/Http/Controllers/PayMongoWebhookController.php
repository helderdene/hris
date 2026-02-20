<?php

namespace App\Http\Controllers;

use App\Services\Billing\PayMongoWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Paymongo\Exceptions\SignatureVerificationException;

class PayMongoWebhookController extends Controller
{
    /**
     * Handle incoming PayMongo webhook events.
     */
    public function handle(Request $request, PayMongoWebhookService $webhookService): Response
    {
        try {
            $event = $webhookService->validateAndParse($request);
            $webhookService->handleEvent($event);

            return response('', 200);
        } catch (SignatureVerificationException) {
            return response('Invalid signature.', 403);
        } catch (\Throwable $e) {
            Log::error('PayMongo webhook error: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return response('Webhook processing error.', 500);
        }
    }
}
