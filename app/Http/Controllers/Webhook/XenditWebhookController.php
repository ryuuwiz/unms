<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class XenditWebhookController extends Controller
{
    public function __construct(
        protected PaymentWebhookController $paymentWebhookController
    ) {}

    /**
     * Handle incoming Xendit webhook callback (kompatibilitas mundur).
     */
    public function handle(Request $request): JsonResponse
    {
        return $this->paymentWebhookController->handle($request, 'xendit');
    }
}
