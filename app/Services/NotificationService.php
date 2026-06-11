<?php

namespace App\Services;

use App\DTO\SendNotificationData;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Jobs\SendBatchNotificationJob;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class NotificationService
{
    /**
     * @throws Throwable
     */
    public function send(SendNotificationData $data): array
    {
        $cacheKey = $data->idempotencyKey
            ? "notify_batch:{$data->idempotencyKey}"
            : null;

        if ($cacheKey && $cached = Redis::get($cacheKey)) {
            return json_decode($cached, true);
        }

        $existingBatch = NotificationBatch::where(
            'idempotency_key',
            $data->idempotencyKey
        )->first();

        if ($existingBatch) {
            return [
                'batch_id' => $existingBatch->id,
                'message' => 'Рассылка уже существует',
            ];
        }

        try {
            DB::beginTransaction();

            $batch = NotificationBatch::create([
                'idempotency_key' => $data->idempotencyKey,
                'type' => $data->type,
                'channel' => $data->channel,
                'message' => $data->message,
            ]);

            $priority = match ($data->type) {
                NotificationType::TRANSACTIONAL => NotificationPriority::HIGH,
                NotificationType::MARKETING => NotificationPriority::LOW,
            };

            $rows = [];

            foreach ($data->recipients as $recipient) {
                $rows[] = [
                    'batch_id' => $batch->id,
                    'recipient' => $recipient,
                    'channel' => $data->channel->value,
                    'message' => $data->message,
                    'priority' => $priority->value,
                    'status' => NotificationStatus::QUEUED,
                    'attempts' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            Notification::insert($rows);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        dispatch(new SendBatchNotificationJob($batch->id))
            ->onConnection(config('queue.default'));

        $response = [
            'batch_id' => $batch->id,
            'message' => 'Рассылка принята',
        ];

        if ($cacheKey) {
            Redis::setex(
                $cacheKey,
                3600,
                json_encode($response)
            );
        }

        return $response;
    }

    public function history(string $recipient): LengthAwarePaginator
    {
        $notifications = Notification::query()
            ->where('recipient', $recipient)
            ->latest()
            ->paginate(20);

        $notifications->getCollection()->transform(function (Notification $notification) {
            return [
                'id' => $notification->id,
                'batch_id' => $notification->batch_id,
                'channel' => $notification->channel,
                'message' => $notification->message,

                'recipient' => $notification->recipient,
                'status' => $notification->status,
                'status_label' => $notification->status->label(),

                'priority' => $notification->priority,

                'sent_at' => $notification->sent_at,
                'delivered_at' => $notification->delivered_at,
                'created_at' => $notification->created_at,
            ];
        });

        return $notifications;
    }
}
