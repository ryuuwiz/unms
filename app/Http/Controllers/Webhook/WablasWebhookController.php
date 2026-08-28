<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Wablas\WablasWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WablasWebhookController extends Controller
{
    public function __construct(
        protected WablasWebhookService $webhookService
    ) {}

    /**
     * Unified entrypoint for all WABLAS webhook callbacks.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Jika payload kosong tapi ada raw json body
        if (empty($payload)) {
            $payload = (array) json_decode($request->getContent(), true);
        }

        $result = $this->webhookService->process($payload);

        return response()->json([
            'status' => $result['status'],
            'type' => $result['type'],
            'message' => $result['message'],
        ], 200);
    }

    /**
     * Dedicated entrypoint for message delivery status tracking (DLR).
     */
    public function tracking(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (empty($payload)) {
            $payload = (array) json_decode($request->getContent(), true);
        }

        $this->webhookService->handleTrackingStatus($payload);

        return response()->json([
            'status' => true,
            'message' => 'Status tracking webhook diterima',
        ], 200);
    }

    /**
     * Dedicated entrypoint for customer incoming messages (Inbound Chat).
     */
    public function message(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (empty($payload)) {
            $payload = (array) json_decode($request->getContent(), true);
        }

        $reply = $this->webhookService->handleIncomingMessage($payload);

        return response()->json([
            'status' => true,
            'message' => 'Pesan masuk berhasil diproses',
            'auto_reply' => (bool) $reply,
        ], 200);
    }
}
