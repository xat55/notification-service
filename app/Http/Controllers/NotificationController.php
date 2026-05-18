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
        $result = $this->notificationService->sendBulk(
            channel: $request->input('channel'),
            message: $request->input('message'),
            recipientIds: $request->input('recipient_ids'),
            priority: $request->input('priority', 'low'),
            idempotencyKey: $request->input('idempotency_key')
        );

        return response()->json([
            'batch_id' => $result['batch_id'],
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
