<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        protected WhatsappWebhookService $webhookService
    ) {}

    /**
     * Unified entrypoint for all WhatsApp gateway webhook callbacks (GOWA/WAHA).
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Jika payload kosong tapi ada raw json body
        if (empty($payload)) {
            $payload = (array) json_decode($request->getContent(), true);
        }

        $gowaSysblas = $this->webhookService->resolveGowaSysblas($payload);
        if ($gowaSysblas && ! $this->webhookService->verifyGowaSignature($request, $gowaSysblas)) {
            return response()->json([
                'status' => false,
                'type' => 'unauthorized',
                'message' => 'Signature webhook tidak valid.',
            ], 401);
        }

        $result = $this->webhookService->process($payload);

        return response()->json([
            'status' => $result['status'],
            'type' => $result['type'],
            'message' => $result['message'],
        ], 200);
    }
}
