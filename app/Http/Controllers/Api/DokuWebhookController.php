<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DokuPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DokuWebhookController extends Controller
{
    public function __construct(
        protected DokuPaymentService $paymentService
    ) {}

    /**
     * Handle incoming payment status webhook from DOKU.
     */
    public function handleNotification(Request $request): JsonResponse
    {
        if (! $this->paymentService->verifyNotificationSignature($request)) {
            Log::warning('Rejected DOKU notification with an invalid signature', [
                'ip' => $request->ip(),
                'request_id' => $request->header('Request-Id'),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid notification signature',
            ], 401);
        }

        $payload = $request->all();

        $processed = $this->paymentService->processNotification($payload);

        if (! $processed) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to process payment notification payload',
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Payment notification processed successfully',
        ], 200);
    }
}
