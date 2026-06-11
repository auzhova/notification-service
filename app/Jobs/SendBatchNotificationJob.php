<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBatchNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $batchId
    ) {}

    public function handle(): void
    {
        Notification::where('batch_id', $this->batchId)
            ->where('status', NotificationStatus::QUEUED)
            ->select('id', 'priority')
            ->chunkById(500, function ($notifications) {
                foreach ($notifications as $notification) {
                    dispatch(new SendNotificationJob($notification->id));
                }
            });
    }
}
