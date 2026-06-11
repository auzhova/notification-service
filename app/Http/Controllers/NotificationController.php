<?php

namespace App\Http\Controllers;

use App\DTO\SendNotificationData;
use App\Enums\NotificationType;
use App\Enums\NotificationChannel;
use App\Http\Requests\SendNotificationRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $service
    ) {}

    public function send(SendNotificationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $data = new SendNotificationData(
            type: NotificationType::from($validated['type']),
            channel: NotificationChannel::from($validated['channel']),
            message: $validated['message'],
            recipients: $validated['recipients'],
            idempotencyKey: $request->header('Idempotency-Key')
        );

        $response = $this->service->send($data);

        return response()->json($response, 202);
    }

    public function history(string $recipient): JsonResponse
    {
        $notifications = $this->service->history($recipient);

        return response()->json($notifications);
    }
}
