<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\ProviderFactory;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    private const MAX_ATTEMPTS = 3;

    public int $tries = self::MAX_ATTEMPTS;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public int $notificationId
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(ProviderFactory $factory): void
    {
        $notification = Notification::find($this->notificationId);

        if (!$notification) {
            return;
        }
        if (!$notification->lockProcessing()) {
            return;
        }

        $notification->refresh();

        try {
            $provider = $factory->make($notification->channel);

            try {
                $result = $provider->send($notification->recipient, $notification->message);
            } catch (Throwable $e) {
                if (!$this->processFailure($notification, $e->getMessage())) {
                    return;
                }
                throw $e;
            }

            if ($result->success) {
                $notification->markAsSent($result->providerMessageId);
                // Статус delivered должен приходить от провайдера, заглушка
                return;
            }

            if (!$this->processFailure($notification, $result->error ?? 'Ошибка отправки')) {
                return;
            }

            throw new Exception(
                $result->error ?? 'Временная ошибка'
            );
        } finally {
            $notification->unlockProcessing();
        }
    }

    private function processFailure(
        Notification $notification,
        string $error
    ): bool {
        $notification->incrementAttempts($error);

        if ($notification->attempts >= self::MAX_ATTEMPTS) {
            $notification->markAsFailed($error);
            return false;
        }

        return true;
    }
}
