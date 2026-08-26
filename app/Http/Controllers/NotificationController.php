<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use App\Http\Requests\SendNotificationRequest;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function send(SendNotificationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->notificationService->sendBulk(
            channel: $validated['channel'],
            message: $validated['message'],
            recipientIds: $validated['recipient_ids'],
            priority: $validated['priority'] ?? 'low',
            idempotencyKey: $validated['idempotency_key'] ?? null
        );

        return response()->json([
            'batch_id' => $result['batch_id'],
            'created' => $result['created'] ?? 0,
            'skipped' => $result['skipped'] ?? 0,
            'message' => 'Notifications queued successfully'
        ], 202);
    }

    public function history(string $subscriberId): JsonResponse
    {
        $notifications = $this->notificationService->getHistory($subscriberId);

        return response()->json([
            'subscriber_id' => $subscriberId,
            'notifications' => $notifications
        ]);
    }
}
